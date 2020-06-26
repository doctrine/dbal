<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Exception as TheDriverException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;

/**
 * Abstract base implementation of the {@link Doctrine\DBAL\Driver} interface for Microsoft SQL Server based drivers.
 */
abstract class AbstractSQLServerDriver implements Driver
{
    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new SQLServer2012Platform();
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(Connection $conn)
    {
        return new SQLServerSchemaManager($conn);
    }

    /**
     * @param string $message
     *
     * @return DriverException
     */
    public function convertException($message, TheDriverException $exception)
    {
        return new DriverException($message, $exception);
    }
}
