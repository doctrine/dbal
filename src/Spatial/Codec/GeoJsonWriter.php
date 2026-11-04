<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial\Codec;

use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;
use JsonException;

use function ini_get;
use function ini_set;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * Writes GeoJSON geometry objects (RFC 7946).
 *
 * @internal
 */
final class GeoJsonWriter
{
    /** @throws InvalidGeometry */
    public static function write(Geometry $geometry): string
    {
        if ($geometry->getDimension()->hasM()) {
            throw InvalidGeometry::unsupportedDimension($geometry->getDimension(), 'GeoJSON');
        }

        $precision = ini_get('serialize_precision');

        if ($precision !== '-1') {
            ini_set('serialize_precision', '-1');
        }

        try {
            return json_encode(self::toArray($geometry), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $e) {
            throw InvalidGeometry::malformedGeoJson($e->getMessage(), $e);
        } finally {
            if ($precision !== '-1' && $precision !== false) {
                ini_set('serialize_precision', $precision);
            }
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidGeometry
     */
    private static function toArray(Geometry $geometry): array
    {
        $type = match ($geometry->getKind()) {
            GeometryKind::POINT => 'Point',
            GeometryKind::LINESTRING => 'LineString',
            GeometryKind::POLYGON => 'Polygon',
            GeometryKind::MULTIPOINT => 'MultiPoint',
            GeometryKind::MULTILINESTRING => 'MultiLineString',
            GeometryKind::MULTIPOLYGON => 'MultiPolygon',
            GeometryKind::GEOMETRYCOLLECTION, GeometryKind::GEOMETRY => 'GeometryCollection',
            // GeoJSON has no curves and no surfaces of ISO 13249-3
            GeometryKind::CIRCULARSTRING,
            GeometryKind::COMPOUNDCURVE,
            GeometryKind::CURVEPOLYGON,
            GeometryKind::MULTICURVE,
            GeometryKind::MULTISURFACE,
            GeometryKind::POLYHEDRALSURFACE,
            GeometryKind::TIN,
            GeometryKind::TRIANGLE => throw InvalidGeometry::unsupportedKind('GeoJSON', $geometry->getKind()),
        };

        if ($geometry->getKind() === GeometryKind::GEOMETRYCOLLECTION) {
            $members = [];

            foreach ($geometry->getGeometries() as $member) {
                $members[] = self::toArray($member);
            }

            return ['type' => $type, 'geometries' => $members];
        }

        return ['type' => $type, 'coordinates' => $geometry->getCoordinates()];
    }
}
