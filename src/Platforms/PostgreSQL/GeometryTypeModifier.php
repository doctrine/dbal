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
    private const NAMES = [
        GeometryKind::GEOMETRY->value => 'Geometry',
        GeometryKind::POINT->value => 'Point',
        GeometryKind::LINESTRING->value => 'LineString',
        GeometryKind::POLYGON->value => 'Polygon',
        GeometryKind::MULTIPOINT->value => 'MultiPoint',
        GeometryKind::MULTILINESTRING->value => 'MultiLineString',
        GeometryKind::MULTIPOLYGON->value => 'MultiPolygon',
        GeometryKind::GEOMETRYCOLLECTION->value => 'GeometryCollection',
    ];

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
            self::NAMES[($kind ?? GeometryKind::GEOMETRY)->value],
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
            $code <= 7 ? GeometryKind::fromWkbCode($code) : GeometryKind::GEOMETRY,
            Dimension::fromFlags(($typeModifier & 0x02) !== 0, ($typeModifier & 0x01) !== 0),
            $srid !== 0 ? $srid : null,
        ];
    }
}
