<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial\Codec;

use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;

use function array_fill;
use function array_merge;
use function assert;
use function count;
use function pack;

use const NAN;

/**
 * Writes the ISO and extended (PostGIS) well-known binary formats, in little-endian byte order.
 *
 * @internal
 */
final class WkbWriter
{
    private const FLAG_Z    = 0x80000000;
    private const FLAG_M    = 0x40000000;
    private const FLAG_SRID = 0x20000000;

    /**
     * Writes the ISO WKB of the geometry, without SRID.
     */
    public static function write(Geometry $geometry): string
    {
        return self::writeGeometry($geometry, false, null);
    }

    /**
     * Writes the EWKB of the geometry, with its SRID unless it is 0.
     */
    public static function writeExtended(Geometry $geometry): string
    {
        $srid = $geometry->getSrid();

        return self::writeGeometry($geometry, true, $srid !== 0 ? $srid : null);
    }

    private static function writeGeometry(Geometry $geometry, bool $extended, ?int $srid): string
    {
        $kind      = $geometry->getKind();
        $dimension = $geometry->getDimension();
        $header    = self::writeHeader($kind, $dimension, $extended, $srid);

        if ($kind->hasGeometryMembers()) {
            $members = $geometry->getGeometries();
            $wkb     = $header . pack('V', count($members));

            foreach ($members as $member) {
                $wkb .= self::writeGeometry($member, $extended, null);
            }

            return $wkb;
        }

        return $header . self::writeCoordinates($kind, $dimension, $geometry->getCoordinates(), $extended);
    }

    /** @param list<mixed> $coordinates */
    private static function writeCoordinates(
        GeometryKind $kind,
        Dimension $dimension,
        array $coordinates,
        bool $extended,
    ): string {
        switch ($kind) {
            case GeometryKind::POINT:
                /** @var list<float> $position */
                $position = $coordinates;

                return self::writePosition($position, $dimension);

            case GeometryKind::LINESTRING:
            case GeometryKind::CIRCULARSTRING:
                /** @var list<list<float>> $positions */
                $positions = $coordinates;

                return self::writePositions($positions);

            case GeometryKind::POLYGON:
            case GeometryKind::TRIANGLE:
                /** @var list<list<list<float>>> $rings */
                $rings = $coordinates;
                $wkb   = pack('V', count($rings));

                foreach ($rings as $ring) {
                    $wkb .= self::writePositions($ring);
                }

                return $wkb;

            default:
                $memberKind = $kind->getMemberKind();
                assert($memberKind instanceof GeometryKind);
                $wkb = pack('V', count($coordinates));

                foreach ($coordinates as $member) {
                    /** @var list<mixed> $member */
                    $wkb .= self::writeHeader($memberKind, $dimension, $extended, null)
                        . self::writeCoordinates($memberKind, $dimension, $member, $extended);
                }

                return $wkb;
        }
    }

    private static function writeHeader(GeometryKind $kind, Dimension $dimension, bool $extended, ?int $srid): string
    {
        $code = $kind->getWkbCode();

        if (! $extended) {
            return pack('CV', 1, $code + match ($dimension) {
                Dimension::XY => 0,
                Dimension::XYZ => 1000,
                Dimension::XYM => 2000,
                Dimension::XYZM => 3000,
            });
        }

        if ($dimension->hasZ()) {
            $code |= self::FLAG_Z;
        }

        if ($dimension->hasM()) {
            $code |= self::FLAG_M;
        }

        if ($srid === null) {
            return pack('CV', 1, $code);
        }

        return pack('CVV', 1, $code | self::FLAG_SRID, $srid);
    }

    /** @param list<float> $position */
    private static function writePosition(array $position, Dimension $dimension): string
    {
        if ($position === []) {
            // an empty point is encoded with NaN coordinates
            $position = array_fill(0, $dimension->getCoordinateCount(), NAN);
        }

        return pack('e*', ...$position);
    }

    /** @param list<list<float>> $positions */
    private static function writePositions(array $positions): string
    {
        if ($positions === []) {
            return pack('V', 0);
        }

        return pack('V', count($positions)) . pack('e*', ...array_merge(...$positions));
    }
}
