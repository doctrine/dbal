<?php

namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

/**
 * Driver interface.
 * Interface that all DBAL drivers must implement.
 */
interface Driver
{
    /**
     * Attempts to create a connection with the database.
     *
     * @param mixed[] $params All connection parameters.
     *
     * @return DriverConnection The database connection.
     *
     * @throws Exception
     */
    public function connect(array $params);

    /**
     * Gets the DatabasePlatform instance that provides all the metadata about
     * the platform this driver connects to.
     *
     * @return AbstractPlatform The database platform.
     */
    public function getDatabasePlatform();

    /**
     * Gets the SchemaManager that can be used to inspect and change the underlying
     * database schema of the platform this driver connects to.
     *
     * @return AbstractSchemaManager
     */
    public function getSchemaManager(Connection $conn);

    /**
     * Converts a given driver-level exception into a DBAL-level driver exception.
     *
     * Implementors should use the vendor-specific error code and SQLSTATE of the exception
     * and instantiate the most appropriate specialized {@link DriverException} subclass.
     *
     * @param string    $message   The exception message to use.
     * @param Exception $exception The driver exception to convert.
     *
     * @return DriverException An instance of {@link DriverException} or one of its subclasses.
     */
    public function convertException($message, Exception $exception);
}
