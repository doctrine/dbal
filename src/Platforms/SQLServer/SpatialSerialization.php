<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\SQLServer;

use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;

use function array_slice;
use function array_values;
use function count;
use function is_nan;
use function ord;
use function sprintf;
use function strlen;
use function unpack;

/**
 * Decodes the native serialization format of the geometry and geography types of SQL Server, which is what
 * selecting a spatial column returns.
 *
 * @internal
 *
 * @link https://learn.microsoft.com/en-us/openspecs/sql_server_protocols/ms-ssclrt/
 */
final class SpatialSerialization
{
    private const HAS_Z                  = 0x01;
    private const HAS_M                  = 0x02;
    private const IS_SINGLE_POINT        = 0x08;
    private const IS_SINGLE_LINE_SEGMENT = 0x10;

    private int $offset = 0;

    /** @var list<list<float>> */
    private array $points = [];

    /** @var list<int> The offsets of the first point of the figures */
    private array $figures = [];

    /** @var list<array{int, int, int}> The parent offset, figure offset and type of the shapes */
    private array $shapes = [];

    private function __construct(private readonly string $data)
    {
    }

    /**
     * @param bool $geography Whether the value is of the geography type, which stores the latitude first.
     *
     * @throws InvalidGeometry
     */
    public static function decode(string $data, bool $geography = false): Geometry
    {
        $decoder = new self($data);

        $srid       = $decoder->readInt32();
        $version    = $decoder->readByte();
        $properties = $decoder->readByte();

        if ($version !== 1 && $version !== 2) {
            throw self::malformed(sprintf('unsupported version %d', $version));
        }

        $dimension = Dimension::fromFlags(
            ($properties & self::HAS_Z) !== 0,
            ($properties & self::HAS_M) !== 0,
        );

        if (($properties & self::IS_SINGLE_POINT) !== 0) {
            $pointCount = 1;
        } elseif (($properties & self::IS_SINGLE_LINE_SEGMENT) !== 0) {
            $pointCount = 2;
        } else {
            $pointCount = $decoder->readUInt32();
        }

        $decoder->readPoints($pointCount, $dimension, $geography);

        if (($properties & self::IS_SINGLE_POINT) !== 0) {
            $decoder->figures = [0];
            $decoder->shapes  = [[-1, 0, 1]];
        } elseif (($properties & self::IS_SINGLE_LINE_SEGMENT) !== 0) {
            $decoder->figures = [0];
            $decoder->shapes  = [[-1, 0, 2]];
        } else {
            $decoder->readFiguresAndShapes($version);
        }

        if ($decoder->offset !== strlen($data)) {
            throw self::malformed(sprintf('unexpected data at offset %d', $decoder->offset));
        }

        if ($decoder->shapes === []) {
            throw self::malformed('no shape');
        }

        return $decoder->buildShape(0, $dimension, $srid);
    }

    /** @throws InvalidGeometry */
    private function readPoints(int $count, Dimension $dimension, bool $geography): void
    {
        $this->ensureAvailable($count * 16);

        for ($i = 0; $i < $count; $i++) {
            [$first, $second] = $this->readDoubles(2);

            // the geography type stores the latitude first
            $this->points[] = $geography ? [$second, $first] : [$first, $second];
        }

        $ordinates = ($dimension->hasZ() ? 1 : 0) + ($dimension->hasM() ? 1 : 0);

        for ($ordinate = 0; $ordinate < $ordinates; $ordinate++) {
            foreach ($this->readDoubles($count) as $i => $value) {
                if (is_nan($value)) {
                    throw self::malformed('NULL Z and M values are not supported');
                }

                $this->points[$i][] = $value;
            }
        }
    }

    /** @throws InvalidGeometry */
    private function readFiguresAndShapes(int $version): void
    {
        $figureCount = $this->readUInt32();
        $this->ensureAvailable($figureCount * 5);

        for ($i = 0; $i < $figureCount; $i++) {
            $attribute = $this->readByte();

            // version 2 uses 2 and 3 for arcs and composite curves, version 1 uses 2 for exterior rings
            if ($version === 2 && $attribute > 1) {
                throw self::unsupportedCurves();
            }

            $offset = $this->readUInt32();

            if ($offset > count($this->points)) {
                throw self::malformed(sprintf('invalid point offset %d', $offset));
            }

            $this->figures[] = $offset;
        }

        $shapeCount = $this->readUInt32();
        $this->ensureAvailable($shapeCount * 9);

        for ($i = 0; $i < $shapeCount; $i++) {
            $parent = $this->readInt32();
            $figure = $this->readInt32();
            $type   = $this->readByte();

            if ($type > 7) {
                throw self::unsupportedCurves();
            }

            if ($parent >= $i || ($i > 0 && $parent < 0) || $figure >= $figureCount) {
                throw self::malformed(sprintf('invalid shape %d', $i));
            }

            $this->shapes[] = [$parent, $figure, $type];
        }

        if ($version === 2 && $this->readUInt32() > 0) {
            throw self::unsupportedCurves();
        }
    }

