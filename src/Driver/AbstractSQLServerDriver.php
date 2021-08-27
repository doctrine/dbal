<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\SQLSrv\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;

use function assert;

/**
 * Abstract base implementation of the {@link Driver} interface for Microsoft SQL Server based drivers.
 */
abstract class AbstractSQLServerDriver implements Driver
{
    public function getDatabasePlatform(): AbstractPlatform
    {
        return new SQLServerPlatform();
    }

    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): AbstractSchemaManager
    {
        assert($platform instanceof SQLServerPlatform);

        return new SQLServerSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverterInterface
    {
        return new ExceptionConverter();
    }
}
