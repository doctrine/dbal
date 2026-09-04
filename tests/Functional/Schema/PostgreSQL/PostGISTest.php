<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\PostgreSQL;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\SpatialTestCase;
use Doctrine\DBAL\Tests\SpatialReferenceSystems;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

final class PostGISTest extends SpatialTestCase
{
    private const TABLE_NAME = 'postgis_schema_test';

    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            return;
        }

        self::markTestSkipped('PostGIS tests require PostgreSQL with PostGIS extension.');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $schemaManager = $this->connection->createSchemaManager();
        if (! $schemaManager->tablesExist([self::TABLE_NAME])) {
            return;
        }

        $schemaManager->dropTable(self::TABLE_NAME);
    }

    public function testCreateTableWithSpatialColumns(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE_NAME)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
                    ->setSrid(SpatialReferenceSystems::SRID_WGS84)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('geog')
                    ->setTypeName(Types::GEOGRAPHY)
                    ->setGeometryType('POINT')
                    ->setSrid(SpatialReferenceSystems::SRID_WGS84)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        self::assertTrue($schemaManager->tablesExist([self::TABLE_NAME]));

        $columns = $schemaManager->listTableColumns(self::TABLE_NAME);

        self::assertSame(Type::getType(Types::GEOMETRY), $columns['geom']->getType());
        self::assertSame(Type::getType(Types::GEOGRAPHY), $columns['geog']->getType());
    }

    public function testSpatialColumnIntrospection(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE_NAME)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('simple_geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('typed_geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('LINESTRING')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('srid_geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->setSrid(SpatialReferenceSystems::SRID_UTM_33N)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('full_geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POLYGON')
                    ->setSrid(SpatialReferenceSystems::SRID_UTM_33N)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('simple_geog')
                    ->setTypeName(Types::GEOGRAPHY)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('typed_geog')
                    ->setTypeName(Types::GEOGRAPHY)
                    ->setGeometryType('LINESTRING')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('srid_geog')
                    ->setTypeName(Types::GEOGRAPHY)
                    ->setSrid(SpatialReferenceSystems::SRID_NAD27)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('full_geog')
                    ->setTypeName(Types::GEOGRAPHY)
                    ->setGeometryType('POLYGON')
                    ->setSrid(SpatialReferenceSystems::SRID_NAD27)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE_NAME);

        $simpleGeom = $columns['simple_geom'];
        self::assertSame(Type::getType(Types::GEOMETRY), $simpleGeom->getType());
        self::assertNull($simpleGeom->getGeometryType());
        self::assertNull($simpleGeom->getSrid());

        $typedGeom = $columns['typed_geom'];
        self::assertSame(Type::getType(Types::GEOMETRY), $typedGeom->getType());
        self::assertSame('LineString', $typedGeom->getGeometryType());
        self::assertNull($typedGeom->getSrid());

        $sridGeom = $columns['srid_geom'];
        self::assertSame(Type::getType(Types::GEOMETRY), $sridGeom->getType());
        self::assertSame('Geometry', $sridGeom->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_UTM_33N, $sridGeom->getSrid());

        $fullGeom = $columns['full_geom'];
        self::assertSame(Type::getType(Types::GEOMETRY), $fullGeom->getType());
        self::assertSame('Polygon', $fullGeom->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_UTM_33N, $fullGeom->getSrid());

        $simpleGeog = $columns['simple_geog'];
        self::assertSame(Type::getType(Types::GEOGRAPHY), $simpleGeog->getType());
        self::assertSame('Geometry', $simpleGeog->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $simpleGeog->getSrid());

        $typedGeog = $columns['typed_geog'];
        self::assertSame(Type::getType(Types::GEOGRAPHY), $typedGeog->getType());
        self::assertSame('LineString', $typedGeog->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $typedGeog->getSrid());

        $sridGeog = $columns['srid_geog'];
        self::assertSame(Type::getType(Types::GEOGRAPHY), $sridGeog->getType());
        self::assertSame('Geometry', $sridGeog->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_NAD27, $sridGeog->getSrid());

        $fullGeog = $columns['full_geog'];
        self::assertSame(Type::getType(Types::GEOGRAPHY), $fullGeog->getType());
        self::assertSame('Polygon', $fullGeog->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_NAD27, $fullGeog->getSrid());
    }

    public function testAlterTableAddSpatialColumn(): void
    {
        // Create table without spatial columns
        $table = Table::editor()
            ->setUnquotedName(self::TABLE_NAME)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        // Add spatial column via ALTER TABLE
        $newTable = Table::editor()
            ->setUnquotedName(self::TABLE_NAME)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('location')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
                    ->setSrid(SpatialReferenceSystems::SRID_WGS84)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $comparator = $schemaManager->createComparator();
        $tableDiff  = $comparator->compareTables($table, $newTable);

        $schemaManager->alterTable($tableDiff);

        // Verify spatial column was added
        $columns = $schemaManager->listTableColumns(self::TABLE_NAME);
        self::assertArrayHasKey('location', $columns);
        self::assertSame(Type::getType(Types::GEOMETRY), $columns['location']->getType());
    }

    public function testAlterTableAlterSpatialColumn(): void
    {
        // Create table without spatial columns
        $table = Table::editor()
            ->setUnquotedName(self::TABLE_NAME)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
                    ->setSrid(SpatialReferenceSystems::SRID_WGS84)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('geog')
                    ->setTypeName(Types::GEOGRAPHY)
                    ->setGeometryType('POINT')
                    ->setSrid(SpatialReferenceSystems::SRID_WGS84)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        // Add spatial column via ALTER TABLE
        $newTable = Table::editor()
            ->setUnquotedName(self::TABLE_NAME)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('LINESTRING')
                    ->setSrid(SpatialReferenceSystems::SRID_UTM_33N)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('geog')
                    ->setTypeName(Types::GEOGRAPHY)
                    ->setGeometryType('POLYGON')
                    ->setSrid(SpatialReferenceSystems::SRID_NAD27)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $comparator = $schemaManager->createComparator();
        $tableDiff  = $comparator->compareTables($table, $newTable);

        $schemaManager->alterTable($tableDiff);

        // Verify spatial column was added
        $columns = $schemaManager->listTableColumns(self::TABLE_NAME);

        $geom = $columns['geom'];
        self::assertSame(Type::getType(Types::GEOMETRY), $geom->getType());
        self::assertSame('LineString', $geom->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_UTM_33N, $geom->getSrid());

        $geog = $columns['geog'];
        self::assertSame(Type::getType(Types::GEOGRAPHY), $geog->getType());
        self::assertSame('Polygon', $geog->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_NAD27, $geog->getSrid());
    }

    public function testSpatialIndex(): void
    {
        $index = Index::editor()
            ->setUnquotedName('spatial_idx')
            ->setType(IndexType::SPATIAL)
            ->setUnquotedColumnNames('location')
            ->create();

        $table = Table::editor()
            ->setUnquotedName(self::TABLE_NAME)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('location')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
                    ->setSrid(4326)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setIndexes($index)
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTableByUnquotedName(self::TABLE_NAME);

        // Verify the table structure is maintained
        self::assertTrue(
            $schemaManager->createComparator()
                ->compareTables($table, $onlineTable)
                ->isEmpty(),
        );

        // Verify the spatial index exists
        self::assertTrue($onlineTable->hasIndex('spatial_idx'));

        // Verify the index type is SPATIAL
        $spatialIndex = $onlineTable->getIndex('spatial_idx');
        self::assertSame(IndexType::SPATIAL, $spatialIndex->getType());
    }
}
