<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\SpatialRelation;
use Doctrine\DBAL\Tests\Functional\SpatialTestCase;
use Doctrine\DBAL\Types\Types;

use function array_combine;
use function array_intersect_key;
use function array_map;
use function in_array;
use function round;
use function strtolower;

final class SpatialExpressionTest extends SpatialTestCase
{
    private const POLYGON = 'POLYGON((0 0,10 0,10 10,0 10,0 0))';

    private const PARIS  = 'POINT(2.3522 48.8566)';
    private const LONDON = 'POINT(-0.1276 51.5072)';

    /** The geodesic distance between Paris and London on the WGS 84 ellipsoid, in meters, as computed by PostGIS */
    private const PARIS_LONDON_DISTANCE = 343896.891;

    private const DIMENSION_DEPENDENT_RELATIONS = [
        SpatialRelation::CROSSES,
        SpatialRelation::OVERLAPS,
        SpatialRelation::TOUCHES,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $this->requireSpatiaLite();
    }

    public function testRelations(): void
    {
        // the expected relations, among contains, crosses, disjoint, equals, intersects, overlaps, touches and within
        $cases = [
            'polygon and interior point' => [self::POLYGON, 'POINT(5 5)', ['contains', 'intersects']],
            'interior point and polygon' => ['POINT(5 5)', self::POLYGON, ['intersects', 'within']],
            'polygon and boundary point' => [self::POLYGON, 'POINT(10 5)', ['intersects', 'touches']],
            'line on the boundary and polygon' => ['LINESTRING(0 0,10 0)', self::POLYGON, ['intersects', 'touches']],
            'polygon and interior line ending on the boundary' => [
                self::POLYGON,
                'LINESTRING(0 5,5 5)',
                ['contains', 'intersects'],
            ],
            'line leaving the polygon' => ['LINESTRING(5 5,15 5)', self::POLYGON, ['crosses', 'intersects']],
            'points inside and outside the polygon' => [
                'MULTIPOINT((5 5),(15 15))',
                self::POLYGON,
                ['crosses', 'intersects'],
            ],
            'overlapping polygons' => [
                self::POLYGON,
                'POLYGON((5 5,15 5,15 15,5 15,5 5))',
                ['intersects', 'overlaps'],
            ],
            'crossing lines' => ['LINESTRING(0 0,10 10)', 'LINESTRING(0 10,10 0)', ['crosses', 'intersects']],
            'overlapping lines' => ['LINESTRING(0 0,10 0)', 'LINESTRING(5 0,15 0)', ['intersects', 'overlaps']],
            'equal polygons' => [
                self::POLYGON,
                'POLYGON((10 0,10 10,0 10,0 0,10 0))',
                ['contains', 'equals', 'intersects', 'within'],
            ],
            'disjoint points' => ['POINT(0 0)', 'POINT(1 1)', ['disjoint']],
        ];

        $this->insertGeometries(array_map(static fn (array $case): array => [$case[0], $case[1]], $cases));

        $platform = $this->connection->getDatabasePlatform();
        $actual   = $expected = [];

        foreach (SpatialRelation::cases() as $relation) {
            $relationName = strtolower($relation->name);
            $values       = $this->fetchBooleans($platform->getSpatialRelationExpression($relation, 'a', 'b'));

            foreach ($values as $case => $value) {
                // some platforms evaluate the relationships that are not defined for the dimensions of the geometries
                // to NULL, such as a point crossing a polygon
                if ($value === null && in_array($relation, self::DIMENSION_DEPENDENT_RELATIONS, true)) {
                    $value = false;
                }

                $actual[$case][$relationName]   = $value;
                $expected[$case][$relationName] = in_array($relationName, $cases[$case][2], true);
            }
        }

        if ($platform instanceof MariaDBPlatform) {
            // MariaDB does not follow the specification for some geometries touching each other
            $expected['polygon and boundary point']['contains']     = true;
            $expected['line on the boundary and polygon']['within'] = true;
            $expected['crossing lines']['overlaps']                 = true;
            $expected['overlapping lines']['crosses']               = true;
        }

        self::assertSame($expected, $actual);
    }

