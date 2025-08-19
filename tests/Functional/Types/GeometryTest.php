<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\SpatialTestCase;
use Doctrine\DBAL\Tests\SpatialReferenceSystems;
use Doctrine\DBAL\Types\Types;
use Throwable;

/**
 * Tests for GEOMETRY type data operations and conversions.
 */
class GeometryTest extends SpatialTestCase
{
    private const TABLE_NAME = 'geometry_test';

    protected function tearDown(): void
    {
        parent::tearDown();

        $schemaManager = $this->connection->createSchemaManager();
        if (! $schemaManager->tablesExist([self::TABLE_NAME])) {
            return;
        }

        $schemaManager->dropTable(self::TABLE_NAME);
    }

    public function testGeometryDataConversion(): void
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
                    ->setUnquotedName('location')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
                    ->setSrid(SpatialReferenceSystems::SRID_WGS84)
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

        $locations = [
            ['name' => 'San Francisco', 'location' => '{"type":"Point","coordinates":[-122.4194,37.7749]}'],
            ['name' => 'Los Angeles', 'location' => '{"type":"Point","coordinates":[-118.2437,34.0522]}'],
            ['name' => 'New York', 'location' => '{"type":"Point","coordinates":[-74.0060,40.7128]}'],
        ];

        foreach ($locations as $location) {
            $this->connection->insert(self::TABLE_NAME, $location, [
                'name' => Types::STRING,
                'location' => Types::GEOMETRY,
            ]);
        }

        // Retrieve and verify data conversion
        $platform = $this->connection->getDatabasePlatform();

        $geometryAsGeoJSON = $platform->getGeometryAsGeoJSONSQL('location');

        $results = $this->connection->createQueryBuilder()
            ->select('name', $geometryAsGeoJSON . ' as location')
            ->from(self::TABLE_NAME)
            ->orderBy('name')
            ->executeQuery()
            ->fetchAllAssociative();

        self::assertCount(3, $results);
        self::assertSame('Los Angeles', $results[0]['name']);
        // Verify coordinates are present (format may vary by database - MySQL includes spaces and bbox)
        self::assertStringContainsString('"coordinates"', $results[0]['location']);
        self::assertStringContainsString('-118.2437', $results[0]['location']);
        self::assertStringContainsString('34.0522', $results[0]['location']);
    }

    public function testGeometryWithDifferentSRIDs(): void
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
                    ->setUnquotedName('wgs84_point')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
                    ->setSrid(SpatialReferenceSystems::SRID_WGS84)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('utm_point')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
                    ->setSrid(SpatialReferenceSystems::SRID_UTM_33N)
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

        // Insert points with different SRIDs
        $wgs84GeoJSON = '{"type":"Point","coordinates":[-122.4194,37.7749],'
            . '"crs":{"type":"name","properties":{"name":"EPSG:' . SpatialReferenceSystems::SRID_WGS84 . '"}}}';
        $utmGeoJSON   = '{"type":"Point","coordinates":[551490,5181640],'
            . '"crs":{"type":"name","properties":{"name":"EPSG:' . SpatialReferenceSystems::SRID_UTM_33N . '"}}}';

        $this->connection->insert(self::TABLE_NAME, [
            'wgs84_point' => $wgs84GeoJSON,
            'utm_point' => $utmGeoJSON,
        ], [
            'wgs84_point' => Types::GEOMETRY,
            'utm_point' => Types::GEOMETRY,
        ]);

        // Verify SRID preservation in data operations
        $result = $this->connection->createQueryBuilder()
            ->select('ST_SRID(wgs84_point) as wgs84_srid', 'ST_SRID(utm_point) as utm_srid')
            ->from(self::TABLE_NAME)
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($result);
        self::assertEquals(SpatialReferenceSystems::SRID_WGS84, $result['wgs84_srid']);
        self::assertEquals(SpatialReferenceSystems::SRID_UTM_33N, $result['utm_srid']);
    }

    public function testGeometryWithInvalidSRID(): void
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

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Geometry SRID (32633) does not match column SRID (4326)');

        // Wrong SRID (UTM 33N instead of WGS84) - should fail
        $wrongSridGeoJSON = '{"type":"Point","coordinates":[551490,5181640],'
            . '"crs":{"type":"name","properties":{"name":"EPSG:' . SpatialReferenceSystems::SRID_UTM_33N . '"}}}';

        $this->connection->insert(
            self::TABLE_NAME,
            ['location' => $wrongSridGeoJSON], // Wrong SRID - should fail
            ['location' => Types::GEOMETRY],
        );
    }

    public function testGeometryNullValues(): void
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
                    ->setUnquotedName('location')
                    ->setTypeName(Types::GEOMETRY)
                    ->setGeometryType('POINT')
                    ->setNotNull(false)
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

        // Insert row with null geometry
        $this->connection->insert(self::TABLE_NAME, [
            'name' => 'Unknown Location',
            'location' => null,
        ], [
            'name' => Types::STRING,
            'location' => Types::GEOMETRY,
        ]);

        $platform = $this->connection->getDatabasePlatform();

        $geometryAsGeoJSON = $platform->getGeometryAsGeoJSONSQL('location');

        $results = $this->connection->createQueryBuilder()
            ->select('name', $geometryAsGeoJSON . ' AS location')
            ->from(self::TABLE_NAME)
            ->orderBy('name')
            ->executeQuery()
            ->fetchAllAssociative();

        self::assertCount(1, $results);
        self::assertSame('Unknown Location', $results[0]['name']);
        self::assertNull($results[0]['location']);
    }
}
