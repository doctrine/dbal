<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\PostgreSQL\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Exception\InvalidPlatformVersion;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;
use Doctrine\DBAL\ServerVersionProvider;

use function assert;
use function preg_match;
use function version_compare;

/**
 * Abstract base implementation of the {@link Driver} interface for PostgreSQL based drivers.
 */
abstract class AbstractPostgreSQLDriver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): PostgreSQLPlatform
    {
        $version = $versionProvider->getServerVersion();

        if (preg_match('/^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+))?)?/', $version, $versionParts) === 0) {
            throw InvalidPlatformVersion::new(
                $version,
                '<major_version>.<minor_version>.<patch_version>'
            );
        }

        $majorVersion = $versionParts['major'];
        $minorVersion = $versionParts['minor'] ?? 0;
        $patchVersion = $versionParts['patch'] ?? 0;
        $version      = $majorVersion . '.' . $minorVersion . '.' . $patchVersion;

        if (version_compare($version, '10.0', '>=')) {
            return new PostgreSQL100Platform();
        }

        return new PostgreSQLPlatform();
    }

    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): PostgreSQLSchemaManager
    {
        assert($platform instanceof PostgreSQLPlatform);

        return new PostgreSQLSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }
}
