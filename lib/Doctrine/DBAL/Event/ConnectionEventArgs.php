<?php

namespace Doctrine\DBAL\Event;

use Doctrine\Common\EventArgs;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Deprecations\Deprecation;

/**
 * Event Arguments used when a Driver connection is established inside {@link Connection}.
 */
class ConnectionEventArgs extends EventArgs
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @deprecated Use ConnectionEventArgs::getConnection() and Connection::getDriver() instead.
     *
     * @return Driver
     */
    public function getDriver()
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/3580',
            'ConnectionEventArgs::getDriver() is deprecated, ' .
            'use ConnectionEventArgs::getConnection()->getDriver() instead.'
        );

        return $this->connection->getDriver();
    }

    /**
     * @deprecated Use ConnectionEventArgs::getConnection() and Connection::getDatabasePlatform() instead.
     *
     * @return AbstractPlatform
     */
    public function getDatabasePlatform()
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/3580',
            'ConnectionEventArgs::getDatabasePlatform() is deprecated, ' .
            'use ConnectionEventArgs::getConnection()->getDatabasePlatform() instead.'
        );

        return $this->connection->getDatabasePlatform();
    }

    /**
     * @deprecated Use ConnectionEventArgs::getConnection() and Connection::getSchemaManager() instead.
     *
     * @return AbstractSchemaManager
     */
    public function getSchemaManager()
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/3580',
            'ConnectionEventArgs::getSchemaManager() is deprecated, ' .
            'use ConnectionEventArgs::getConnection()->getSchemaManager() instead.'
        );

        return $this->connection->getSchemaManager();
    }
}
