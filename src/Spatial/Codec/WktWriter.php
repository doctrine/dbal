<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial\Codec;

use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;

use function implode;
use function ini_get;
use function ini_set;
use function str_ends_with;
use function substr;
use function var_export;

/**
 * Writes the ISO and extended (PostGIS) well-known text formats.
 *
 * Coordinates are written with the shortest representation that reads back as the same float.
 *
 * @internal
 */
final class WktWriter
{
    public static function write(Geometry $geometry, bool $extended): string
    {
        $precision = ini_get('serialize_precision');

        if ($precision !== '-1') {
            ini_set('serialize_precision', '-1');
        }

        try {
            $wkt = self::writeGeometry($geometry, $extended);
        } finally {
            if ($precision !== '-1' && $precision !== false) {
                ini_set('serialize_precision', $precision);
            }
        }

        if ($extended && $geometry->getSrid() !== 0) {
            return 'SRID=' . $geometry->getSrid() . ';' . $wkt;
        }

        return $wkt;
    }

    private static function writeGeometry(Geometry $geometry, bool $extended): string
    {
        $kind      = $geometry->getKind();
        $dimension = $geometry->getDimension();

        if ($extended) {
            // EWKT only tags the M dimension, the other ones are implied by the number of coordinates
            $tag = $kind->value . ($dimension === Dimension::XYM ? 'M' : '');
        } else {
            $tag = $kind->value . match ($dimension) {
                Dimension::XY => '',
                Dimension::XYZ => ' Z ',
                Dimension::XYM => ' M ',
                Dimension::XYZM => ' ZM ',
            };
        }

        if ($geometry->isEmpty()) {
            return self::trimTag($tag) . ' EMPTY';
        }

        if ($kind === GeometryKind::GEOMETRYCOLLECTION) {
            $members = [];

            foreach ($geometry->getGeometries() as $member) {
                $members[] = self::writeGeometry($member, $extended);
            }

            return $tag . '(' . implode(',', $members) . ')';
        }

        return $tag . self::writeCoordinates($kind, $geometry->getCoordinates());
    }

    /** @param list<mixed> $coordinates */
    private static function writeCoordinates(GeometryKind $kind, array $coordinates): string
    {
        switch ($kind) {
            case GeometryKind::POINT:
                /** @var list<float> $position */
                $position = $coordinates;

                return '(' . self::writePosition($position) . ')';

            case GeometryKind::LINESTRING:
                /** @var list<list<float>> $positions */
                $positions = $coordinates;

                return self::writePositions($positions);

            case GeometryKind::POLYGON:
                /** @var list<list<list<float>>> $rings */
                $rings = $coordinates;

                return self::writeRings($rings);

            case GeometryKind::MULTIPOINT:
                $points = [];

                foreach ($coordinates as $position) {
                    /** @var list<float> $position */
                    $points[] = $position === [] ? 'EMPTY' : '(' . self::writePosition($position) . ')';
                }

                return '(' . implode(',', $points) . ')';

            case GeometryKind::MULTILINESTRING:
                $lineStrings = [];

                foreach ($coordinates as $positions) {
                    /** @var list<list<float>> $positions */
                    $lineStrings[] = $positions === [] ? 'EMPTY' : self::writePositions($positions);
                }

                return '(' . implode(',', $lineStrings) . ')';

            default:
                $polygons = [];

                foreach ($coordinates as $rings) {
                    /** @var list<list<list<float>>> $rings */
                    $polygons[] = $rings === [] ? 'EMPTY' : self::writeRings($rings);
                }

                return '(' . implode(',', $polygons) . ')';
        }
    }

    /** @param list<list<list<float>>> $rings */
    private static function writeRings(array $rings): string
    {
        $parts = [];

        foreach ($rings as $ring) {
            $parts[] = self::writePositions($ring);
        }

        return '(' . implode(',', $parts) . ')';
    }

    /** @param list<list<float>> $positions */
    private static function writePositions(array $positions): string
    {
        $parts = [];

        foreach ($positions as $position) {
            $parts[] = self::writePosition($position);
        }

        return '(' . implode(',', $parts) . ')';
    }

    /** @param list<float> $position */
    private static function writePosition(array $position): string
    {
        $parts = [];

        foreach ($position as $coordinate) {
            // var_export() is locale-independent and honors serialize_precision = -1
            $number  = var_export($coordinate, true);
            $parts[] = str_ends_with($number, '.0') ? substr($number, 0, -2) : $number;
        }

        return implode(' ', $parts);
    }

    private static function trimTag(string $tag): string
    {
        return $tag[-1] === ' ' ? substr($tag, 0, -1) : $tag;
    }
}
