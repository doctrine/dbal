<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Spatial;

use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function hex2bin;
use function serialize;
use function setlocale;
use function strtoupper;
use function unserialize;

use const INF;
use const LC_NUMERIC;

class GeometryTest extends TestCase
{
    /** Reference encodings produced by PostGIS 3.6 (SELECT 'ewkt'::geometry) */
    #[DataProvider('postgisProvider')]
    public function testPostGISEncodings(string $ewkt, string $ewkbHex): void
    {
        $fromWkt = Geometry::fromWkt($ewkt);
        $fromWkb = Geometry::fromWkb((string) hex2bin($ewkbHex));

        self::assertSame($ewkbHex, strtoupper(bin2hex($fromWkt->asEwkb())));
        self::assertTrue($fromWkb->equals($fromWkt));
        self::assertSame($ewkt, $fromWkb->asEwkt());
    }

    /** @return iterable<string, array{string, string}> */
    public static function postgisProvider(): iterable
    {
        yield 'point' => ['SRID=4326;POINT(1 2)', '0101000020E6100000000000000000F03F0000000000000040'];
        yield 'point without SRID' => ['POINT(1 2)', '0101000000000000000000F03F0000000000000040'];
        yield 'point Z' => [
            'SRID=4326;POINT(1 2 3)',
            '01010000A0E6100000000000000000F03F00000000000000400000000000000840',
        ];

        yield 'point M' => [
            'SRID=4326;POINTM(1 2 4)',
            '0101000060E6100000000000000000F03F00000000000000400000000000001040',
        ];

        yield 'point ZM' => [
            'SRID=4326;POINT(1 2 3 4)',
            '01010000E0E6100000000000000000F03F000000000000004000000000000008400000000000001040',
        ];

        yield 'empty point' => ['SRID=4326;POINT EMPTY', '0101000020E6100000000000000000F87F000000000000F87F'];
        yield 'empty line string' => ['SRID=4326;LINESTRING EMPTY', '0102000020E610000000000000'];
        yield 'empty polygon' => ['SRID=4326;POLYGON EMPTY', '0103000020E610000000000000'];
        yield 'empty multi-point' => ['SRID=4326;MULTIPOINT EMPTY', '0104000020E610000000000000'];
        yield 'empty collection' => ['SRID=4326;GEOMETRYCOLLECTION EMPTY', '0107000020E610000000000000'];
        yield 'multi-point' => [
            'SRID=4326;MULTIPOINT((0 0),(1 1))',
            '0104000020E6100000020000000101000000000000000000000000000000000000000101000000'
                . '000000000000F03F000000000000F03F',
        ];

        yield 'circular string' => [
            'SRID=4326;CIRCULARSTRING(0 0,1 1,2 0)',
            '0108000020E61000000300000000000000000000000000000000000000000000000000F03F000000000000F03F000000'
                . '00000000400000000000000000',
        ];

        yield 'circular string M' => [
            'CIRCULARSTRINGM(0 0 1,1 1 2,2 0 3)',
            '01080000400300000000000000000000000000000000000000000000000000F03F000000000000F03F000000000000F0'
                . '3F0000000000000040000000000000004000000000000000000000000000000840',
        ];

        yield 'compound curve' => [
            'SRID=4326;COMPOUNDCURVE((0 0,1 0),CIRCULARSTRING(1 0,2 1,3 0))',
            '0109000020E61000000200000001020000000200000000000000000000000000000000000000000000000000F03F0000'
                . '000000000000010800000003000000000000000000F03F00000000000000000000000000000040000000000000F03F00'
                . '000000000008400000000000000000',
        ];

        yield 'curve polygon' => [
            'SRID=4326;CURVEPOLYGON(CIRCULARSTRING(0 0,4 0,4 4,0 4,0 0),(1 1,3 3,3 1,1 1))',
            '010A000020E6100000020000000108000000050000000000000000000000000000000000000000000000000010400000'
                . '000000000000000000000000104000000000000010400000000000000000000000000000104000000000000000000000'
                . '000000000000010200000004000000000000000000F03F000000000000F03F0000000000000840000000000000084000'
                . '00000000000840000000000000F03F000000000000F03F000000000000F03F',
        ];

        yield 'multi-curve' => [
            'SRID=4326;MULTICURVE((0 0,5 5),CIRCULARSTRING(4 0,4 4,8 4))',
            '010B000020E6100000020000000102000000020000000000000000000000000000000000000000000000000014400000'
                . '000000001440010800000003000000000000000000104000000000000000000000000000001040000000000000104000'
                . '000000000020400000000000001040',
        ];

        yield 'multi-surface' => [
            'SRID=4326;MULTISURFACE(CURVEPOLYGON(CIRCULARSTRING(0 0,4 0,4 4,0 4,0 0)),((10 10,14 12,11 10,10 10)))',
            '010C000020E610000002000000010A000000010000000108000000050000000000000000000000000000000000000000'
                . '000000000010400000000000000000000000000000104000000000000010400000000000000000000000000000104000'
                . '000000000000000000000000000000010300000001000000040000000000000000002440000000000000244000000000'
                . '00002C4000000000000028400000000000002640000000000000244000000000000024400000000000002440',
        ];

        yield 'polyhedral surface' => [
            'POLYHEDRALSURFACE(((0 0 0,0 1 0,1 1 0,0 0 0)),((0 0 0,0 1 1,0 1 0,0 0 0)))',
            '010F00008002000000010300008001000000040000000000000000000000000000000000000000000000000000000000'
                . '000000000000000000000000F03F0000000000000000000000000000F03F000000000000F03F00000000000000000000'
                . '000000000000000000000000000000000000000000000103000080010000000400000000000000000000000000000000'
                . '00000000000000000000000000000000000000000000000000F03F000000000000F03F00000000000000000000000000'
                . '00F03F0000000000000000000000000000000000000000000000000000000000000000',
        ];

        yield 'TIN' => [
            'TIN(((0 0,0 1,1 1,0 0)),((0 0,1 1,1 0,0 0)))',
            '011000000002000000011100000001000000040000000000000000000000000000000000000000000000000000000000'
                . '00000000F03F000000000000F03F000000000000F03F0000000000000000000000000000000001110000000100000004'
                . '00000000000000000000000000000000000000000000000000F03F000000000000F03F000000000000F03F0000000000'
                . '00000000000000000000000000000000000000',
        ];

        yield 'triangle' => [
            'TRIANGLE((0 0,0 1,1 1,0 0))',
            '01110000000100000004000000000000000000000000000000000000000000000000000000000000000000F03F000000'
                . '000000F03F000000000000F03F00000000000000000000000000000000',
        ];
    }

