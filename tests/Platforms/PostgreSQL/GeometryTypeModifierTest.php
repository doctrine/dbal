<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\PostgreSQL;

use Doctrine\DBAL\Platforms\PostgreSQL\GeometryTypeModifier;
use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\GeometryKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GeometryTypeModifierTest extends TestCase
{
    #[DataProvider('renderProvider')]
    public function testRender(?GeometryKind $kind, ?Dimension $dimension, ?int $srid, string $expected): void
    {
        self::assertSame($expected, GeometryTypeModifier::render('geometry', $kind, $dimension, $srid));
    }

    /** @return iterable<string, array{?GeometryKind, ?Dimension, ?int, string}> */
    public static function renderProvider(): iterable
    {
        yield 'unconstrained' => [null, null, null, 'geometry'];
        yield 'SRID 0' => [null, null, 0, 'geometry'];
        yield 'SRID' => [null, null, 4326, 'geometry(Geometry,4326)'];
        yield 'kind' => [GeometryKind::POINT, null, null, 'geometry(Point)'];
        yield 'two-dimensional' => [null, Dimension::XY, null, 'geometry(Geometry)'];
        yield 'all' => [GeometryKind::MULTIPOLYGON, Dimension::XYZM, 2154, 'geometry(MultiPolygonZM,2154)'];
        yield 'M' => [GeometryKind::LINESTRING, Dimension::XYM, 3857, 'geometry(LineStringM,3857)'];
    }

    /** Type modifiers computed by PostGIS 3.6 (pg_attribute.atttypmod) */
    #[DataProvider('parseProvider')]
    public function testParse(int $typeModifier, ?GeometryKind $kind, ?Dimension $dimension, ?int $srid): void
    {
        self::assertSame([$kind, $dimension, $srid], GeometryTypeModifier::parse($typeModifier));
    }

    /** @return iterable<string, array{int, ?GeometryKind, ?Dimension, ?int}> */
    public static function parseProvider(): iterable
    {
        yield 'geometry' => [-1, null, null, null];
        yield 'geometry(Geometry)' => [0, GeometryKind::GEOMETRY, Dimension::XY, null];
        yield 'geometry(Geometry,4326)' => [1107456, GeometryKind::GEOMETRY, Dimension::XY, 4326];
        yield 'geometry(Point)' => [4, GeometryKind::POINT, Dimension::XY, null];
        yield 'geometry(Point,4326)' => [1107460, GeometryKind::POINT, Dimension::XY, 4326];
        yield 'geometry(PointZ,4326)' => [1107462, GeometryKind::POINT, Dimension::XYZ, 4326];
        yield 'geometry(PointM,4326)' => [1107461, GeometryKind::POINT, Dimension::XYM, 4326];
        yield 'geometry(PointZM,4326)' => [1107463, GeometryKind::POINT, Dimension::XYZM, 4326];
        yield 'geometry(LineString,3857)' => [987400, GeometryKind::LINESTRING, Dimension::XY, 3857];
        yield 'geometry(Polygon,2154)' => [551436, GeometryKind::POLYGON, Dimension::XY, 2154];
    }
}
