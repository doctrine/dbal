<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial\Codec;

use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;
use JsonException;

use function array_is_list;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Reads GeoJSON geometry objects (RFC 7946).
 *
 * @internal
 */
final class GeoJsonReader
{
    private const KINDS = [
        'Point' => GeometryKind::POINT,
        'LineString' => GeometryKind::LINESTRING,
        'Polygon' => GeometryKind::POLYGON,
        'MultiPoint' => GeometryKind::MULTIPOINT,
        'MultiLineString' => GeometryKind::MULTILINESTRING,
        'MultiPolygon' => GeometryKind::MULTIPOLYGON,
        'GeometryCollection' => GeometryKind::GEOMETRYCOLLECTION,
    ];

    /** @throws InvalidGeometry */
    public static function read(string $geoJson, int $srid): Geometry
    {
        try {
            $data = json_decode($geoJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidGeometry::malformedGeoJson($e->getMessage(), $e);
        }

        return self::readObject($data, $srid, null);
    }

    /** @throws InvalidGeometry */
    private static function readObject(mixed $data, int $srid, ?Dimension $parentDimension): Geometry
    {
        if (! is_array($data) || ! isset($data['type']) || ! is_string($data['type'])) {
            throw InvalidGeometry::malformedGeoJson('expected a geometry object with a "type" member');
        }

        $kind = self::KINDS[$data['type']] ?? throw InvalidGeometry::malformedGeoJson(
            sprintf('unsupported type "%s"', $data['type']),
        );

        if ($kind === GeometryKind::GEOMETRYCOLLECTION) {
            $geometries = $data['geometries'] ?? null;

            if (! is_array($geometries) || ! array_is_list($geometries)) {
                throw InvalidGeometry::malformedGeoJson('a GeometryCollection must have a "geometries" array');
            }

            $members   = [];
            $dimension = $parentDimension;

            foreach ($geometries as $member) {
                $member      = self::readObject($member, $srid, $dimension);
                $dimension ??= $member->getDimension();
                $members[]   = $member;
            }

            return Geometry::create($kind, $dimension ?? Dimension::XY, $members, $srid);
        }

        if (! isset($data['coordinates']) || ! is_array($data['coordinates'])) {
            throw InvalidGeometry::malformedGeoJson(sprintf('a %s must have a "coordinates" array', $data['type']));
        }

        $depth = match ($kind) {
            GeometryKind::POINT => 0,
            GeometryKind::LINESTRING, GeometryKind::MULTIPOINT => 1,
            GeometryKind::POLYGON, GeometryKind::MULTILINESTRING => 2,
            default => 3,
        };

        $length      = null;
        $coordinates = self::readCoordinates($data['coordinates'], $depth, $length);

        $dimension = match ($length) {
            null => $parentDimension ?? Dimension::XY,
            2 => Dimension::XY,
            3 => Dimension::XYZ,
            default => throw InvalidGeometry::malformedGeoJson('positions must have 2 or 3 coordinates'),
        };

        if ($parentDimension !== null && $dimension !== $parentDimension) {
            throw InvalidGeometry::mixedDimensions($parentDimension, $dimension);
        }

        return Geometry::create($kind, $dimension, $coordinates, $srid);
    }

    /**
     * Reads nested lists of positions, converting the coordinates to floats.
     *
     * @param array<mixed> $coordinates
     *
     * @return list<mixed>
     *
     * @throws InvalidGeometry
     */
    private static function readCoordinates(array $coordinates, int $depth, ?int &$length): array
    {
        if (! array_is_list($coordinates)) {
            throw InvalidGeometry::malformedGeoJson('coordinates must be arrays');
        }

        if ($depth === 0) {
            if ($coordinates === []) {
                return [];
            }

            $position = [];

            foreach ($coordinates as $coordinate) {
                if (! is_int($coordinate) && ! is_float($coordinate)) {
                    throw InvalidGeometry::malformedGeoJson('coordinates must be numbers');
                }

                $position[] = (float) $coordinate;
            }

            $length ??= count($position);

            return $position;
        }

        $children = [];

        foreach ($coordinates as $child) {
            if (! is_array($child)) {
                throw InvalidGeometry::malformedGeoJson('coordinates must be nested arrays of positions');
            }

            $children[] = self::readCoordinates($child, $depth - 1, $length);
        }

        return $children;
    }
}
