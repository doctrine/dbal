<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\SQLite;

use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;

use function array_chunk;
use function array_merge;
use function array_values;
use function count;
use function intdiv;
use function max;
use function min;
use function ord;
use function pack;
use function sprintf;
use function strlen;
use function unpack;

use const INF;

/**
 * Encodes and decodes the internal geometry format of SpatiaLite (BLOB-Geometry), which spatial functions accept
 * and the triggers of registered geometry columns require.
 *
 * @internal
 *
 * @link https://www.gaia-gis.it/gaia-sins/BLOB-Geometry.html
 */
final class SpatiaLiteBlob
{
    private const START   = 0x00;
    private const MBR_END = 0x7C;
    private const ENTITY  = 0x69;
    private const END     = 0xFE;

    private int $offset = 0;

    private bool $littleEndian = true;

    private function __construct(private readonly string $data)
    {
    }

    /** @throws InvalidGeometry */
    public static function encode(Geometry $geometry): string
    {
        $extendedKind = $geometry->findExtendedKind();

        if ($extendedKind !== null) {
            throw InvalidGeometry::unsupportedKind('SQLite', $extendedKind);
        }

        if ($geometry->isEmpty() || self::containsEmptyMember($geometry)) {
            throw InvalidGeometry::unsupportedEmptyGeometry('SQLite');
        }

        $bounds = [INF, INF, -INF, -INF];
        $body   = self::encodeBody($geometry, $bounds);

        return pack('CCV', self::START, 1, $geometry->getSrid())
            . pack('e4', ...$bounds)
            . pack('CV', self::MBR_END, self::getClassType($geometry->getKind(), $geometry->getDimension()))
            . $body
            . pack('C', self::END);
    }

    /** @throws InvalidGeometry */
    public static function decode(string $blob): Geometry
    {
        $length = strlen($blob);

        if ($length < 44 || ord($blob[0]) !== self::START || ord($blob[38]) !== self::MBR_END) {
            throw self::malformed('not a SpatiaLite geometry');
        }

        if (ord($blob[$length - 1]) !== self::END) {
            throw self::malformed('missing end marker');
        }

        $decoder = new self($blob);

        $decoder->littleEndian = match (ord($blob[1])) {
            0x01 => true,
            0x00 => false,
            default => throw self::malformed('unsupported encoding (compressed TinyPoint?)'),
        };

        $decoder->offset = 2;
        $srid            = $decoder->readInt();
        $decoder->offset = 39;

        [$kind, $dimension] = self::parseClassType($decoder->readInt());

        $geometry = $decoder->readGeometry($kind, $dimension, $srid);
        $offset   = $decoder->getOffset();

        if ($offset !== $length - 1) {
            throw self::malformed(sprintf('unexpected data at offset %d', $offset));
        }

        return $geometry;
    }

    /** @param array{float, float, float, float} $bounds */
    private static function encodeBody(Geometry $geometry, array &$bounds): string
    {
        $kind      = $geometry->getKind();
        $dimension = $geometry->getDimension();

        if ($kind === GeometryKind::GEOMETRYCOLLECTION || $kind->getMemberKind() !== null) {
            $members = $geometry->getGeometries();
            $body    = pack('V', count($members));

            foreach ($members as $member) {
                $body .= pack('CV', self::ENTITY, self::getClassType($member->getKind(), $dimension))
                    . self::encodeBody($member, $bounds);
            }

            return $body;
        }

        $coordinates = $geometry->getCoordinates();

        switch ($kind) {
            case GeometryKind::POINT:
                /** @var list<float> $position */
                $position = $coordinates;
                self::extendBounds($bounds, $position);

                return pack('e*', ...$position);

            case GeometryKind::LINESTRING:
                /** @var list<list<float>> $positions */
                $positions = $coordinates;

                return self::encodePositions($positions, $bounds);

            default:
                /** @var list<list<list<float>>> $rings */
                $rings = $coordinates;
                $body  = pack('V', count($rings));

                foreach ($rings as $ring) {
                    $body .= self::encodePositions($ring, $bounds);
                }

                return $body;
        }
    }

    /**
     * @param list<list<float>>                 $positions
     * @param array{float, float, float, float} $bounds
     */
    private static function encodePositions(array $positions, array &$bounds): string
    {
        foreach ($positions as $position) {
            self::extendBounds($bounds, $position);
        }

        return pack('V', count($positions)) . pack('e*', ...array_merge(...$positions));
    }

    /**
     * @param array{float, float, float, float} $bounds
     * @param list<float>                       $position
     */
    private static function extendBounds(array &$bounds, array $position): void
    {
        $bounds = [
            min($bounds[0], $position[0]),
            min($bounds[1], $position[1]),
            max($bounds[2], $position[0]),
            max($bounds[3], $position[1]),
        ];
    }

    private static function containsEmptyMember(Geometry $geometry): bool
    {
        $kind = $geometry->getKind();

        if ($kind !== GeometryKind::GEOMETRYCOLLECTION && $kind->getMemberKind() === null) {
            return false;
        }

        foreach ($geometry->getGeometries() as $member) {
            if ($member->isEmpty() || self::containsEmptyMember($member)) {
                return true;
            }
        }

        return false;
    }

