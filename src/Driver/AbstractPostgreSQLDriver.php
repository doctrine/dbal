<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\PostgreSQL\ExceptionConverter;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\ServerVersionProvider;

/**
 * Abstract base implementation of the {@see Driver} interface for PostgreSQL based drivers.
 */
abstract class AbstractPostgreSQLDriver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): PostgreSQLPlatform
    {
        return new PostgreSQLPlatform();
    }

    public function getExceptionConverter(): ExceptionConverterInterface
    {
        return new ExceptionConverter();
    }
}
