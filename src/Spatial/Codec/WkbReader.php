<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial\Codec;

use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;

use function array_chunk;
use function array_values;
use function intdiv;
use function is_nan;
use function ord;
use function sprintf;
use function strlen;
use function unpack;

/**
 * Reads the ISO and extended (PostGIS) well-known binary formats, in both byte orders.
 *
 * @internal
 */
final class WkbReader
{
    private const FLAG_Z    = 0x80000000;
    private const FLAG_M    = 0x40000000;
    private const FLAG_SRID = 0x20000000;

    private int $offset = 0;

    private readonly int $length;

    private function __construct(private readonly string $data)
    {
        $this->length = strlen($data);
    }

    /**
     * @param int $srid The SRID of the geometry, unless the value carries one.
     *
     * @throws InvalidGeometry
     */
    public static function read(string $wkb, int $srid = 0): Geometry
    {
        $reader   = new self($wkb);
        $geometry = $reader->readGeometry(null, $srid, true);

        if ($reader->offset !== $reader->length) {
            throw InvalidGeometry::malformedWkb(sprintf('unexpected data at offset %d', $reader->offset));
        }

        return $geometry;
    }

    /** @throws InvalidGeometry */
    private function readGeometry(?Dimension $parentDimension, int $srid, bool $topLevel): Geometry
    {
        [$kind, $dimension, $coordinates, $srid] = $this->readParts($parentDimension, $srid, $topLevel);

        return Geometry::create($kind, $dimension, $coordinates, $srid);
    }

    /**
     * @return array{GeometryKind, Dimension, list<mixed>, int}
     *
     * @throws InvalidGeometry
     */
    private function readParts(?Dimension $parentDimension, int $srid, bool $topLevel): array
    {
        $byteOrder = $this->readByte();

        if ($byteOrder !== 0 && $byteOrder !== 1) {
            throw InvalidGeometry::malformedWkb(sprintf(
                'invalid byte order %d at offset %d',
                $byteOrder,
                $this->offset - 1,
            ));
        }

        $littleEndian = $byteOrder === 1;
        $typeWord     = $this->readUInt32($littleEndian);
        $hasZ         = ($typeWord & self::FLAG_Z) !== 0;
        $hasM         = ($typeWord & self::FLAG_M) !== 0;
        $code         = $typeWord & 0x0FFFFFFF;

        if ($code >= 1000) {
            $isoDimension = intdiv($code, 1000);
            $code        %= 1000;

            if ($isoDimension > 3) {
                throw InvalidGeometry::unsupportedWkbType($typeWord);
            }

            $hasZ = $hasZ || $isoDimension === 1 || $isoDimension === 3;
            $hasM = $hasM || $isoDimension === 2 || $isoDimension === 3;
        }

        $kind = GeometryKind::fromWkbCode($code);

        if ($kind === GeometryKind::GEOMETRY) {
            throw InvalidGeometry::unsupportedWkbType($code);
        }

        if (($typeWord & self::FLAG_SRID) !== 0) {
            if (! $topLevel) {
                throw InvalidGeometry::malformedWkb('the members of a collection cannot carry an SRID');
            }

            $embeddedSrid = $this->readUInt32($littleEndian);

            // PostGIS writes the unknown SRID as 0 (or a negative number in old versions)
            if ($embeddedSrid > 0x7FFFFFFF) {
                $embeddedSrid = 0;
            }

            if ($srid !== 0 && $embeddedSrid !== 0 && $embeddedSrid !== $srid) {
                throw InvalidGeometry::conflictingSrid($embeddedSrid, $srid);
            }

            if ($embeddedSrid !== 0) {
                $srid = $embeddedSrid;
            }
        }

        $dimension = Dimension::fromFlags($hasZ, $hasM);

        if ($parentDimension !== null && $dimension !== $parentDimension) {
            throw InvalidGeometry::mixedDimensions($parentDimension, $dimension);
        }

        $coordinates = match ($kind) {
            GeometryKind::POINT => $this->readPoint($dimension, $littleEndian),
            GeometryKind::LINESTRING => $this->readPositions($dimension, $littleEndian),
            GeometryKind::POLYGON => $this->readRings($dimension, $littleEndian),
            default => $this->readMembers($kind, $dimension, $srid, $littleEndian),
        };

        return [$kind, $dimension, $coordinates, $srid];
    }

