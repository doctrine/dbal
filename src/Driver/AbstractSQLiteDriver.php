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

    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): SqliteSchemaManager
    {
        assert($platform instanceof SqlitePlatform);

        return new SqliteSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }
}
