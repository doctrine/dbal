<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;
use Doctrine\DBAL\Tests\Functional\SpatialTestCase;
use Doctrine\DBAL\Types\GeographyType;
use Doctrine\DBAL\Types\GeometryType;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function implode;
use function sprintf;

final class GeographyTest extends SpatialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTableIfExists('geography_test');
    }

    #[DataProvider('valueProvider')]
    public function testValueRoundTrip(string $wkt, string $expectedEwkt): void
    {
        $this->createTable(
            [],
            Column::editor()
                ->setUnquotedName('location')
                ->setTypeName(Types::GEOGRAPHY)
                ->setNotNull(false)
                ->create(),
        );

        $this->connection->insert(
            'geography_test',
            ['id' => 1, 'location' => Geometry::fromWkt($wkt)],
            ['location' => Types::GEOGRAPHY],
        );

        $value = $this->connection->convertToPHPValue($this->fetchLocation(), Types::GEOGRAPHY);

        self::assertInstanceOf(Geometry::class, $value);
        self::assertTrue($value->equals(Geometry::fromWkt($expectedEwkt)), $value->asEwkt());
    }

    /** @return iterable<string, array{string, string}> */
    public static function valueProvider(): iterable
    {
        yield 'point' => ['SRID=4326;POINT(2.3522219 48.856614)', 'SRID=4326;POINT(2.3522219 48.856614)'];
        yield 'point without SRID' => ['POINT(-0.1276 51.5072)', 'SRID=4326;POINT(-0.1276 51.5072)'];
        yield 'line string' => ['SRID=4326;LINESTRING(2 48,3 49,4 48)', 'SRID=4326;LINESTRING(2 48,3 49,4 48)'];
        yield 'polygon' => [
            'SRID=4326;POLYGON((2 48,3 48,3 49,2 49,2 48))',
            'SRID=4326;POLYGON((2 48,3 48,3 49,2 49,2 48))',
        ];

        yield 'multi-point' => ['SRID=4326;MULTIPOINT((2 48),(3 49))', 'SRID=4326;MULTIPOINT((2 48),(3 49))'];
    }

    public function testNull(): void
    {
        $this->createTable(
            [],
            Column::editor()
                ->setUnquotedName('location')
                ->setTypeName(Types::GEOGRAPHY)
                ->setNotNull(false)
                ->create(),
        );

        $this->connection->insert('geography_test', ['id' => 1, 'location' => null], ['location' => Types::GEOGRAPHY]);

        self::assertNull($this->connection->convertToPHPValue($this->fetchLocation(), Types::GEOGRAPHY));
    }

    public function testSchema(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $table = $this->createTable(
            [],
            Column::editor()
                ->setUnquotedName('any_geography')
                ->setTypeName(Types::GEOGRAPHY)
                ->setNotNull(false)
                ->create(),
            Column::editor()
                ->setUnquotedName('point')
                ->setTypeName(Types::GEOGRAPHY)
                ->setGeometryType(GeometryKind::POINT)
                ->setNotNull(false)
                ->create(),
            Column::editor()
                ->setUnquotedName('srid_4269')
                ->setTypeName(Types::GEOGRAPHY)
                ->setSrid(4269)
                ->setNotNull(false)
                ->create(),
        );

        $schemaManager     = $this->connection->createSchemaManager();
        $introspectedTable = $schemaManager->introspectTableByUnquotedName('geography_test');

        self::assertTrue($schemaManager->createComparator()->compareTables($introspectedTable, $table)->isEmpty());
        self::assertTrue($schemaManager->createComparator()->compareTables($table, $introspectedTable)->isEmpty());

        $column = $introspectedTable->getColumn('point');

        // the other platforms declare geometry columns
        if ($platform instanceof PostgreSQLPlatform || $platform instanceof SQLServerPlatform) {
            self::assertInstanceOf(GeographyType::class, $column->getType());
        } else {
            self::assertInstanceOf(GeometryType::class, $column->getType());
        }

        if ($platform instanceof PostgreSQLPlatform) {
            self::assertSame(GeometryKind::POINT, $column->getGeometryType());
            self::assertSame(4326, $column->getSrid());
            self::assertSame(4269, $introspectedTable->getColumn('srid_4269')->getSrid());
        }

        if (! ($platform instanceof MySQL80Platform) && ! ($platform instanceof OraclePlatform)) {
            return;
        }

        self::assertSame(4326, $column->getSrid());
        self::assertSame(4269, $introspectedTable->getColumn('srid_4269')->getSrid());
    }

    public function testSpatialIndex(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform || $platform instanceof DB2Platform) {
            self::markTestSkipped(sprintf('%s does not support spatial indexes.', $platform::class));
        }

        $table = $this->createTable(
            [
                Index::editor()
                    ->setUnquotedName('geography_test_location')
                    ->setUnquotedColumnNames('location')
                    ->setType(IndexType::SPATIAL)
                    ->create(),
            ],
            Column::editor()
                ->setUnquotedName('location')
                ->setTypeName(Types::GEOGRAPHY)
                ->setGeometryType(GeometryKind::POINT)
                ->create(),
        );

        $this->connection->insert(
            'geography_test',
            ['id' => 1, 'location' => Geometry::point(2.3522219, 48.856614)],
            ['location' => Types::GEOGRAPHY],
        );

        $schemaManager     = $this->connection->createSchemaManager();
        $introspectedTable = $schemaManager->introspectTableByUnquotedName('geography_test');

        self::assertSame(IndexType::SPATIAL, $introspectedTable->getIndex('geography_test_location')->getType());
        self::assertTrue($schemaManager->createComparator()->compareTables($introspectedTable, $table)->isEmpty());
    }

    public function testGeodesicWithinDistance(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->requireSpatiaLite();

            // the geodesic functions of SpatiaLite read the ellipsoids from the spatial_ref_sys table
            if ($this->connection->fetchOne("SELECT 1 FROM sqlite_master WHERE name = 'spatial_ref_sys'") === false) {
                $this->connection->executeQuery("SELECT InitSpatialMetadata(1, 'WGS84_ONLY')");
            }
        }

        $this->createTable(
            [],
            Column::editor()
                ->setUnquotedName('location')
                ->setTypeName(Types::GEOGRAPHY)
                ->create(),
        );

        foreach ([1 => [2.3522219, 48.856614], 2 => [-0.1276, 51.5072], 3 => [13.405, 52.52]] as $id => [$x, $y]) {
            $this->connection->insert(
                'geography_test',
                ['id' => $id, 'location' => Geometry::point($x, $y)],
                ['location' => Types::GEOGRAPHY],
            );
        }

        $ids = $this->connection->fetchFirstColumn(
            sprintf(
                'SELECT id FROM geography_test WHERE %s = %s ORDER BY id',
                $platform->getSpatialGeodesicWithinDistanceExpression(
                    'location',
                    $this->connection->convertToDatabaseValueSQL('?', Types::GEOGRAPHY),
                    '?',
                ),
                $platform->convertBooleans(true),
            ),
            [Geometry::point(2.3522219, 48.856614), 350000],
            [Types::GEOGRAPHY, Types::INTEGER],
        );

        self::assertEquals([1, 2], $ids);
    }

    /**
     * PostgreSQL uses the index of a geography column for the geodesic tests of distance.
     */
    public function testGeodesicWithinDistanceUsesTheIndexOnPostgreSQL(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform instanceof PostgreSQLPlatform) {
            self::markTestSkipped('This test covers the index of PostgreSQL geography columns.');
        }

        $this->createTable(
            [
                Index::editor()
                    ->setUnquotedName('geography_test_location')
                    ->setUnquotedColumnNames('location')
                    ->setType(IndexType::SPATIAL)
                    ->create(),
            ],
            Column::editor()
                ->setUnquotedName('location')
                ->setTypeName(Types::GEOGRAPHY)
                ->create(),
        );

        $this->connection->beginTransaction();

        try {
            $this->connection->executeStatement('SET LOCAL enable_seqscan = off');

            $plan = $this->connection->fetchFirstColumn(sprintf(
                'EXPLAIN SELECT id FROM geography_test WHERE %s',
                $platform->getSpatialGeodesicWithinDistanceExpression(
                    'location',
                    "'SRID=4326;POINT(2.3522219 48.856614)'::geography",
                    '1000',
                ),
            ));
        } finally {
            $this->connection->rollBack();
        }

        self::assertStringContainsString('geography_test_location', implode("\n", $plan));
    }

    /** @param list<Index> $indexes */
    private function createTable(array $indexes, Column ...$columns): Table
    {
        $table = Table::editor()
            ->setUnquotedName('geography_test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                ...$columns,
            )
            ->setIndexes(...$indexes)
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        return $table;
    }

    private function fetchLocation(): mixed
    {
        return $this->connection->fetchOne(sprintf(
            'SELECT %s FROM geography_test WHERE id = 1',
            $this->connection->convertToPHPValueSQL('location', Types::GEOGRAPHY),
        ));
    }
}