    /** @throws InvalidGeometry */
    private function buildShape(int $shape, Dimension $dimension, int $srid): Geometry
    {
        [, , $type] = $this->shapes[$shape];

        if ($type === 0) {
            throw self::malformed('unknown shape type');
        }

        $kind = GeometryKind::fromWkbCode($type);

        if ($kind === GeometryKind::GEOMETRYCOLLECTION || $kind->getMemberKind() !== null) {
            $members = [];

            foreach ($this->shapes as $child => [$parent]) {
                if ($parent !== $shape) {
                    continue;
                }

                $members[] = $this->buildShape($child, $dimension, $srid);
            }

            if ($kind === GeometryKind::GEOMETRYCOLLECTION) {
                return Geometry::create($kind, $dimension, $members, $srid);
            }

            $coordinates = [];

            foreach ($members as $member) {
                if ($member->getKind() !== $kind->getMemberKind()) {
                    throw self::malformed(sprintf('unexpected %s in a %s', $member->getKind()->value, $kind->value));
                }

                $coordinates[] = $member->getCoordinates();
            }

            return Geometry::create($kind, $dimension, $coordinates, $srid);
        }

        $figures = [];

        foreach ($this->getFigures($shape) as $figure) {
            $figures[] = $this->getFigurePoints($figure);
        }

        $coordinates = match ($kind) {
            GeometryKind::POINT => $figures === [] ? [] : $figures[0][0] ?? [],
            GeometryKind::LINESTRING => $figures[0] ?? [],
            default => $figures,
        };

        return Geometry::create($kind, $dimension, $coordinates, $srid);
    }

    /**
     * Returns the figures of a shape: from its figure offset to the one of the next shape that is not one of its
     * descendants.
     *
     * @return list<int>
     */
    private function getFigures(int $shape): array
    {
        $start = $this->shapes[$shape][1];

        if ($start < 0) {
            return [];
        }

        $end = count($this->figures);

        for ($next = $shape + 1, $shapeCount = count($this->shapes); $next < $shapeCount; $next++) {
            [, $figure] = $this->shapes[$next];

            if ($figure < 0 || $this->isDescendant($next, $shape)) {
                continue;
            }

            $end = $figure;

            break;
        }

        $figures = [];

        for ($figure = $start; $figure < $end; $figure++) {
            $figures[] = $figure;
        }

        return $figures;
    }

    private function isDescendant(int $shape, int $ancestor): bool
    {
        for ($parent = $this->shapes[$shape][0]; $parent >= 0; $parent = $this->shapes[$parent][0]) {
            if ($parent === $ancestor) {
                return true;
            }
        }

        return false;
    }

    /** @return list<list<float>> */
    private function getFigurePoints(int $figure): array
    {
        $start = $this->figures[$figure];
        $end   = $this->figures[$figure + 1] ?? count($this->points);

        return array_values(array_slice($this->points, $start, $end - $start));
    }

    /** @throws InvalidGeometry */
    private function readByte(): int
    {
        $this->ensureAvailable(1);

        return ord($this->data[$this->offset++]);
    }

    /** @throws InvalidGeometry */
    private function readUInt32(): int
    {
        $this->ensureAvailable(4);

        /** @var array{1: int} $value */
        $value         = unpack('V', $this->data, $this->offset);
        $this->offset += 4;

        return $value[1];
    }

    /** @throws InvalidGeometry */
    private function readInt32(): int
    {
        $value = $this->readUInt32();

        return $value > 0x7FFFFFFF ? $value - 0x100000000 : $value;
    }

    /**
     * @return list<float>
     *
     * @throws InvalidGeometry
     */
    private function readDoubles(int $count): array
    {
        if ($count === 0) {
            return [];
        }

        $this->ensureAvailable($count * 8);

        /** @var array<int, float> $values */
        $values        = unpack('e' . $count, $this->data, $this->offset);
        $this->offset += $count * 8;

        return array_values($values);
    }

    /** @throws InvalidGeometry */
    private function ensureAvailable(int $bytes): void
    {
        if (strlen($this->data) - $this->offset < $bytes) {
            throw self::malformed(sprintf('expected %d more bytes at offset %d', $bytes, $this->offset));
        }
    }

    private static function unsupportedCurves(): InvalidGeometry
    {
        return self::malformed('curves are not supported');
    }

    private static function malformed(string $reason): InvalidGeometry
    {
        return InvalidGeometry::malformedSQLServerGeometry($reason);
    }
}