    /**
     * @return list<float>
     *
     * @throws InvalidGeometry
     */
    private function readPoint(Dimension $dimension, bool $littleEndian): array
    {
        $position = $this->readDoubles($dimension->getCoordinateCount(), $littleEndian);

        foreach ($position as $coordinate) {
            if (! is_nan($coordinate)) {
                return $position;
            }
        }

        // an empty point is encoded with NaN coordinates
        return [];
    }

    /**
     * @return list<list<float>>
     *
     * @throws InvalidGeometry
     */
    private function readPositions(Dimension $dimension, bool $littleEndian): array
    {
        $count = $this->readUInt32($littleEndian);

        if ($count === 0) {
            return [];
        }

        $coordinateCount = $dimension->getCoordinateCount();

        return array_chunk($this->readDoubles($count * $coordinateCount, $littleEndian), $coordinateCount);
    }

    /**
     * @return list<list<list<float>>>
     *
     * @throws InvalidGeometry
     */
    private function readRings(Dimension $dimension, bool $littleEndian): array
    {
        $count = $this->readUInt32($littleEndian);
        $this->ensureAvailable($count * 4);

        $rings = [];

        for ($i = 0; $i < $count; $i++) {
            $rings[] = $this->readPositions($dimension, $littleEndian);
        }

        return $rings;
    }

    /**
     * @return list<mixed>
     *
     * @throws InvalidGeometry
     */
    private function readMembers(GeometryKind $kind, Dimension $dimension, int $srid, bool $littleEndian): array
    {
        $count = $this->readUInt32($littleEndian);
        $this->ensureAvailable($count * 9);

        $memberKind = $kind->getMemberKind();
        $members    = [];

        for ($i = 0; $i < $count; $i++) {
            if ($memberKind === null) {
                $members[] = $this->readGeometry($dimension, $srid, false);

                continue;
            }

            [$actualKind, , $coordinates] = $this->readParts($dimension, $srid, false);

            if ($actualKind !== $memberKind) {
                throw InvalidGeometry::invalidStructure(
                    $kind,
                    sprintf('its members must be of type %s, got %s', $memberKind->value, $actualKind->value),
                );
            }

            $members[] = $coordinates;
        }

        return $members;
    }

    /** @throws InvalidGeometry */
    private function readByte(): int
    {
        $this->ensureAvailable(1);

        return ord($this->data[$this->offset++]);
    }

    /** @throws InvalidGeometry */
    private function readUInt32(bool $littleEndian): int
    {
        $this->ensureAvailable(4);

        /** @var array{1: int} $value */
        $value         = unpack($littleEndian ? 'V' : 'N', $this->data, $this->offset);
        $this->offset += 4;

        return $value[1];
    }

    /**
     * @return list<float>
     *
     * @throws InvalidGeometry
     */
    private function readDoubles(int $count, bool $littleEndian): array
    {
        $this->ensureAvailable($count * 8);

        /** @var array<int, float> $values */
        $values        = unpack(($littleEndian ? 'e' : 'E') . $count, $this->data, $this->offset);
        $this->offset += $count * 8;

        return array_values($values);
    }

    /** @throws InvalidGeometry */
    private function ensureAvailable(int $bytes): void
    {
        if ($this->length - $this->offset < $bytes) {
            throw InvalidGeometry::malformedWkb(sprintf(
                'expected %d more bytes at offset %d, %d available',
                $bytes,
                $this->offset,
                $this->length - $this->offset,
            ));
        }
    }
}
