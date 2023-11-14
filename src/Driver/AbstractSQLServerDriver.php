<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\SQLSrv\ExceptionConverter;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\ServerVersionProvider;

/**
 * Abstract base implementation of the {@see Driver} interface for Microsoft SQL Server based drivers.
 */
abstract class AbstractSQLServerDriver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): SQLServerPlatform
    {
        return new SQLServerPlatform();
    }

    public function getExceptionConverter(): ExceptionConverterInterface
    {
        return new ExceptionConverter();
    }
}