    /** @throws InvalidGeometry */
    private function readGeometry(GeometryKind $kind, Dimension $dimension, int $srid): Geometry
    {
        if ($kind === GeometryKind::GEOMETRYCOLLECTION || $kind->getMemberKind() !== null) {
            $count   = $this->readInt();
            $members = [];

            for ($i = 0; $i < $count; $i++) {
                if ($this->readByte() !== self::ENTITY) {
                    throw self::malformed(sprintf('missing entity marker at offset %d', $this->offset - 1));
                }

                [$memberKind, $memberDimension] = self::parseClassType($this->readInt());

                if ($memberDimension !== $dimension) {
                    throw InvalidGeometry::mixedDimensions($dimension, $memberDimension);
                }

                if ($kind->getMemberKind() !== null && $memberKind !== $kind->getMemberKind()) {
                    throw InvalidGeometry::invalidStructure($kind, 'unexpected member type ' . $memberKind->value);
                }

                $members[] = $this->readGeometry($memberKind, $dimension, $srid);
            }

            if ($kind === GeometryKind::GEOMETRYCOLLECTION) {
                return Geometry::create($kind, $dimension, $members, $srid);
            }

            $coordinates = [];

            foreach ($members as $member) {
                $coordinates[] = $member->getCoordinates();
            }

            return Geometry::create($kind, $dimension, $coordinates, $srid);
        }

        $coordinateCount = $dimension->getCoordinateCount();

        $coordinates = match ($kind) {
            GeometryKind::POINT => $this->readDoubles($coordinateCount),
            GeometryKind::LINESTRING => $this->readPositions($coordinateCount),
            default => $this->readRings($coordinateCount),
        };

        return Geometry::create($kind, $dimension, $coordinates, $srid);
    }

    /**
     * @param int<2, 4> $coordinateCount
     *
     * @return list<list<float>>
     *
     * @throws InvalidGeometry
     */
    private function readPositions(int $coordinateCount): array
    {
        $count = $this->readInt();

        if ($count === 0) {
            return [];
        }

        return array_chunk($this->readDoubles($count * $coordinateCount), $coordinateCount);
    }

    /**
     * @param int<2, 4> $coordinateCount
     *
     * @return list<list<list<float>>>
     *
     * @throws InvalidGeometry
     */
    private function readRings(int $coordinateCount): array
    {
        $count = $this->readInt();
        $this->ensureAvailable($count * 4);

        $rings = [];

        for ($i = 0; $i < $count; $i++) {
            $rings[] = $this->readPositions($coordinateCount);
        }

        return $rings;
    }

    private function getOffset(): int
    {
        return $this->offset;
    }

    /** @throws InvalidGeometry */
    private function readByte(): int
    {
        $this->ensureAvailable(1);

        return ord($this->data[$this->offset++]);
    }

    /** @throws InvalidGeometry */
    private function readInt(): int
    {
        $this->ensureAvailable(4);

        /** @var array{1: int} $value */
        $value         = unpack($this->littleEndian ? 'V' : 'N', $this->data, $this->offset);
        $this->offset += 4;

        return $value[1];
    }

    /**
     * @return list<float>
     *
     * @throws InvalidGeometry
     */
    private function readDoubles(int $count): array
    {
        $this->ensureAvailable($count * 8);

        /** @var array<int, float> $values */
        $values        = unpack(($this->littleEndian ? 'e' : 'E') . $count, $this->data, $this->offset);
        $this->offset += $count * 8;

        return array_values($values);
    }

    /** @throws InvalidGeometry */
    private function ensureAvailable(int $bytes): void
    {
        // the last byte is the end marker
        if (strlen($this->data) - 1 - $this->offset < $bytes) {
            throw self::malformed(sprintf('expected %d more bytes at offset %d', $bytes, $this->offset));
        }
    }

    private static function getClassType(GeometryKind $kind, Dimension $dimension): int
    {
        return $kind->getWkbCode() + match ($dimension) {
            Dimension::XY => 0,
            Dimension::XYZ => 1000,
            Dimension::XYM => 2000,
            Dimension::XYZM => 3000,
        };
    }

    /**
     * @return array{GeometryKind, Dimension}
     *
     * @throws InvalidGeometry
     */
    private static function parseClassType(int $classType): array
    {
        if ($classType >= 1000000) {
            throw self::malformed('compressed geometries are not supported');
        }

        $dimension = intdiv($classType, 1000);
        $code      = $classType % 1000;

        if ($dimension > 3 || $code < 1 || $code > 7) {
            throw self::malformed(sprintf('unsupported geometry class %d', $classType));
        }

        return [
            GeometryKind::fromWkbCode($code),
            Dimension::fromFlags($dimension === 1 || $dimension === 3, $dimension === 2 || $dimension === 3),
        ];
    }

    private static function malformed(string $reason): InvalidGeometry
    {
        return InvalidGeometry::malformedSpatiaLiteBlob($reason);
    }
}