    public function testRelationOfNullGeometry(): void
    {
        $this->insertGeometries(['null' => [null, 'POINT(1 1)']]);

        $platform = $this->connection->getDatabasePlatform();

        foreach (SpatialRelation::cases() as $relation) {
            self::assertSame(
                ['null' => null],
                $this->fetchBooleans($platform->getSpatialRelationExpression($relation, 'a', 'b')),
                $relation->name,
            );
        }
    }

    public function testDistance(): void
    {
        // the rows following the NULL geometries cover a bug of MariaDB
        $this->insertGeometries([
            'null' => [null, 'POINT(1 1)'],
            'points' => ['POINT(0 0)', 'POINT(3 4)'],
            'point and line' => ['POINT(1 5)', 'LINESTRING(0 2,4 2)'],
            'null second geometry' => ['POINT(1 1)', null],
            'polygon and interior point' => [self::POLYGON, 'POINT(5 5)'],
            'geographic points' => ['SRID=4326;POINT(2 48)', 'SRID=4326;POINT(5 52)'],
        ]);

        self::assertSame([
            'null' => null,
            'points' => 5.0,
            'point and line' => 3.0,
            'null second geometry' => null,
            'polygon and interior point' => 0.0,
            'geographic points' => 5.0,
        ], $this->fetchNumbers(
            $this->connection->getDatabasePlatform()->getSpatialDistanceExpression('a', 'b'),
        ));
    }

    public function testWithinDistance(): void
    {
        $this->insertGeometries([
            'null' => [null, 'POINT(1 1)'],
            'points' => ['POINT(0 0)', 'POINT(3 4)'],
            'geographic points' => ['SRID=4326;POINT(2 48)', 'SRID=4326;POINT(5 52)'],
        ]);

        $platform = $this->connection->getDatabasePlatform();

        self::assertSame(
            ['null' => null, 'points' => true, 'geographic points' => true],
            $this->fetchBooleans($platform->getSpatialWithinDistanceExpression('a', 'b', '5')),
        );

        self::assertSame(
            ['null' => null, 'points' => false, 'geographic points' => false],
            $this->fetchBooleans($platform->getSpatialWithinDistanceExpression('a', 'b', '4.5 + 0.4')),
        );
    }

    public function testArea(): void
    {
        $this->insertGeometries([
            'polygon' => [self::POLYGON, null],
            'polygon with hole' => ['POLYGON((0 0,10 0,10 10,0 10,0 0),(2 2,2 4,4 4,4 2,2 2))', null],
            'multi-polygon' => ['MULTIPOLYGON(((0 0,1 0,1 1,0 1,0 0)),((2 2,4 2,4 4,2 4,2 2)))', null],
            'geographic polygon' => ['SRID=4326;POLYGON((2 48,3 48,3 49,2 49,2 48))', null],
            'null' => [null, null],
        ]);

        self::assertSame([
            'polygon' => 100.0,
            'polygon with hole' => 96.0,
            'multi-polygon' => 5.0,
            'geographic polygon' => 1.0,
            'null' => null,
        ], $this->fetchNumbers($this->connection->getDatabasePlatform()->getSpatialAreaExpression('a')));
    }

    public function testLength(): void
    {
        $geometries = [
            'line string' => ['LINESTRING(0 0,3 4,3 10)', null],
            'multi-line string' => ['MULTILINESTRING((0 0,3 4),(0 0,0 1))', null],
            'geographic line string' => ['SRID=4326;LINESTRING(2 48,5 52)', null],
            'null' => [null, null],
        ];

        $expected = [
            'line string' => 11.0,
            'multi-line string' => 6.0,
            'geographic line string' => 5.0,
            'null' => null,
        ];

        if (! $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $geometries['three-dimensional line string'] = ['LINESTRING Z(0 0 0,3 4 12)', null];
            $expected['three-dimensional line string']   = 5.0;
        }

        $this->insertGeometries($geometries);

        self::assertSame(
            $expected,
            $this->fetchNumbers($this->connection->getDatabasePlatform()->getSpatialLengthExpression('a')),
        );
    }

