<?php

namespace Doctrine\DBAL\Connections\Connector;

use Doctrine\DBAL\Driver;

/**
 * Interface Connector
 */
interface Connector
{
    /**
     * Connects to a specific connection.
     *
     * @param string $connectionName
     *
     * @return Driver\Connection
     */
    public function connectTo($connectionName);
}
