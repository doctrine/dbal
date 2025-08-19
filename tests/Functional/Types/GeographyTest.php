<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\SpatialTestCase;
use Doctrine\DBAL\Tests\SpatialReferenceSystems;
use Doctrine\DBAL\Types\Types;
use Throwable;

/**
 * Tests for GEOGRAPHY type data operations and conversions.
 * GEOGRAPHY is only supported on PostgreSQL with PostGIS extension.
 */
class GeographyTest extends SpatialTestCase
{
    private const TABLE_NAME = 'geography_test';

    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            return;
        }

        self::markTestSkipped('GEOGRAPHY type is only supported on PostgreSQL with PostGIS.');
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

    public function testGeographyDataConversion(): void
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
                    ->setTypeName(Types::GEOGRAPHY)
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
            ['name' => 'London', 'location' => '{"type":"Point","coordinates":[-0.1276,51.5074]}'],
            ['name' => 'Paris', 'location' => '{"type":"Point","coordinates":[2.3522,48.8566]}'],
            ['name' => 'Berlin', 'location' => '{"type":"Point","coordinates":[13.4050,52.5200]}'],
        ];

        foreach ($locations as $location) {
            $this->connection->insert(self::TABLE_NAME, $location, [
                'name' => Types::STRING,
                'location' => Types::GEOGRAPHY,
            ]);
        }

        // Retrieve and verify data conversion
        $platform = $this->connection->getDatabasePlatform();

        $geographyAsGeoJSON = $platform->getGeographyAsGeoJSONSQL('location');

        $results = $this->connection->createQueryBuilder()
            ->select('name', $geographyAsGeoJSON . ' as location')
            ->from(self::TABLE_NAME)
            ->orderBy('name')
            ->executeQuery()
            ->fetchAllAssociative();

        self::assertCount(3, $results);
        self::assertSame('Berlin', $results[0]['name']);
        self::assertStringContainsString('"coordinates":[13.405,52.52]', $results[0]['location']);
    }

    public function testGeographyWithInvalidSRID(): void
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

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Only lon/lat coordinate systems are supported in geography');

        // UTM Zone 33N coordinates - should fail because GEOGRAPHY requires spherical coordinates
        $utmGeoJSON = '{"type":"Point","coordinates":[551490,5181640],'
            . '"crs":{"type":"name","properties":{"name":"EPSG:' . SpatialReferenceSystems::SRID_UTM_33N . '"}}}';

        $this->connection->insert(
            self::TABLE_NAME,
            ['location' => $utmGeoJSON], // UTM Zone 33N - should fail
            ['location' => Types::GEOGRAPHY],
        );
    }

    public function testGeographyNullValues(): void
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
                    ->setTypeName(Types::GEOGRAPHY)
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

        $this->connection->insert(self::TABLE_NAME, [
            'name' => 'Unknown Location',
            'location' => null,
        ], [
            'name' => Types::STRING,
            'location' => Types::GEOGRAPHY,
        ]);

        $platform = $this->connection->getDatabasePlatform();

        $geographyAsGeoJSON = $platform->getGeographyAsGeoJSONSQL('location');

        $results = $this->connection->createQueryBuilder()
            ->select('name', $geographyAsGeoJSON . ' AS location')
            ->from(self::TABLE_NAME)
            ->orderBy('name')
            ->executeQuery()
            ->fetchAllAssociative();

        self::assertCount(1, $results);
        self::assertSame('Unknown Location', $results[0]['name']);
        self::assertNull($results[0]['location']);
    }
}
