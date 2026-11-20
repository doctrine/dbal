<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\PostgreSQL;

use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\GeometryKind;

use function sprintf;

/**
 * Renders and parses the type modifiers of the PostGIS "geometry" type, e.g. geometry(PointZ,4326).
 *
 * The functions of PostGIS are not used, so that schemas can be introspected without PostGIS.
 *
 * @internal
 */
final class GeometryTypeModifier
{
    /** @codeCoverageIgnore */
    private function __construct()
    {
    }

    /**
     * Renders the declaration of a column of the given PostGIS type ("geometry" or "geography").
     *
     * A type modifier is only rendered if the kind, the dimension or a non-zero SRID is specified, since a bare
     * type accepts every kind, dimension and SRID while a type modifier always constrains the dimension.
     */
    public static function render(string $type, ?GeometryKind $kind, ?Dimension $dimension, ?int $srid): string
    {
        if ($srid === 0) {
            $srid = null;
        }

        if ($kind === null && $dimension === null && $srid === null) {
            return $type;
        }

        return sprintf(
            '%s(%s%s%s)',
            $type,
            self::getKindName($kind ?? GeometryKind::GEOMETRY),
            match ($dimension) {
                null, Dimension::XY => '',
                Dimension::XYZ => 'Z',
                Dimension::XYM => 'M',
                Dimension::XYZM => 'ZM',
            },
            $srid !== null ? ',' . $srid : '',
        );
    }

    /**
     * Parses the value of pg_attribute.atttypmod of a column of a PostGIS type.
     *
     * @return array{?GeometryKind, ?Dimension, ?int}
     */
    public static function parse(int $typeModifier): array
    {
        if ($typeModifier < 0) {
            return [null, null, null];
        }

        $code = ($typeModifier & 0xFC) >> 2;
        $srid = ($typeModifier & 0x0FFFFF00) >> 8;

        return [
            self::getKind($code),
            Dimension::fromFlags(($typeModifier & 0x02) !== 0, ($typeModifier & 0x01) !== 0),
            $srid !== 0 ? $srid : null,
        ];
    }

    /**
     * Returns the name of the kind in the type modifier, as PostGIS prints it.
     */
    private static function getKindName(GeometryKind $kind): string
    {
        return match ($kind) {
            GeometryKind::GEOMETRY => 'Geometry',
            GeometryKind::POINT => 'Point',
            GeometryKind::LINESTRING => 'LineString',
            GeometryKind::POLYGON => 'Polygon',
            GeometryKind::MULTIPOINT => 'MultiPoint',
            GeometryKind::MULTILINESTRING => 'MultiLineString',
            GeometryKind::MULTIPOLYGON => 'MultiPolygon',
            GeometryKind::GEOMETRYCOLLECTION => 'GeometryCollection',
            GeometryKind::CIRCULARSTRING => 'CircularString',
            GeometryKind::COMPOUNDCURVE => 'CompoundCurve',
            GeometryKind::CURVEPOLYGON => 'CurvePolygon',
            GeometryKind::MULTICURVE => 'MultiCurve',
            GeometryKind::MULTISURFACE => 'MultiSurface',
            GeometryKind::POLYHEDRALSURFACE => 'PolyhedralSurface',
            GeometryKind::TIN => 'Tin',
            GeometryKind::TRIANGLE => 'Triangle',
        };
    }

    /**
     * Returns the kind of the given code of a type modifier, numbered by PostGIS like the WKB type codes up to 12.
     */
    private static function getKind(int $code): GeometryKind
    {
        return match ($code) {
            13 => GeometryKind::POLYHEDRALSURFACE,
            14 => GeometryKind::TRIANGLE,
            15 => GeometryKind::TIN,
            default => $code <= 12 ? GeometryKind::fromWkbCode($code) : GeometryKind::GEOMETRY,
        };
    }
}