    public function testCoordinatesAndSrid(): void
    {
        $this->insertGeometries([
            'point' => ['POINT(1 2)', null],
            'geographic point' => ['SRID=4326;POINT(2.5 48.25)', null],
            'null' => [null, null],
        ]);

        $platform = $this->connection->getDatabasePlatform();

        self::assertSame(
            ['point' => 1.0, 'geographic point' => 2.5, 'null' => null],
            $this->fetchNumbers($platform->getSpatialXExpression('a')),
        );

        self::assertSame(
            ['point' => 2.0, 'geographic point' => 48.25, 'null' => null],
            $this->fetchNumbers($platform->getSpatialYExpression('a')),
        );

        self::assertSame(
            ['point' => 0.0, 'geographic point' => 4326.0, 'null' => null],
            $this->fetchNumbers($platform->getSpatialSridExpression('a')),
        );
    }

    public function testGeodesicDistance(): void
    {
        $this->initializeSpatiaLiteMetadata();

        $platform = $this->connection->getDatabasePlatform();

        $geometries = [
            'points' => ['SRID=4326;' . self::PARIS, 'SRID=4326;' . self::LONDON],
            'null' => [null, 'SRID=4326;' . self::LONDON],
        ];

        // Db2 rounds the coordinates of the geometries without SRID to integers
        if (! $platform instanceof DB2Platform) {
            $geometries['points without SRID'] = [self::PARIS, self::LONDON];
        }

        $this->insertGeometries($geometries);

        $distances = $this->fetchNumbers($platform->getSpatialGeodesicDistanceExpression('a', 'b'));

        self::assertNull($distances['null']);
        unset($distances['null']);

        // MySQL 5.7 and MariaDB compute on a sphere
        foreach ($distances as $distance) {
            self::assertEqualsWithDelta(self::PARIS_LONDON_DISTANCE, $distance, self::PARIS_LONDON_DISTANCE * 0.005);
        }

        self::assertSame(
            ['points' => true, 'null' => null],
            array_intersect_key(
                $this->fetchBooleans($platform->getSpatialGeodesicWithinDistanceExpression('a', 'b', '350000')),
                ['points' => true, 'null' => true],
            ),
        );

        self::assertSame(
            ['points' => false, 'null' => null],
            array_intersect_key(
                $this->fetchBooleans($platform->getSpatialGeodesicWithinDistanceExpression('a', 'b', '300000')),
                ['points' => true, 'null' => true],
            ),
        );
    }

    public function testGeodesicAreaAndLength(): void
    {
        $this->initializeSpatiaLiteMetadata();

        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform && ! $platform instanceof MySQL80Platform) {
            $this->expectException(NotSupported::class);
        }

        $areaExpression   = $platform->getSpatialGeodesicAreaExpression('a');
        $lengthExpression = $platform->getSpatialGeodesicLengthExpression('b');

        $this->insertGeometries([
            'geometries' => [
                'SRID=4326;POLYGON((2 48,3 48,3 49,2 49,2 48))',
                'SRID=4326;LINESTRING(2.3522 48.8566,-0.1276 51.5072)',
            ],
            'null' => [null, null],
        ]);

        $areas = $this->fetchNumbers($areaExpression);

        self::assertNull($areas['null']);
        self::assertEqualsWithDelta(8217589924.77, $areas['geometries'], 8217589924.77 * 0.001);

        $lengths = $this->fetchNumbers($lengthExpression);

