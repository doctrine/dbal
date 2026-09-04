<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Tests\SpatialReferenceSystems;
use Doctrine\DBAL\Types\Geometry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GeometryTest extends TestCase
{
    public function testFromGeoJSONCreatesGeometryWithoutSrid(): void
    {
        $geoJson  = '{"type":"Point","coordinates":[1,2]}';
        $geometry = Geometry::fromGeoJSON($geoJson);

        self::assertSame($geoJson, $geometry->getGeoJSON()->toString());
        self::assertNull($geometry->getSrid());
    }

    public function testFromGeoJSONCreatesGeometryWithSrid(): void
    {
        $geoJson  = '{"type":"Point","coordinates":[1,2],'
            . '"crs":{"type":"name","properties":{"name":"EPSG:' . SpatialReferenceSystems::SRID_WGS84 . '"}}}';
        $geometry = Geometry::fromGeoJSON($geoJson);

        self::assertSame($geoJson, $geometry->getGeoJSON()->toString());
        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $geometry->getSrid());
    }

    public function testToGeoJSON(): void
    {
        $geoJson  = '{"type":"Point","coordinates":[1,2],'
            . '"crs":{"type":"name","properties":{"name":"EPSG:' . SpatialReferenceSystems::SRID_WGS84 . '"}}}';
        $geometry = Geometry::fromGeoJSON($geoJson);

        self::assertSame($geoJson, $geometry->toGeoJSON());
    }

    public function testToStringReturnsGeoJSON(): void
    {
        $geoJson  = '{"type":"Point","coordinates":[1,2],'
            . '"crs":{"type":"name","properties":{"name":"EPSG:' . SpatialReferenceSystems::SRID_WGS84 . '"}}}';
        $geometry = Geometry::fromGeoJSON($geoJson);

        self::assertSame($geoJson, (string) $geometry);
    }

    public function testFromGeoJSONValidatesFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON string');

        Geometry::fromGeoJSON('INVALID JSON');
    }

    public function testSupportsVariousGeometryTypes(): void
    {
        $geometries = [
            '{"type":"Point","coordinates":[1,2]}',
            '{"type":"LineString","coordinates":[[0,0],[1,1],[2,2]]}',
            '{"type":"Polygon","coordinates":[[[0,0],[4,0],[4,4],[0,4],[0,0]]]}',
            '{"type":"MultiPoint","coordinates":[[0,0],[1,1]]}',
            '{"type":"MultiLineString","coordinates":[[[0,0],[1,1]],[[2,2],[3,3]]]}',
            '{"type":"MultiPolygon","coordinates":[[[[0,0],[4,0],[4,4],[0,4],[0,0]]]]}',
            '{"type":"GeometryCollection","geometries":[{"type":"Point","coordinates":[1,1]},'
                . '{"type":"LineString","coordinates":[[0,0],[1,1]]}]}',
        ];

        foreach ($geometries as $geoJson) {
            $geometry = Geometry::fromGeoJSON($geoJson);
            self::assertSame($geoJson, $geometry->getGeoJSON()->toString());
        }
    }

    public function testGetGeoJSONReturnsGeoJSONObject(): void
    {
        $geoJson  = '{"type":"Point","coordinates":[1,2]}';
        $geometry = Geometry::fromGeoJSON($geoJson);
        $result   = $geometry->getGeoJSON();

        self::assertSame($geoJson, $result->toString());
    }
}
