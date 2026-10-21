<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

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
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\BoundingBox;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;
use Doctrine\DBAL\Tests\Functional\SpatialTestCase;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\GeometryType;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_map;
use function preg_grep;
use function preg_match;
use function sprintf;
use function str_contains;
use function strtolower;

final class GeometryTest extends SpatialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    /**
     * The conversions of the value and of its selection reference the parameter once.
     */
    public function testSelectedParameter(): void
    {
        $value = $this->connection->fetchOne(
            $this->connection->getDatabasePlatform()->getDummySelectSQL($this->connection->convertToPHPValueSQL(
                $this->connection->convertToDatabaseValueSQL('?', Types::GEOMETRY),
                Types::GEOMETRY,
            )),
            [Geometry::fromWkt('SRID=4326;LINESTRING(1 2,3 4)')],
            [Types::GEOMETRY],
        );

        self::assertSame(
            'SRID=4326;LINESTRING(1 2,3 4)',
            $this->connection->convertToPHPValue($value, Types::GEOMETRY)?->asEwkt(),
        );
    }

    /**
     * PostGIS rejects a new SRID, dimension or kind for the values of a populated column without conversion.
     */
    public function testChangeOfPopulatedColumnOnPostgreSQL(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestSkipped('This test covers the conversion of PostGIS values.');
        }

        $table = $this->createTable(Column::editor()
            ->setUnquotedName('geom')
            ->setTypeName(Types::GEOMETRY)
            ->setGeometryType(GeometryKind::POINT)
            ->create());

        $this->connection->insert(
            'geometry_test',
            ['id' => 1, 'geom' => Geometry::point(1, 2)],
            ['geom' => Types::GEOMETRY],
        );

        $schemaManager = $this->connection->createSchemaManager();

        $expected = Geometry::fromWkt('SRID=4326;MULTIPOINT Z((1 2 0))')->asEwkt();

        // the geography changes the kind and the dimension too, then the geometry changes the type only
        foreach ([Types::GEOGRAPHY, Types::GEOMETRY] as $typeName) {
            $newTable = $table->edit()
                ->modifyColumnByUnquotedName('geom', static function (ColumnEditor $editor) use ($typeName): void {
                    $editor->setTypeName($typeName)
                        ->setGeometryType(GeometryKind::MULTIPOINT)
                        ->setCoordinateDimension(Dimension::XYZ)
                        ->setSrid(4326);
                })
                ->create();

            $schemaManager->alterTable($schemaManager->createComparator()->compareTables($table, $newTable));
            $table = $newTable;

            self::assertSame(
                $expected,
                $this->connection->convertToPHPValue($this->fetchGeometry(1), Types::GEOMETRY)?->asEwkt(),
                $typeName,
            );
        }
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

        if (
            $platform instanceof PostgreSQLPlatform
            || $platform instanceof MySQL80Platform
            || $platform instanceof OraclePlatform
        ) {
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
        $this->requireSpatialIndexes();

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

        // the tables and sequences storing the spatial indexes of Oracle are not introspected
        if (! $this->connection->getDatabasePlatform() instanceof OraclePlatform) {
            return;
        }

        $names = [
            ...array_map(
                static fn (OptionallyQualifiedName $name): string => $name->toString(),
                $schemaManager->introspectTableNames(),
            ),
            ...array_map(static fn (Sequence $sequence): string => $sequence->getName(), [
                ...$schemaManager->introspectSequences(),
                ...$schemaManager->listSequences(),
            ]),
            ...$schemaManager->listTableNames(),
        ];

        self::assertSame([], preg_grep('/^"?MDR[ST]_/i', $names));
    }

    /**
     * The spatial indexes created with another access method or operator class are introspected as spatial indexes,
     * so that the comparator keeps them.
     */
    #[DataProvider('postgreSQLSpatialIndexProvider')]
    public function testOtherSpatialIndexesOfPostgreSQL(string $method, string $operatorClass): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestSkipped('This test covers the indexes of PostGIS.');
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
                ->create(),
        );

        $this->connection->executeStatement('DROP INDEX geometry_test_location');
        $this->connection->executeStatement(sprintf(
            'CREATE INDEX geometry_test_location ON geometry_test USING %s (location %s)',
            $method,
            $operatorClass,
        ));

        $schemaManager     = $this->connection->createSchemaManager();
        $introspectedTable = $schemaManager->introspectTableByUnquotedName('geometry_test');

        self::assertSame(IndexType::SPATIAL, $introspectedTable->getIndex('geometry_test_location')->getType());
        self::assertTrue($schemaManager->createComparator()->compareTables($introspectedTable, $table)->isEmpty());
    }

    /** @return iterable<string, array{string, string}> */
    public static function postgreSQLSpatialIndexProvider(): iterable
    {
        yield 'n-dimensional GiST' => ['GIST', 'gist_geometry_ops_nd'];
        yield 'SP-GiST' => ['SPGIST', 'spgist_geometry_ops_2d'];
        yield 'BRIN' => ['BRIN', 'brin_geometry_inclusion_ops_2d'];
    }

    public function testSpatialIndexBoundingBox(): void
    {
        $this->requireSpatialIndexes();

        $this->createTable(
            [
                Index::editor()
                    ->setUnquotedName('geometry_test_location')
                    ->setUnquotedColumnNames('location')
                    ->setType(IndexType::SPATIAL)
                    ->setBoundingBox(new BoundingBox(-1000.5, 0, 1000, 500))
                    ->create(),
            ],
            Column::editor()
                ->setUnquotedName('location')
                ->setTypeName(Types::GEOMETRY)
                ->setGeometryType(GeometryKind::POINT)
                ->setSrid(3857)
                ->create(),
        );

        $schemaManager = $this->connection->createSchemaManager();

        $indexes = [
            $schemaManager->introspectTableByUnquotedName('geometry_test')->getIndex('geometry_test_location'),
            $schemaManager->listTableIndexes('geometry_test')['geometry_test_location'],
        ];

        foreach ($indexes as $index) {
            self::assertSame(IndexType::SPATIAL, $index->getType());

            // only SQL Server stores the bounding box of spatial indexes
            if ($this->connection->getDatabasePlatform() instanceof SQLServerPlatform) {
                self::assertEquals(new BoundingBox(-1000.5, 0, 1000, 500), $index->getBoundingBox());
            } else {
                self::assertNull($index->getBoundingBox());
            }
        }
    }

    public function testSpatialIndexOnUnsupportedPlatforms(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform instanceof SQLitePlatform && ! $platform instanceof DB2Platform) {
            self::markTestSkipped('This test covers the platforms that do not support spatial indexes.');
        }

        $indexes = [
            Index::editor()
                ->setUnquotedName('geometry_test_location')
                ->setUnquotedColumnNames('location')
                ->setType(IndexType::SPATIAL)
                ->create(),
        ];

        $column = Column::editor()
            ->setUnquotedName('location')
            ->setTypeName(Types::GEOMETRY)
            ->create();

        if ($platform instanceof DB2Platform) {
            $this->expectException(NotSupported::class);
        }

        $table = $this->createTable($indexes, $column);

        // SQLite creates a regular index: the spatial indexes of SpatiaLite are only used by explicit queries
        $schemaManager     = $this->connection->createSchemaManager();
        $introspectedTable = $schemaManager->introspectTableByUnquotedName('geometry_test');

        self::assertSame(IndexType::REGULAR, $introspectedTable->getIndex('geometry_test_location')->getType());
        self::assertTrue($schemaManager->createComparator()->compareTables($introspectedTable, $table)->isEmpty());
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

    /** @param non-empty-string $ewkt */
    #[DataProvider('curveAndSurfaceProvider')]
    public function testCurvesAndSurfaces(string $ewkt, GeometryKind $kind): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $geometry = Geometry::fromWkt($ewkt);

        // only PostGIS supports the curves and surfaces of ISO 13249-3
        if (! $platform instanceof PostgreSQLPlatform) {
            try {
                $platform->getGeometryTypeDeclarationSQL(['geometryType' => $kind]);
                self::fail('The declaration of the column must be rejected.');
            } catch (InvalidGeometry) {
            }

            if ($platform instanceof SQLitePlatform) {
                $this->requireSpatiaLite();
            }

            $this->createTable(Column::editor()
                ->setUnquotedName('geom')
                ->setTypeName(Types::GEOMETRY)
                ->setNotNull(false)
                ->create());

            $this->expectException(ConversionException::class);

            $this->connection->insert('geometry_test', ['id' => 1, 'geom' => $geometry], ['geom' => Types::GEOMETRY]);
        }

        $table = $this->createTable(
            Column::editor()
                ->setUnquotedName('geom')
                ->setTypeName(Types::GEOMETRY)
                ->setNotNull(false)
                ->create(),
            Column::editor()
                ->setUnquotedName('typed')
                ->setTypeName(Types::GEOMETRY)
                ->setGeometryType($kind)
                ->setNotNull(false)
                ->create(),
        );

        $this->connection->insert(
            'geometry_test',
            ['id' => 1, 'geom' => $geometry, 'typed' => $geometry],
            ['geom' => Types::GEOMETRY, 'typed' => Types::GEOMETRY],
        );

        $value = $this->connection->convertToPHPValue($this->fetchGeometry(1), Types::GEOMETRY);

        self::assertInstanceOf(Geometry::class, $value);
        self::assertTrue($value->equals($geometry), $value->asEwkt());

        $schemaManager     = $this->connection->createSchemaManager();
        $introspectedTable = $schemaManager->introspectTableByUnquotedName('geometry_test');

        self::assertSame($kind, $introspectedTable->getColumn('typed')->getGeometryType());
        self::assertTrue($schemaManager->createComparator()->compareTables($introspectedTable, $table)->isEmpty());
    }

    /** @return iterable<string, array{non-empty-string, GeometryKind}> */
    public static function curveAndSurfaceProvider(): iterable
    {
        yield 'circular string' => ['CIRCULARSTRING(0 0,1 1,2 0)', GeometryKind::CIRCULARSTRING];
        yield 'compound curve' => [
            'COMPOUNDCURVE((0 0,1 0),CIRCULARSTRING(1 0,2 1,3 0))',
            GeometryKind::COMPOUNDCURVE,
        ];

        yield 'curve polygon' => [
            'CURVEPOLYGON(CIRCULARSTRING(0 0,4 0,4 4,0 4,0 0),(1 1,3 3,3 1,1 1))',
            GeometryKind::CURVEPOLYGON,
        ];

        yield 'multi-curve' => ['MULTICURVE((0 0,5 5),CIRCULARSTRING(4 0,4 4,8 4))', GeometryKind::MULTICURVE];
        yield 'multi-surface' => [
            'MULTISURFACE(CURVEPOLYGON(CIRCULARSTRING(0 0,4 0,4 4,0 4,0 0)),((10 10,14 12,11 10,10 10)))',
            GeometryKind::MULTISURFACE,
        ];

        yield 'polyhedral surface' => [
            'POLYHEDRALSURFACE(((0 0,0 1,1 1,0 0)),((0 0,1 1,1 0,0 0)))',
            GeometryKind::POLYHEDRALSURFACE,
        ];

        yield 'TIN' => ['TIN(((0 0,0 1,1 1,0 0)),((0 0,1 1,1 0,0 0)))', GeometryKind::TIN];
        yield 'triangle' => ['TRIANGLE((0 0,0 1,1 1,0 0))', GeometryKind::TRIANGLE];
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

    private function requireSpatialIndexes(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform instanceof SQLitePlatform && ! $platform instanceof DB2Platform) {
            return;
        }

        self::markTestSkipped(sprintf('%s does not support spatial indexes.', $platform::class));
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
