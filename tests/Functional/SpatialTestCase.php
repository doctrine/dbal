<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;

/**
 * Base class for spatial tests.
 *
 * Supports both PostgreSQL (with PostGIS extension) and MySQL (native spatial support).
 */
abstract class SpatialTestCase extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            $this->requiresPostGIS();

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            return;
        }

        self::markTestSkipped('Spatial types are only supported on PostgreSQL (PostGIS) and MySQL platforms.');
    }

    private function requiresPostGIS(): void
    {
        $installed = $this->connection->fetchOne('
            SELECT extname
            FROM pg_extension
            WHERE extname = \'postgis\'
        ');

        if ($installed !== false) {
            return;
        }

        $available = $this->connection->fetchOne('
            SELECT name
            FROM pg_available_extensions
            WHERE name = \'postgis\'
        ');

        if ($available !== false) {
            self::fail(
                'PostGIS extension is available but not installed. ' .
                'Please install PostGIS: CREATE EXTENSION postgis;',
            );
        } else {
            self::markTestSkipped(
                'PostGIS extension is not available in this PostgreSQL installation. ' .
                'Skipping spatial tests.',
            );
        }
    }
}
