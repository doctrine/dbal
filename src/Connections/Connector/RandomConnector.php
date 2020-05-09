<?php

namespace Doctrine\DBAL\Connections\Connector;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use function array_rand;

/**
 * Random connector is connecting to a random slave connection
 */
class RandomConnector extends AbstractMasterSlaveConnector implements Connector
{
    public const STRATEGY = 'random';

    /**
     * Connects to a specific connection.
     *
     * @param string $connectionName
     *
     * @return DriverConnection
     */
    public function connectTo($connectionName)
    {
        $params = $this->_params;

        $config = $params['master'];

        if ($connectionName !== 'master') {
            $config = $params['slaves'][array_rand($params['slaves'])];
        }

        return $this->connectWithParams($config);
    }
}