    /** @param list<mixed> $coordinates */
    #[DataProvider('wktProvider')]
    public function testWkt(
        string $wkt,
        GeometryKind $kind,
        Dimension $dimension,
        array $coordinates,
        string $expectedWkt,
    ): void {
        $geometry = Geometry::fromWkt($wkt);

        self::assertSame($kind, $geometry->getKind());
        self::assertSame($dimension, $geometry->getDimension());
        self::assertSame($coordinates, $geometry->getCoordinates());
        self::assertSame($expectedWkt, $geometry->asWkt());
        self::assertTrue(Geometry::fromWkt($geometry->asWkt())->equals($geometry));
        self::assertTrue(Geometry::fromWkb($geometry->asWkb())->equals($geometry));
        self::assertTrue(Geometry::fromWkb($geometry->asEwkb())->equals($geometry));
    }

    /** @return iterable<string, array{string, GeometryKind, Dimension, list<mixed>, string}> */
    public static function wktProvider(): iterable
    {
        yield 'point' => ['POINT(1 2)', GeometryKind::POINT, Dimension::XY, [1.0, 2.0], 'POINT(1 2)'];
        yield 'point with spaces and case' => [
            ' point ( -1.5e2  +2.25 ) ',
            GeometryKind::POINT,
            Dimension::XY,
            [-150.0, 2.25],
            'POINT(-150 2.25)',
        ];

        yield 'point Z tag' => [
            'POINT Z (1 2 3)',
            GeometryKind::POINT,
            Dimension::XYZ,
            [1.0, 2.0, 3.0],
            'POINT Z (1 2 3)',
        ];

        yield 'point M glued tag' => [
            'POINTM(1 2 3)',
            GeometryKind::POINT,
            Dimension::XYM,
            [1.0, 2.0, 3.0],
            'POINT M (1 2 3)',
        ];

        yield 'point ZM inferred' => [
            'POINT(1 2 3 4)',
            GeometryKind::POINT,
            Dimension::XYZM,
            [1.0, 2.0, 3.0, 4.0],
            'POINT ZM (1 2 3 4)',
        ];

        yield 'line string' => [
            'LINESTRING(0 0,1 1,2 0.5)',
            GeometryKind::LINESTRING,
            Dimension::XY,
            [[0.0, 0.0], [1.0, 1.0], [2.0, 0.5]],
            'LINESTRING(0 0,1 1,2 0.5)',
        ];

        yield 'polygon with hole' => [
            'POLYGON((0 0,10 0,10 10,0 10,0 0),(1 1,2 1,2 2,1 1))',
            GeometryKind::POLYGON,
            Dimension::XY,
            [
                [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]],
                [[1.0, 1.0], [2.0, 1.0], [2.0, 2.0], [1.0, 1.0]],
            ],
            'POLYGON((0 0,10 0,10 10,0 10,0 0),(1 1,2 1,2 2,1 1))',
        ];