        self::assertNull($lengths['null']);
        self::assertEqualsWithDelta(
            self::PARIS_LONDON_DISTANCE,
            $lengths['geometries'],
            self::PARIS_LONDON_DISTANCE * 0.001,
        );
    }

    public function testParameters(): void
    {
        $this->insertGeometries([
            'near' => ['POINT(1 1)', null],
            'far' => ['POINT(8 8)', null],
        ]);

        $platform  = $this->connection->getDatabasePlatform();
        $parameter = $this->connection->convertToDatabaseValueSQL('?', Types::GEOMETRY);
        $true      = $platform->convertBooleans(true);

        self::assertSame(['near'], $this->connection->fetchFirstColumn(
            'SELECT name FROM geometry_expression_test WHERE '
                . $platform->getSpatialWithinDistanceExpression('a', $parameter, '?') . ' = ' . $true,
            [Geometry::point(0, 0), 2],
            [Types::GEOMETRY, Types::INTEGER],
        ));

        self::assertSame(['far'], $this->connection->fetchFirstColumn(
            'SELECT name FROM geometry_expression_test WHERE '
                . $platform->getSpatialRelationExpression(SpatialRelation::CONTAINS, $parameter, 'a') . ' = ' . $true,
            [Geometry::fromWkt('POLYGON((5 5,10 5,10 10,5 10,5 5))')],
            [Types::GEOMETRY],
        ));

        self::assertEquals(5, $this->connection->fetchOne(
            $platform->getDummySelectSQL($platform->getSpatialDistanceExpression($parameter, $parameter)),
            [Geometry::point(0, 0), Geometry::point(3, 4)],
            [Types::GEOMETRY, Types::GEOMETRY],
        ));
    }

    /**
     * The geodesic functions of SpatiaLite read the ellipsoid of the SRID from the spatial_ref_sys table, which
     * InitSpatialMetadata() creates.
     */
    private function initializeSpatiaLiteMetadata(): void
    {
        if (
            ! $this->connection->getDatabasePlatform() instanceof SQLitePlatform
            || $this->connection->fetchOne("SELECT 1 FROM sqlite_master WHERE name = 'spatial_ref_sys'") !== false
        ) {
            return;
        }

        $this->connection->executeQuery("SELECT InitSpatialMetadata(1, 'WGS84_ONLY')");
    }

    /** @param array<string, array{?string, ?string}> $geometries */
    private function insertGeometries(array $geometries): void
    {
        $this->dropAndCreateTable(
            Table::editor()
                ->setUnquotedName('geometry_expression_test')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('id')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('name')
                        ->setTypeName(Types::STRING)
                        ->setLength(64)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('a')
                        ->setTypeName(Types::GEOMETRY)
                        ->setNotNull(false)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('b')
                        ->setTypeName(Types::GEOMETRY)
                        ->setNotNull(false)
                        ->create(),
                )
                ->setPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()
                        ->setUnquotedColumnNames('id')
                        ->create(),
                )
                ->create(),
        );

        $id = 0;

        foreach ($geometries as $name => [$a, $b]) {
            $this->connection->insert('geometry_expression_test', [
                'id' => ++$id,
                'name' => $name,
                'a' => $a === null ? null : Geometry::fromWkt($a),
                'b' => $b === null ? null : Geometry::fromWkt($b),
            ], ['a' => Types::GEOMETRY, 'b' => Types::GEOMETRY]);
        }
    }

    /** @return array<string, ?bool> */
    private function fetchBooleans(string $expression): array
    {
        return array_map(
            fn (mixed $value): ?bool => $this->connection->convertToPHPValue($value, Types::BOOLEAN),
            $this->fetchValues($expression),
        );
    }

    /** @return array<string, ?float> */
    private function fetchNumbers(string $expression): array
    {
        return array_map(
            static fn (mixed $value): ?float => $value === null ? null : round((float) $value, 9),
            $this->fetchValues($expression),
        );
    }

    /** @return array<string, mixed> */
    private function fetchValues(string $expression): array
    {
        $rows = $this->connection->fetchAllNumeric(
            'SELECT name, ' . $expression . ' FROM geometry_expression_test ORDER BY id',
        );

        return array_combine(
            array_map(static fn (array $row): string => $row[0], $rows),
            array_map(static fn (array $row): mixed => $row[1], $rows),
        );
    }
}
