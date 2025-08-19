<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;

/**
 * Base class for spatial tests that require PostGIS extension.
 */
abstract class SpatialTestCase extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestSkipped('PostGIS is only available as an extension in PostgreSQL.');
        }

        $this->requiresPostGIS();
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