        yield 'multi-point without parentheses' => [
            'MULTIPOINT(0 0, 1 1)',
            GeometryKind::MULTIPOINT,
            Dimension::XY,
            [[0.0, 0.0], [1.0, 1.0]],
            'MULTIPOINT((0 0),(1 1))',
        ];

        yield 'multi-point with an empty point' => [
            'MULTIPOINT(EMPTY,(1 1))',
            GeometryKind::MULTIPOINT,
            Dimension::XY,
            [[], [1.0, 1.0]],
            'MULTIPOINT(EMPTY,(1 1))',
        ];

        yield 'multi-line string' => [
            'MULTILINESTRING Z ((0 0 1,1 1 1),(2 2 2,3 3 3))',
            GeometryKind::MULTILINESTRING,
            Dimension::XYZ,
            [[[0.0, 0.0, 1.0], [1.0, 1.0, 1.0]], [[2.0, 2.0, 2.0], [3.0, 3.0, 3.0]]],
            'MULTILINESTRING Z ((0 0 1,1 1 1),(2 2 2,3 3 3))',
        ];

        yield 'multi-polygon' => [
            'MULTIPOLYGON(((0 0,1 0,1 1,0 0)),((5 5,6 5,6 6,5 5)))',
            GeometryKind::MULTIPOLYGON,
            Dimension::XY,
            [
                [[[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 0.0]]],
                [[[5.0, 5.0], [6.0, 5.0], [6.0, 6.0], [5.0, 5.0]]],
            ],
            'MULTIPOLYGON(((0 0,1 0,1 1,0 0)),((5 5,6 5,6 6,5 5)))',
        ];
    }

    public function testGeometryCollection(): void
    {
        $geometry = Geometry::fromWkt(
            'SRID=3857;GEOMETRYCOLLECTION(POINT(1 2),GEOMETRYCOLLECTION(LINESTRING(0 0,1 1)))',
        );

        self::assertSame(GeometryKind::GEOMETRYCOLLECTION, $geometry->getKind());
        self::assertSame(3857, $geometry->getSrid());

        $members = $geometry->getGeometries();

        self::assertCount(2, $members);
        self::assertSame(GeometryKind::POINT, $members[0]->getKind());
        self::assertSame(3857, $members[0]->getSrid());
        self::assertSame(GeometryKind::GEOMETRYCOLLECTION, $members[1]->getKind());
        self::assertSame(
            'GEOMETRYCOLLECTION(POINT(1 2),GEOMETRYCOLLECTION(LINESTRING(0 0,1 1)))',
            $geometry->asWkt(),
        );
        self::assertTrue(Geometry::fromWkb($geometry->asEwkb())->equals($geometry));
        self::assertTrue(Geometry::fromWkb($geometry->asWkb(), 3857)->equals($geometry));

        $this->expectException(InvalidGeometry::class);
        $geometry->getCoordinates();
    }

    public function testMembersOfMultiGeometries(): void
    {
        $members = Geometry::fromWkt('SRID=4326;MULTIPOINT((1 2),(3 4))')->getGeometries();

        self::assertCount(2, $members);
        self::assertSame('SRID=4326;POINT(3 4)', $members[1]->asEwkt());

        $this->expectException(InvalidGeometry::class);
        Geometry::point(1, 2)->getGeometries();
    }

    public function testPoint(): void
    {
        $point = Geometry::point(2.3522219, 48.856614, 4326);

        self::assertSame(GeometryKind::POINT, $point->getKind());
        self::assertSame(Dimension::XY, $point->getDimension());
        self::assertSame([2.3522219, 48.856614], $point->getCoordinates());
        self::assertSame(4326, $point->getSrid());
        self::assertFalse($point->isEmpty());
        self::assertSame('SRID=4326;POINT(2.3522219 48.856614)', $point->asEwkt());
        self::assertSame('{"type":"Point","coordinates":[2.3522219,48.856614]}', $point->asGeoJson());
    }

    public function testCoordinatesAreWrittenLosslessly(): void
    {
        $values = [0.1 + 0.2, 1 / 3, -0.0, 1e-300, 5e-324, 1.7976931348623157e308, 123456789.123456789];

        foreach ($values as $value) {
            $point = Geometry::point($value, -$value);

            self::assertTrue(Geometry::fromWkt($point->asWkt())->equals($point), $point->asWkt());
            self::assertTrue(Geometry::fromGeoJson($point->asGeoJson(), 0)->equals($point), $point->asGeoJson());
            self::assertTrue(Geometry::fromWkb($point->asWkb())->equals($point));
        }
    }

    public function testWktIsLocaleIndependent(): void
    {
        $locale = setlocale(LC_NUMERIC, '0');
        setlocale(LC_NUMERIC, 'fr_FR.UTF-8', 'fr_FR', 'de_DE.UTF-8', 'de_DE');

        try {
            self::assertSame('POINT(1.5 -2.25)', Geometry::point(1.5, -2.25)->asWkt());
        } finally {
            setlocale(LC_NUMERIC, (string) $locale);
        }
    }

    public function testBigEndianWkb(): void
    {
        $wkb = (string) hex2bin('00000000013FF00000000000004000000000000000');

        self::assertSame('POINT(1 2)', Geometry::fromWkb($wkb)->asWkt());
    }

    public function testIsoWkbWithDimensions(): void
    {
        $geometry = Geometry::fromWkt('LINESTRING ZM (0 0 1 2,1 1 3 4)');
        $wkb      = $geometry->asWkb();

        self::assertSame('01ba0b0000', bin2hex($wkb[0] . $wkb[1] . $wkb[2] . $wkb[3] . $wkb[4]));
        self::assertTrue(Geometry::fromWkb($wkb)->equals($geometry));
    }

    public function testGeoJson(): void
    {
        $geoJson = '{"type":"GeometryCollection","geometries":['
            . '{"type":"Point","coordinates":[1,2,3]},'
            . '{"type":"LineString","coordinates":[[0,0,0],[1.5,1,2]]}]}';

        $geometry = Geometry::fromGeoJson($geoJson);

        self::assertSame(4326, $geometry->getSrid());
        self::assertSame(Dimension::XYZ, $geometry->getDimension());
        self::assertSame('GEOMETRYCOLLECTION Z (POINT Z (1 2 3),LINESTRING Z (0 0 0,1.5 1 2))', $geometry->asWkt());
        self::assertSame(
            '{"type":"GeometryCollection","geometries":[{"type":"Point","coordinates":[1.0,2.0,3.0]},'
                . '{"type":"LineString","coordinates":[[0.0,0.0,0.0],[1.5,1.0,2.0]]}]}',
            $geometry->asGeoJson(),
        );
    }

    public function testGeoJsonCannotRepresentMeasures(): void
    {
        $this->expectException(InvalidGeometry::class);

        Geometry::fromWkt('POINT M (1 2 3)')->asGeoJson();
    }

    public function testWithSrid(): void
    {
        $point = Geometry::point(1, 2);
        $other = $point->withSrid(4326);

        self::assertSame(0, $point->getSrid());
        self::assertSame(4326, $other->getSrid());
        self::assertSame($point, $point->withSrid(0));
        self::assertFalse($point->equals($other));
        self::assertSame(
            4326,
            Geometry::fromWkt('GEOMETRYCOLLECTION(POINT(1 2))')->withSrid(4326)->getGeometries()[0]->getSrid(),
        );
    }

    public function testEmbeddedSridIsUsed(): void
    {
        self::assertSame(4326, Geometry::fromWkt('SRID=4326;POINT(1 2)', 4326)->getSrid());
        self::assertSame(2154, Geometry::fromWkt('POINT(1 2)', 2154)->getSrid());
        self::assertSame(0, Geometry::fromWkt('SRID=0;POINT(1 2)')->getSrid());
    }

    public function testConflictingSrid(): void
    {
        $this->expectException(InvalidGeometry::class);
        $this->expectExceptionMessage('The geometry carries the SRID 4326, which conflicts with the SRID 3857.');

        Geometry::fromWkb(Geometry::point(1, 2, 4326)->asEwkb(), 3857);
    }

    public function testSerialization(): void
    {
        $geometry = Geometry::fromWkt('SRID=4326;POLYGON((0 0,1 0,1 1,0 0))');

        /** @var Geometry $unserialized */
        $unserialized = unserialize(serialize($geometry));

        self::assertTrue($unserialized->equals($geometry));
        self::assertSame($geometry->asEwkt(), $unserialized->asEwkt());
    }

    #[DataProvider('invalidWktProvider')]
    public function testInvalidWkt(string $wkt, string $message): void
    {
        $this->expectException(InvalidGeometry::class);
        $this->expectExceptionMessage($message);

        Geometry::fromWkt($wkt);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidWktProvider(): iterable
    {
        yield 'unknown type' => ['CIRCLE(1 2)', 'unknown geometry type "CIRCLE"'];
        yield 'even circular string' => [
            'CIRCULARSTRING(0 0,1 1,2 0,3 1)',
            'a circular string must have an odd number of points',
        ];

        yield 'polygon in a compound curve' => [
            'COMPOUNDCURVE(POLYGON((0 0,1 0,1 1,0 0)))',
            'it cannot have POLYGON members',
        ];

        yield 'triangle with 5 points' => [
            'TRIANGLE((0 0,0 1,1 1,1 0,0 0))',
            'a triangle must have one ring of 4 points',
        ];

        yield 'garbage' => ['POINT(1 2) foo', 'unexpected token'];
        yield 'unexpected character' => ['POINT(1 2);', 'unexpected character'];
        yield 'missing parenthesis' => ['POINT(1 2', 'expected ")"'];
        yield 'one coordinate' => ['POINT(1)', 'a position must have 2 to 4 coordinates, got 1'];
        yield 'dimension mismatch' => ['POINT Z (1 2)', 'expected 3 coordinates per position (XYZ), got 2'];
        yield 'mixed dimensions' => ['LINESTRING(0 0,1 1 1)', 'a position must be a list of 2 coordinates (XY)'];
        yield 'mixed member dimensions' => [
            'GEOMETRYCOLLECTION(POINT(1 2),POINT(1 2 3))',
            'Mixed coordinate dimensions',
        ];

        yield 'single-point line string' => [
            'LINESTRING(0 0)',
            'a line string must have either 0 or at least 2 points',
        ];

        yield 'open ring' => ['POLYGON((0 0,1 0,1 1,0 1))', 'a ring must be closed'];
        yield 'short ring' => ['POLYGON((0 0,1 0,0 0))', 'a ring must have at least 4 points'];
        yield 'invalid SRID' => ['SRID=1000000;POINT(1 2)', 'Invalid SRID 1000000'];
    }

    #[DataProvider('invalidWkbProvider')]
    public function testInvalidWkb(string $hex, string $message): void
    {
        $this->expectException(InvalidGeometry::class);
        $this->expectExceptionMessage($message);

        Geometry::fromWkb((string) hex2bin($hex));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidWkbProvider(): iterable
    {
        yield 'empty' => ['', 'expected 1 more bytes at offset 0, 0 available'];
        yield 'invalid byte order' => ['0201000000', 'invalid byte order 2 at offset 0'];
        yield 'truncated' => ['0101000000000000000000F03F', 'expected 16 more bytes at offset 5, 8 available'];
        yield 'trailing data' => ['0101000000000000000000F03F000000000000004000', 'unexpected data at offset 21'];
        yield 'abstract curve' => ['010D00000000000000', 'Unsupported WKB geometry type 13'];
        yield 'huge count' => ['0102000000FFFFFFFF', 'more bytes at offset 9'];
        yield 'NaN coordinate' => ['0101000000000000000000F87F0000000000000040', 'coordinates must be finite floats'];
        yield 'nested SRID' => [
            '0104000020E6100000010000000101000020E6100000000000000000F03F0000000000000040',
            'the members of a collection cannot carry an SRID',
        ];

        yield 'wrong member type' => [
            '0104000000010000000102000000000000000000',
            'its members must be of type POINT, got LINESTRING',
        ];
    }

    public function testInvalidGeoJson(): void
    {
        $this->expectException(InvalidGeometry::class);
        $this->expectExceptionMessage('Malformed GeoJSON: unsupported type "Feature".');

        Geometry::fromGeoJson('{"type":"Feature","geometry":null}');
    }

    public function testInvalidCoordinates(): void
    {
        $this->expectException(InvalidGeometry::class);
        $this->expectExceptionMessage('coordinates must be finite floats');

        Geometry::point(INF, 0);
    }
}
