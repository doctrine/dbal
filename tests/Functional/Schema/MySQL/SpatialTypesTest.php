<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\MySQL;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\SpatialTestCase;
use Doctrine\DBAL\Tests\SpatialReferenceSystems;
use Doctrine\DBAL\Types\GeometryType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

final class SpatialTypesTest extends SpatialTestCase
{
    private const TABLE_NAME = 'mysql_spatial_schema_test';

    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            return;
        }

        self::markTestSkipped('This test is MySQL-specific and should not run on other platforms.');
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
                    ->setUnquotedName('point_geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('linestring_geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('LINESTRING')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('polygon_geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POLYGON')
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
        self::assertSame('geometry', $simpleGeom->getGeometryType());
        self::assertNull($simpleGeom->getSrid());

        $pointGeom = $columns['point_geom'];
        self::assertSame(Type::getType(Types::GEOMETRY), $pointGeom->getType());
        self::assertSame('point', $pointGeom->getGeometryType());
        self::assertNull($pointGeom->getSrid());

        $linestringGeom = $columns['linestring_geom'];
        self::assertSame(Type::getType(Types::GEOMETRY), $linestringGeom->getType());
        self::assertSame('linestring', $linestringGeom->getGeometryType());
        self::assertNull($linestringGeom->getSrid());

        $polygonGeom = $columns['polygon_geom'];
        self::assertSame(Type::getType(Types::GEOMETRY), $polygonGeom->getType());
        self::assertSame('polygon', $polygonGeom->getGeometryType());
        self::assertNull($polygonGeom->getSrid());
    }

    public function testSpatialColumnIntrospectionWithSrid(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform instanceof MySQL80Platform) {
            self::markTestSkipped('This test requires MySQL 8.0+ for SRID support in column definitions.');
        }

        $table = Table::editor()
            ->setUnquotedName(self::TABLE_NAME)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('point_srid')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
                    ->setSrid(SpatialReferenceSystems::SRID_WGS84)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('linestring_srid')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('LINESTRING')
                    ->setSrid(SpatialReferenceSystems::SRID_UTM_33N)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('polygon_srid')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POLYGON')
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

        $columns = $schemaManager->listTableColumns(self::TABLE_NAME);

        $pointSrid = $columns['point_srid'];
        self::assertSame(Type::getType(Types::GEOMETRY), $pointSrid->getType());
        self::assertSame('point', $pointSrid->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $pointSrid->getSrid());

        $linestringSrid = $columns['linestring_srid'];
        self::assertSame(Type::getType(Types::GEOMETRY), $linestringSrid->getType());
        self::assertSame('linestring', $linestringSrid->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_UTM_33N, $linestringSrid->getSrid());

        $polygonSrid = $columns['polygon_srid'];
        self::assertSame(Type::getType(Types::GEOMETRY), $polygonSrid->getType());
        self::assertSame('polygon', $polygonSrid->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $polygonSrid->getSrid());
    }

    public function testIntrospectTableCreatedWithRawSQLContainingSpatialColumns(): void
    {
        $this->connection->executeStatement(<<< 'SQL'
            CREATE TABLE mysql_spatial_raw_sql_test (
                id BIGINT NOT NULL PRIMARY KEY,
                point_col POINT DEFAULT NULL,
                linestring_col LINESTRING DEFAULT NULL,
                polygon_col POLYGON DEFAULT NULL,
                multipoint_col MULTIPOINT DEFAULT NULL,
                multilinestring_col MULTILINESTRING DEFAULT NULL,
                multipolygon_col MULTIPOLYGON DEFAULT NULL,
                geometrycollection_col GEOMETRYCOLLECTION DEFAULT NULL
            );
            SQL);

        $schemaManager = $this->connection->createSchemaManager();
        $table         = $schemaManager->introspectTableByUnquotedName('mysql_spatial_raw_sql_test');

        // POINT column
        $pointCol = $table->getColumn('point_col');
        self::assertInstanceOf(GeometryType::class, $pointCol->getType());
        self::assertSame('point', $pointCol->getGeometryType());
        self::assertNull($pointCol->getSrid());

        // LINESTRING column
        $linestringCol = $table->getColumn('linestring_col');
        self::assertInstanceOf(GeometryType::class, $linestringCol->getType());
        self::assertSame('linestring', $linestringCol->getGeometryType());
        self::assertNull($linestringCol->getSrid());

        // POLYGON column
        $polygonCol = $table->getColumn('polygon_col');
        self::assertInstanceOf(GeometryType::class, $polygonCol->getType());
        self::assertSame('polygon', $polygonCol->getGeometryType());
        self::assertNull($polygonCol->getSrid());

        // MULTIPOINT column
        $multipointCol = $table->getColumn('multipoint_col');
        self::assertInstanceOf(GeometryType::class, $multipointCol->getType());
        self::assertSame('multipoint', $multipointCol->getGeometryType());
        self::assertNull($multipointCol->getSrid());

        // MULTILINESTRING column
        $multilinestringCol = $table->getColumn('multilinestring_col');
        self::assertInstanceOf(GeometryType::class, $multilinestringCol->getType());
        self::assertSame('multilinestring', $multilinestringCol->getGeometryType());
        self::assertNull($multilinestringCol->getSrid());

        // MULTIPOLYGON column
        $multipolygonCol = $table->getColumn('multipolygon_col');
        self::assertInstanceOf(GeometryType::class, $multipolygonCol->getType());
        self::assertSame('multipolygon', $multipolygonCol->getGeometryType());
        self::assertNull($multipolygonCol->getSrid());

        // GEOMETRYCOLLECTION column
        $geometrycollectionCol = $table->getColumn('geometrycollection_col');
        self::assertInstanceOf(GeometryType::class, $geometrycollectionCol->getType());
        self::assertSame('geometrycollection', $geometrycollectionCol->getGeometryType());
        self::assertNull($geometrycollectionCol->getSrid());

        // Clean up
        $schemaManager->dropTable('mysql_spatial_raw_sql_test');
    }

    public function testIntrospectTableCreatedWithRawSQLContainingSpatialColumnsWithSrid(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform instanceof MySQL80Platform) {
            self::markTestSkipped('This test requires MySQL 8.0+ for SRID support in column definitions.');
        }

        $this->connection->executeStatement(<<< 'SQL'
            CREATE TABLE mysql_spatial_raw_sql_srid_test (
                id BIGINT NOT NULL PRIMARY KEY,
                point_col POINT SRID 4326 DEFAULT NULL,
                linestring_col LINESTRING SRID 32633 DEFAULT NULL,
                polygon_col POLYGON SRID 4326 DEFAULT NULL,
                multipoint_col MULTIPOINT SRID 4326 DEFAULT NULL,
                multilinestring_col MULTILINESTRING SRID 32633 DEFAULT NULL,
                multipolygon_col MULTIPOLYGON SRID 4326 DEFAULT NULL,
                geometrycollection_col GEOMETRYCOLLECTION SRID 4326 DEFAULT NULL
            );
            SQL);

        $schemaManager = $this->connection->createSchemaManager();
        $table         = $schemaManager->introspectTableByUnquotedName('mysql_spatial_raw_sql_srid_test');

        // POINT column
        $pointCol = $table->getColumn('point_col');
        self::assertInstanceOf(GeometryType::class, $pointCol->getType());
        self::assertSame('point', $pointCol->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $pointCol->getSrid());

        // LINESTRING column
        $linestringCol = $table->getColumn('linestring_col');
        self::assertInstanceOf(GeometryType::class, $linestringCol->getType());
        self::assertSame('linestring', $linestringCol->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_UTM_33N, $linestringCol->getSrid());

        // POLYGON column
        $polygonCol = $table->getColumn('polygon_col');
        self::assertInstanceOf(GeometryType::class, $polygonCol->getType());
        self::assertSame('polygon', $polygonCol->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $polygonCol->getSrid());

        // MULTIPOINT column
        $multipointCol = $table->getColumn('multipoint_col');
        self::assertInstanceOf(GeometryType::class, $multipointCol->getType());
        self::assertSame('multipoint', $multipointCol->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $multipointCol->getSrid());

        // MULTILINESTRING column
        $multilinestringCol = $table->getColumn('multilinestring_col');
        self::assertInstanceOf(GeometryType::class, $multilinestringCol->getType());
        self::assertSame('multilinestring', $multilinestringCol->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_UTM_33N, $multilinestringCol->getSrid());

        // MULTIPOLYGON column
        $multipolygonCol = $table->getColumn('multipolygon_col');
        self::assertInstanceOf(GeometryType::class, $multipolygonCol->getType());
        self::assertSame('multipolygon', $multipolygonCol->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $multipolygonCol->getSrid());

        // GEOMETRYCOLLECTION column
        $geometrycollectionCol = $table->getColumn('geometrycollection_col');
        self::assertInstanceOf(GeometryType::class, $geometrycollectionCol->getType());
        self::assertSame('geometrycollection', $geometrycollectionCol->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $geometrycollectionCol->getSrid());

        // Clean up
        $schemaManager->dropTable('mysql_spatial_raw_sql_srid_test');
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
                    ->setLength(255)
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
                    ->setLength(255)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('location')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
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
        $alterSql   = $this->connection->getDatabasePlatform()->getAlterTableSQL($tableDiff);

        // Execute ALTER TABLE
        foreach ($alterSql as $sql) {
            $this->connection->executeStatement($sql);
        }

        // Verify spatial column was added
        $columns = $schemaManager->listTableColumns(self::TABLE_NAME);
        self::assertArrayHasKey('location', $columns);
        self::assertSame(Type::getType(Types::GEOMETRY), $columns['location']->getType());
        self::assertSame('point', $columns['location']->getGeometryType());
        self::assertNull($columns['location']->getSrid());
    }

    public function testAlterTableAddSpatialColumnWithSrid(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform instanceof MySQL80Platform) {
            self::markTestSkipped('This test requires MySQL 8.0+ for SRID support in column definitions.');
        }

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
                    ->setLength(255)
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

        // Add spatial column with SRID via ALTER TABLE
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
                    ->setLength(255)
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
        $alterSql   = $this->connection->getDatabasePlatform()->getAlterTableSQL($tableDiff);

        // Execute ALTER TABLE
        foreach ($alterSql as $sql) {
            $this->connection->executeStatement($sql);
        }

        // Verify spatial column with SRID was added
        $columns = $schemaManager->listTableColumns(self::TABLE_NAME);
        self::assertArrayHasKey('location', $columns);
        self::assertSame(Type::getType(Types::GEOMETRY), $columns['location']->getType());
        self::assertSame('point', $columns['location']->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_WGS84, $columns['location']->getSrid());
    }

    public function testAlterTableAlterSpatialColumn(): void
    {
        // Create table with spatial column
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
                    ->setLength(255)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
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

        // Alter spatial column to different type
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
                    ->setLength(255)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('LINESTRING')
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
        $alterSql   = $this->connection->getDatabasePlatform()->getAlterTableSQL($tableDiff);

        // Execute ALTER TABLE
        foreach ($alterSql as $sql) {
            $this->connection->executeStatement($sql);
        }

        // Verify spatial column was altered
        $columns = $schemaManager->listTableColumns(self::TABLE_NAME);

        $geom = $columns['geom'];
        self::assertSame(Type::getType(Types::GEOMETRY), $geom->getType());
        self::assertSame('linestring', $geom->getGeometryType());
        self::assertNull($geom->getSrid());
    }

    public function testAlterTableAlterSpatialColumnWithSrid(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform instanceof MySQL80Platform) {
            self::markTestSkipped('This test requires MySQL 8.0+ for SRID support in column definitions.');
        }

        // Create table with spatial column with SRID
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
                    ->setLength(255)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('geom')
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

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        // Alter spatial column to different type and SRID
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
                    ->setLength(255)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('geom')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('LINESTRING')
                    ->setSrid(SpatialReferenceSystems::SRID_UTM_33N)
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
        $alterSql   = $this->connection->getDatabasePlatform()->getAlterTableSQL($tableDiff);

        // Execute ALTER TABLE
        foreach ($alterSql as $sql) {
            $this->connection->executeStatement($sql);
        }

        // Verify spatial column was altered
        $columns = $schemaManager->listTableColumns(self::TABLE_NAME);

        $geom = $columns['geom'];
        self::assertSame(Type::getType(Types::GEOMETRY), $geom->getType());
        self::assertSame('linestring', $geom->getGeometryType());
        self::assertSame(SpatialReferenceSystems::SRID_UTM_33N, $geom->getSrid());
    }

    public function testDifferentGeometryTypes(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE_NAME)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('point_col')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('linestring_col')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('LINESTRING')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('polygon_col')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POLYGON')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('multipoint_col')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('MULTIPOINT')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('multilinestring_col')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('MULTILINESTRING')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('multipolygon_col')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('MULTIPOLYGON')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('geometrycollection_col')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('GEOMETRYCOLLECTION')
                    ->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE_NAME);

        self::assertSame('point', $columns['point_col']->getGeometryType());
        self::assertSame('linestring', $columns['linestring_col']->getGeometryType());
        self::assertSame('polygon', $columns['polygon_col']->getGeometryType());
        self::assertSame('multipoint', $columns['multipoint_col']->getGeometryType());
        self::assertSame('multilinestring', $columns['multilinestring_col']->getGeometryType());
        self::assertSame('multipolygon', $columns['multipolygon_col']->getGeometryType());
        self::assertSame('geometrycollection', $columns['geometrycollection_col']->getGeometryType());
    }
}
