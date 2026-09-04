<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Tests\SpatialReferenceSystems;
use Doctrine\DBAL\Types\GeoJSON;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

class GeoJSONTest extends TestCase
{
    #[DoesNotPerformAssertions]
    #[DataProvider('validGeoJSONStringProvider')]
    public function testFromStringWithValidGeoJSON(string $json): void
    {
        GeoJSON::fromString($json);
    }

    /** @return iterable<string, array{string}> */
    public static function validGeoJSONStringProvider(): iterable
    {
        yield 'Point' => ['{"type":"Point","coordinates":[100.0,0.0]}'];
        yield 'Point with whitespace' => ['  {"type": "Point", "coordinates": [100.0, 0.0]}  '];
        yield 'LineString' => ['{"type":"LineString","coordinates":[[100.0,0.0],[101.0,1.0]]}'];
        yield 'Polygon' => ['{"type":"Polygon",' .
        '"coordinates":[[[100.0,0.0],[101.0,0.0],[101.0,1.0],[100.0,1.0],[100.0,0.0]]]}',
        ];

        yield 'MultiPoint' => ['{"type":"MultiPoint",' .
        '"coordinates":[[100.0,0.0],[101.0,1.0]]}',
        ];

        yield 'MultiLineString' => ['{"type":"MultiLineString",' .
        '"coordinates":[[[100.0,0.0],[101.0,1.0]],[[102.0,2.0],[103.0,3.0]]]}',
        ];

        yield 'MultiPolygon' => ['{"type":"MultiPolygon",' .
        '"coordinates":[[[[100.0,0.0],[101.0,0.0],[101.0,1.0],[100.0,1.0],[100.0,0.0]]]]}',
        ];

        yield 'GeometryCollection' => ['{"type":"GeometryCollection",' .
        '"geometries":[{"type":"Point","coordinates":[100.0,0.0]}]}',
        ];
    }

    #[DataProvider('invalidGeoJSONStringProvider')]
    public function testFromStringWithInvalidGeoJSON(string $invalidJSON): void
    {
        $this->expectException(InvalidArgumentException::class);
        GeoJSON::fromString($invalidJSON);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidGeoJSONStringProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
        yield 'not JSON' => ['not json'];
        yield 'invalid JSON' => ['{invalid json}'];
        yield 'missing type' => ['{"coordinates":[100.0,0.0]}'];
        yield 'invalid type' => ['{"type":"InvalidType","coordinates":[100.0,0.0]}'];
        yield 'Point without coordinates' => ['{"type":"Point"}'];
        yield 'Point with invalid coordinates' => ['{"type":"Point","coordinates":"invalid"}'];
        yield 'Feature not supported' => ['{"type":"Feature",' .
        '"geometry":{"type":"Point","coordinates":[0,0]},"properties":{}}',
        ];

        yield 'FeatureCollection not supported' => ['{"type":"FeatureCollection","features":[]}'];
    }

    public function testGetSridFromCrsProperty(): void
    {
        $json = '{"type":"Point","coordinates":[100.0,0.0],'
            . '"crs":{"type":"name","properties":{"name":"EPSG:' . SpatialReferenceSystems::SRID_WGS84 . '"}}}';

        $geoJSON = GeoJSON::fromString($json);

        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $geoJSON->getSrid());
    }

    public function testGetSridFromCrsPropertyWithUrn(): void
    {
        $json = '{"type":"Point","coordinates":[100.0,0.0],"crs":{' .
            '"type":"name","properties":{' .
                '"name":"urn:ogc:def:crs:EPSG::' . SpatialReferenceSystems::SRID_WGS84 . '"}}}';

        $geoJSON = GeoJSON::fromString($json);

        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $geoJSON->getSrid());
    }

    public function testGetSridReturnsNullWhenNoCrs(): void
    {
        $geoJSON = GeoJSON::fromString('{"type":"Point","coordinates":[100.0,0.0]}');

        self::assertNull($geoJSON->getSrid());
    }

    public function testToString(): void
    {
        $json    = '{"type":"Point","coordinates":[100.0,0.0]}';
        $geoJSON = GeoJSON::fromString($json);

        self::assertJsonStringEqualsJsonString($json, $geoJSON->toString());
    }

    public function testStringable(): void
    {
        $json    = '{"type":"Point","coordinates":[100.0,0.0]}';
        $geoJSON = GeoJSON::fromString($json);

        self::assertJsonStringEqualsJsonString($json, (string) $geoJSON);
    }

    public function testRoundTripPreservesData(): void
    {
        $json = '{"type":"Point","coordinates":[100.5,50.25],'
            . '"crs":{"type":"name","properties":{"name":"EPSG:' . SpatialReferenceSystems::SRID_WGS84 . '"}}}';

        $geoJSON = GeoJSON::fromString($json);
        $output  = $geoJSON->toString();

        self::assertJsonStringEqualsJsonString($json, $output);
        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $geoJSON->getSrid());
    }

    #[DoesNotPerformAssertions]
    public function testGeometryCollectionValidation(): void
    {
        $json = '{"type":"GeometryCollection","geometries":[' .
        '{"type":"Point","coordinates":[100.0,0.0]},' .
        '{"type":"LineString","coordinates":[[101.0,0.0],[102.0,1.0]]}]}';

        GeoJSON::fromString($json);
    }
}
