<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\IBMDB2\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Schema\DB2SchemaManager;
use Doctrine\DBAL\ServerVersionProvider;

use function assert;

/**
 * Abstract base implementation of the {@see Driver} interface for IBM DB2 based drivers.
 */
abstract class AbstractDB2Driver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): DB2Platform
    {
        return new DB2Platform();
    }

    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): DB2SchemaManager
    {
        assert($platform instanceof DB2Platform);

        return new DB2SchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }
}
