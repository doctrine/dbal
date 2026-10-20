<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
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
use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\GeometryType;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function preg_match;
use function sprintf;
use function str_contains;
use function strtolower;

final class GeometryTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        try {
            $platform->getGeometryTypeDeclarationSQL([]);
        } catch (NotSupported) {
            self::markTestSkipped(sprintf('%s does not support spatial types.', $platform::class));
        }

        if (
            $platform instanceof PostgreSQLPlatform
            && $this->connection->fetchOne("SELECT 1 FROM pg_extension WHERE extname = 'postgis'") === false
        ) {
            $this->skipOrFail('This test requires the PostGIS extension.');
        }

        if (
            $platform instanceof DB2Platform
            && $this->connection->fetchOne(
                "SELECT 1 FROM SYSCAT.DATATYPES WHERE TYPESCHEMA = 'SYSIBM' AND TYPENAME = 'ST_GEOMETRY'",
            ) === false
        ) {
            $this->skipOrFail('This test requires Db2 Spatial Analytics.');
        }

        $this->dropTableIfExists('geometry_test');
    }

    #[DataProvider('valueProvider')]
    public function testValueRoundTrip(string $wkt, bool $requiresMeasuresOrElevation, bool $isEmpty): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform && ($requiresMeasuresOrElevation || $isEmpty)) {
            self::markTestSkipped('MySQL and MariaDB only store non-empty two-dimensional geometries.');
        }

        if (($platform instanceof SQLitePlatform || $platform instanceof OraclePlatform) && $isEmpty) {
            self::markTestSkipped('SpatiaLite and Oracle cannot represent empty geometries.');
        }

        if ($platform instanceof SQLServerPlatform && $wkt === 'SRID=4326;POINT EMPTY') {
            self::markTestSkipped('SQL Server cannot build empty points from their WKB.');
        }

        if (
            $platform instanceof DB2Platform
            && ($isEmpty || str_contains($wkt, 'GEOMETRYCOLLECTION') || preg_match('/^SRID=(?!4326;)/', $wkt) === 1)
        ) {
            self::markTestSkipped(
                'Db2 stores neither empty geometries nor geometry collections,'
                    . ' and only predefines the spatial reference systems 0 and 4326 among the tested ones.',
            );
        }

        $this->createTable(Column::editor()
            ->setUnquotedName('geom')
            ->setTypeName(Types::GEOMETRY)
            ->setNotNull(false)
            ->create());

        $geometry = Geometry::fromWkt($wkt);

        $this->connection->insert('geometry_test', ['id' => 1, 'geom' => $geometry], ['geom' => Types::GEOMETRY]);

        $value = $this->connection->convertToPHPValue($this->fetchGeometry(1), Types::GEOMETRY);

        self::assertInstanceOf(Geometry::class, $value);
        self::assertTrue($value->equals($geometry), $value->asEwkt());
    }

    /** @return iterable<string, array{string, bool, bool}> */
    public static function valueProvider(): iterable
    {
        yield 'point' => ['SRID=4326;POINT(2.3522219 48.856614)', false, false];
        yield 'point without SRID' => ['POINT(1 2)', false, false];
        yield 'precise coordinates' => ['SRID=3857;POINT(0.30000000000000004 0.3333333333333333)', false, false];
        yield 'line string' => ['SRID=4326;LINESTRING(0 0,1 1,2 0)', false, false];
        yield 'polygon with hole' => [
            'SRID=2154;POLYGON((0 0,10 0,10 10,0 10,0 0),(1 1,1 2,2 2,2 1,1 1))',
            false,
            false,
        ];

        yield 'multi-point' => ['SRID=4326;MULTIPOINT((0 0),(1 1))', false, false];
        yield 'multi-line string' => ['SRID=4326;MULTILINESTRING((0 0,1 1),(2 2,3 3))', false, false];
        yield 'multi-polygon' => [
            'SRID=4326;MULTIPOLYGON(((0 0,1 0,1 1,0 0)),((5 5,6 5,6 6,5 5)))',
            false,
            false,
        ];

        yield 'geometry collection' => ['SRID=4326;GEOMETRYCOLLECTION(POINT(1 2),LINESTRING(0 0,1 1))', false, false];
        yield 'point Z' => ['SRID=4326;POINT(1 2 3)', true, false];
        yield 'line string M' => ['SRID=4326;LINESTRINGM(0 0 1,1 1 2)', true, false];
        yield 'point ZM' => ['POINT(1 2 3 4)', true, false];
        yield 'empty point' => ['SRID=4326;POINT EMPTY', false, true];
        yield 'empty polygon' => ['POLYGON EMPTY', false, true];
    }

    public function testEmptyGeometryCollection(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (
            $platform instanceof SQLitePlatform
            || $platform instanceof OraclePlatform
            || $platform instanceof DB2Platform
        ) {
            self::markTestSkipped('SpatiaLite, Oracle and Db2 cannot represent empty geometries.');
        }

        $this->createTable(Column::editor()
            ->setUnquotedName('geom')
            ->setTypeName(Types::GEOMETRY)
            ->setNotNull(false)
            ->create());

        $geometry = Geometry::fromWkt('SRID=4326;GEOMETRYCOLLECTION EMPTY');

        $this->connection->insert('geometry_test', ['id' => 1, 'geom' => $geometry], ['geom' => Types::GEOMETRY]);

        $value = $this->connection->convertToPHPValue($this->fetchGeometry(1), Types::GEOMETRY);

        self::assertInstanceOf(Geometry::class, $value);
        self::assertTrue($value->equals($geometry), $value->asEwkt());
    }

    public function testNull(): void
    {
        $this->createTable(Column::editor()
            ->setUnquotedName('geom')
            ->setTypeName(Types::GEOMETRY)
            ->setNotNull(false)
            ->create());

        $this->connection->insert('geometry_test', ['id' => 1, 'geom' => null], ['geom' => Types::GEOMETRY]);

        self::assertNull($this->connection->convertToPHPValue($this->fetchGeometry(1), Types::GEOMETRY));
    }

    public function testUpdateAndQueryBuilder(): void
    {
        $this->createTable(Column::editor()
            ->setUnquotedName('geom')
            ->setTypeName(Types::GEOMETRY)
            ->setNotNull(false)
            ->create());

        $this->connection->insert('geometry_test', ['id' => 1, 'geom' => null], ['geom' => Types::GEOMETRY]);
        $this->connection->update(
            'geometry_test',
            ['geom' => Geometry::point(1, 2, 4326)],
            ['id' => 1],
            ['geom' => Types::GEOMETRY],
        );

        $qb = $this->connection->createQueryBuilder();
        $qb->insert('geometry_test')
            ->values([
                'id' => $qb->createNamedParameter(2, Types::INTEGER),
                'geom' => $qb->createNamedParameter(Geometry::point(3, 4, 4326), Types::GEOMETRY),
            ])
            ->executeStatement();

        self::assertSame(
            'SRID=4326;POINT(1 2)',
            $this->connection->convertToPHPValue($this->fetchGeometry(1), Types::GEOMETRY)?->asEwkt(),
        );
        self::assertSame(
            'SRID=4326;POINT(3 4)',
            $this->connection->convertToPHPValue($this->fetchGeometry(2), Types::GEOMETRY)?->asEwkt(),
        );
    }

    public function testParameterAsFunctionArgument(): void
    {
        $platform  = $this->connection->getDatabasePlatform();
        $predicate = 'ST_Intersects(geom, ' . $this->connection->convertToDatabaseValueSQL('?', Types::GEOMETRY) . ')';

        if ($platform instanceof SQLitePlatform) {
            $this->requireSpatiaLite();

            // the predicates of SpatiaLite return 1, 0 or -1 (error)
            $predicate .= ' = 1';
        } elseif ($platform instanceof DB2Platform) {
            // the predicates of Db2 return integers
            $predicate .= ' = 1';
        } elseif (! $platform instanceof PostgreSQLPlatform && ! $platform instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('This test uses the ST_Intersects() function of PostGIS, MySQL and SpatiaLite.');
        }

        $this->createTable(Column::editor()
            ->setUnquotedName('geom')
            ->setTypeName(Types::GEOMETRY)
            ->create());

        foreach ([1 => Geometry::point(1, 1), 2 => Geometry::point(5, 5)] as $id => $point) {
            $this->connection->insert('geometry_test', ['id' => $id, 'geom' => $point], ['geom' => Types::GEOMETRY]);
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM geometry_test WHERE ' . $predicate,
            [Geometry::fromWkt('POLYGON((0 0,2 0,2 2,0 2,0 0))')],
            [Types::GEOMETRY],
        );

        self::assertEquals([1], $ids);
    }

    public function testSchema(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $columns = [
            Column::editor()
                ->setUnquotedName('any_geometry')
                ->setTypeName(Types::GEOMETRY)
                ->setNotNull(false)
                ->create(),
            Column::editor()
                ->setUnquotedName('point_4326')
                ->setTypeName(Types::GEOMETRY)
                ->setGeometryType(GeometryKind::POINT)
                ->setSrid(4326)
                ->create(),
            Column::editor()
                ->setUnquotedName('polygon')
                ->setTypeName(Types::GEOMETRY)
                ->setGeometryType(GeometryKind::POLYGON)
                ->setNotNull(false)
                ->create(),
            Column::editor()
                ->setUnquotedName('srid_3857')
                ->setTypeName(Types::GEOMETRY)
                ->setSrid(3857)
                ->setNotNull(false)
                ->create(),
        ];

        if ($platform instanceof PostgreSQLPlatform) {
            $columns[] = Column::editor()
                ->setUnquotedName('line_string_z')
                ->setTypeName(Types::GEOMETRY)
                ->setGeometryType(GeometryKind::LINESTRING)
                ->setCoordinateDimension(Dimension::XYZ)
                ->setSrid(4326)
                ->setNotNull(false)
                ->create();
        }

        $table = $this->createTable(...$columns);

        $schemaManager     = $this->connection->createSchemaManager();
        $introspectedTable = $schemaManager->introspectTableByUnquotedName('geometry_test');

        self::assertTrue($schemaManager->createComparator()->compareTables($introspectedTable, $table)->isEmpty());
        self::assertTrue($schemaManager->createComparator()->compareTables($table, $introspectedTable)->isEmpty());

        $column = $introspectedTable->getColumn('point_4326');

        self::assertInstanceOf(GeometryType::class, $column->getType());

        // the kind is only part of the column declaration on these platforms
        if (
            $platform instanceof PostgreSQLPlatform
            || $platform instanceof AbstractMySQLPlatform
            || $platform instanceof SQLitePlatform
            || $platform instanceof DB2Platform
        ) {
            self::assertSame(GeometryKind::POINT, $column->getGeometryType());
        }

        if ($platform instanceof PostgreSQLPlatform || $platform instanceof MySQL80Platform) {
            self::assertSame(4326, $column->getSrid());
        }

        if (! ($platform instanceof PostgreSQLPlatform)) {
            return;
        }

        $column = $introspectedTable->getColumn('line_string_z');

        self::assertSame(GeometryKind::LINESTRING, $column->getGeometryType());
        self::assertSame(Dimension::XYZ, $column->getCoordinateDimension());
        self::assertSame(4326, $column->getSrid());
    }

    public function testSpatialIndex(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform instanceof PostgreSQLPlatform && ! $platform instanceof AbstractMySQLPlatform) {
            self::markTestSkipped(sprintf('Spatial indexes are not supported on %s yet.', $platform::class));
        }

        $table = $this->createTable(
            [
                Index::editor()
                    ->setUnquotedName('geometry_test_location')
                    ->setUnquotedColumnNames('location')
                    ->setType(IndexType::SPATIAL)
                    ->create(),
            ],
            Column::editor()
                ->setUnquotedName('location')
                ->setTypeName(Types::GEOMETRY)
                ->setGeometryType(GeometryKind::POINT)
                ->setSrid(4326)
                ->create(),
        );

        $schemaManager     = $this->connection->createSchemaManager();
        $introspectedTable = $schemaManager->introspectTableByUnquotedName('geometry_test');

        self::assertTrue($schemaManager->createComparator()->compareTables($introspectedTable, $table)->isEmpty());

        $index = $introspectedTable->getIndex('geometry_test_location');

        self::assertSame(IndexType::SPATIAL, $index->getType());

        // the index must not prevent the insertion of large geometries
        $points = [];

        for ($i = 0; $i < 1000; $i++) {
            $points[] = $i . ' ' . $i;
        }

        $this->connection->insert(
            'geometry_test',
            ['id' => 1, 'location' => Geometry::point(1, 2, 4326)],
            ['location' => Types::GEOMETRY],
        );

        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM geometry_test'));
    }

    public function testMariaDBIgnoresTheSrid(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof MariaDBPlatform) {
            self::markTestSkipped('This test requires MariaDB.');
        }

        $table = $this->createTable(Column::editor()
            ->setUnquotedName('geom')
            ->setTypeName(Types::GEOMETRY)
            ->setSrid(4326)
            ->create());

        $schemaManager = $this->connection->createSchemaManager();

        self::assertTrue($schemaManager->createComparator()->compareTables(
            $schemaManager->introspectTableByUnquotedName('geometry_test'),
            $table,
        )->isEmpty());
    }

    public function testMySQLRejectsUnsupportedValues(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('This test requires MySQL or MariaDB.');
        }

        $this->createTable(Column::editor()
            ->setUnquotedName('geom')
            ->setTypeName(Types::GEOMETRY)
            ->setNotNull(false)
            ->create());

        $this->expectException(ConversionException::class);

        $this->connection->insert(
            'geometry_test',
            ['id' => 1, 'geom' => Geometry::fromWkt('POINT Z (1 2 3)')],
            ['geom' => Types::GEOMETRY],
        );
    }

    public function testSpatiaLiteCompatibility(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped('This test requires SQLite.');
        }

        $this->requireSpatiaLite();

        $this->createTable(Column::editor()
            ->setUnquotedName('geom')
            ->setTypeName(Types::GEOMETRY)
            ->create());

        $geometry = Geometry::fromWkt(
            'SRID=4326;POLYGON Z ((0 0 1,10 0 2,10 10 3,0 10 4,0 0 1),(1 1 0,1 2 0,2 2 0,1 1 0))',
        );

        $this->connection->insert('geometry_test', ['id' => 1, 'geom' => $geometry], ['geom' => Types::GEOMETRY]);

        self::assertSame(
            [
                'srid' => 4326,
                'wkt' => 'POLYGON Z((0 0 1, 10 0 2, 10 10 3, 0 10 4, 0 0 1), (1 1 0, 1 2 0, 2 2 0, 1 1 0))',
                'area' => 99.5,
            ],
            $this->connection->fetchAssociative(
                'SELECT ST_SRID(geom) AS srid, ST_AsText(geom) AS wkt, ST_Area(geom) AS area FROM geometry_test',
            ),
        );

        $this->connection->executeStatement(
            "INSERT INTO geometry_test (id, geom) VALUES (2, GeomFromText('POINT(1.5 2.5)', 2154))",
        );

        self::assertSame(
            'SRID=2154;POINT(1.5 2.5)',
            $this->connection->convertToPHPValue($this->fetchGeometry(2), Types::GEOMETRY)?->asEwkt(),
        );
    }

    public function testSpatiaLiteObjectsAreNotIntrospected(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped('This test requires SQLite.');
        }

        $this->requireSpatiaLite();

        // the metadata and spatial index tables would otherwise remain in the database
        $this->markConnectionNotReusable();

        $this->createTable(Column::editor()
            ->setUnquotedName('geom')
            ->setTypeName(Types::GEOMETRY)
            ->setGeometryType(GeometryKind::POINT)
            ->setSrid(4326)
            ->create());

        $this->connection->executeStatement('SELECT InitSpatialMetadata(1)');
        $this->connection->executeStatement(
            "SELECT RecoverGeometryColumn('geometry_test', 'geom', 4326, 'POINT', 'XY')",
        );
        $this->connection->executeStatement("SELECT CreateSpatialIndex('geometry_test', 'geom')");

        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM sqlite_master WHERE name = 'idx_geometry_test_geom'",
            ),
        );

        $schemaManager = $this->connection->createSchemaManager();
        $tableNames    = [];

        foreach ($schemaManager->introspectSchema()->getTables() as $table) {
            $tableNames[] = strtolower($table->getObjectName()->toString());
        }

        self::assertContains('geometry_test', $tableNames);

        foreach ($tableNames as $tableName) {
            self::assertDoesNotMatchRegularExpression(
                '/^(idx_geometry_test_geom|spatial|geometry_columns|views_|virts_|knn|elementary|sql_statements_log)/',
                $tableName,
            );
        }
    }

    public function testOracleGeometries(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof OraclePlatform) {
            self::markTestSkipped('This test requires Oracle.');
        }

        $this->createTable(Column::editor()
            ->setUnquotedName('geom')
            ->setTypeName(Types::GEOMETRY)
            ->create());

        // a point stored in SDO_POINT and an optimized rectangle, as other tools write them
        $this->connection->executeStatement(
            'INSERT INTO geometry_test (id, geom)'
                . ' VALUES (1, SDO_GEOMETRY(2001, 4326, SDO_POINT_TYPE(2.35, 48.85, NULL), NULL, NULL))',
        );
        $this->connection->executeStatement(
            'INSERT INTO geometry_test (id, geom) VALUES (2, SDO_GEOMETRY(2003, NULL, NULL,'
                . ' SDO_ELEM_INFO_ARRAY(1, 1003, 3), SDO_ORDINATE_ARRAY(1, 1, 5, 7)))',
        );

        // Oracle requires counterclockwise exterior rings and clockwise interior rings
        $this->connection->insert(
            'geometry_test',
            ['id' => 3, 'geom' => Geometry::fromWkt('POLYGON((0 0,0 10,10 10,10 0,0 0),(1 1,2 1,2 2,1 2,1 1))')],
            ['geom' => Types::GEOMETRY],
        );

        $expected = [
            1 => 'SRID=4326;POINT(2.35 48.85)',
            2 => 'POLYGON((1 1,5 1,5 7,1 7,1 1))',
            3 => 'POLYGON((0 0,10 0,10 10,0 10,0 0),(1 1,1 2,2 2,2 1,1 1))',
        ];

        foreach ($expected as $id => $ewkt) {
            self::assertSame(
                $ewkt,
                $this->connection->convertToPHPValue($this->fetchGeometry($id), Types::GEOMETRY)?->asEwkt(),
            );
        }

        self::assertSame(
            'TRUE',
            $this->connection->fetchOne(
                'SELECT SDO_GEOM.VALIDATE_GEOMETRY_WITH_CONTEXT(geom, 0.005) FROM geometry_test WHERE id = 3',
            ),
        );
    }

    private function requireSpatiaLite(): void
    {
        try {
            $this->connection->fetchOne('SELECT spatialite_version()');
        } catch (DriverException) {
            $this->skipOrFail('This test requires SpatiaLite.');
        }
    }

    /**
     * The CI jobs of the spatial extensions declare them as required, so that the tests cannot be skipped silently.
     */
    private function skipOrFail(string $message): never
    {
        if (($GLOBALS['spatial'] ?? null) === 'required') {
            self::fail($message);
        }

        self::markTestSkipped($message);
    }

    /** @param list<Index>|Column $indexesOrColumn */
    private function createTable(array|Column $indexesOrColumn, Column ...$columns): Table
    {
        $indexes = [];

        if ($indexesOrColumn instanceof Column) {
            $columns = [$indexesOrColumn, ...$columns];
        } else {
            $indexes = $indexesOrColumn;
        }

        $table = Table::editor()
            ->setUnquotedName('geometry_test')
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

    private function fetchGeometry(int $id): mixed
    {
        return $this->connection->fetchOne(
            sprintf(
                'SELECT %s FROM geometry_test WHERE id = ?',
                $this->connection->convertToPHPValueSQL('geom', Types::GEOMETRY),
            ),
            [$id],
        );
    }
}
