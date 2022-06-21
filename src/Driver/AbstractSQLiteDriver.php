<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\SQLite\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\SqliteSchemaManager;
use Doctrine\DBAL\ServerVersionProvider;
use Doctrine\Deprecations\Deprecation;

use function assert;

/**
 * Abstract base implementation of the {@see Driver} interface for SQLite based drivers.
 */
abstract class AbstractSQLiteDriver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): SqlitePlatform
    {
        return new SqlitePlatform();
    }

    /**
     * @deprecated Use {@link SqlitePlatform::createSchemaManager()} instead.
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): SqliteSchemaManager
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5458',
            'AbstractSQLiteDriver::getSchemaManager() is deprecated.'
                . ' Use SqlitePlatform::createSchemaManager() instead.'
        );

        assert($platform instanceof SqlitePlatform);

        return new SqliteSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }
}
