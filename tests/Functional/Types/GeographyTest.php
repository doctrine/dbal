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
