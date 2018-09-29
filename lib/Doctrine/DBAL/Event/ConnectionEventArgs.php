<?php

namespace Doctrine\DBAL\Event;

use Doctrine\Common\EventArgs;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

/**
 * Event Arguments used when a Driver connection is established inside Doctrine\DBAL\Connection.
 *
 * @link   www.doctrine-project.org
 */
class ConnectionEventArgs extends EventArgs
{
    /** @var Connection */
    private $_connection;

    public function __construct(Connection $connection)
    {
        $this->_connection = $connection;
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * @return Driver
     */
    public function getDriver()
    {
        return $this->_connection->getDriver();
    }

    /**
     * @return AbstractPlatform
     */
    public function getDatabasePlatform()
    {
        return $this->_connection->getDatabasePlatform();
    }

    /**
     * @return AbstractSchemaManager
     */
    public function getSchemaManager()
    {
        return $this->_connection->getSchemaManager();
    }
}
