<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function sprintf;

/**
 * Skips the tests on the platforms whose spatial extension is not available.
 */
abstract class SpatialTestCase extends FunctionalTestCase
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
            self::markTestSkipped('This test requires the PostGIS extension.');
        }

        if (
            ! $platform instanceof DB2Platform
            || $this->connection->fetchOne(
                "SELECT 1 FROM SYSCAT.DATATYPES WHERE TYPESCHEMA = 'SYSIBM' AND TYPENAME = 'ST_GEOMETRY'",
            ) !== false
        ) {
            return;
        }

        self::markTestSkipped('This test requires Db2 Spatial Analytics.');
    }

    /**
     * SQLite stores geometries without SpatiaLite, which spatial functions require.
     */
    protected function requireSpatiaLite(): void
    {
        try {
            $this->connection->fetchOne('SELECT spatialite_version()');
        } catch (DriverException) {
            self::markTestSkipped('This test requires SpatiaLite.');
        }
    }
}
