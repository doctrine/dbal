<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\SQLite\ExceptionConverter;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\ServerVersionProvider;

/**
 * Abstract base implementation of the {@see Driver} interface for SQLite based drivers.
 */
abstract class AbstractSQLiteDriver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): SqlitePlatform
    {
        return new SqlitePlatform();
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }
}
