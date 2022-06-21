<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\PostgreSQL\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;
use Doctrine\DBAL\ServerVersionProvider;
use Doctrine\Deprecations\Deprecation;

use function assert;

/**
 * Abstract base implementation of the {@see Driver} interface for PostgreSQL based drivers.
 */
abstract class AbstractPostgreSQLDriver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): PostgreSQLPlatform
    {
        return new PostgreSQLPlatform();
    }

    /**
     * @deprecated Use {@link PostgreSQLPlatform::createSchemaManager()} instead.
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): PostgreSQLSchemaManager
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5458',
            'AbstractPostgreSQLDriver::getSchemaManager() is deprecated.'
                . ' Use PostgreSQLPlatform::createSchemaManager() instead.'
        );

        assert($platform instanceof PostgreSQLPlatform);

        return new PostgreSQLSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }
}
