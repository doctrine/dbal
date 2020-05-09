<?php

namespace Doctrine\DBAL\Connections\Connector;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Exception\ConnectionException;
use Exception;
use function array_pop;
use function shuffle;

/**
 * HighAvailabilityConnector is connection to the first random slave connection that is available
 */
class HighAvailabilityConnector extends AbstractMasterSlaveConnector implements Connector
{
    public const STRATEGY = 'high_availability';

    /**
     * Connects to a specific connection.
     *
     * @param string $connectionName
     *
     * @return Driver\Connection
     *
     * @throws ConnectionException
     */
    public function connectTo($connectionName)
    {
        return $connectionName === 'master' ?
            $this->connectToMaster() :
            $this->connectToSlave();
    }

    /**
     * Connets to a master
     *
     * @return Driver\Connection
     */
    private function connectToMaster()
    {
        return $this->connectWithParams($this->_params['master']);
    }

    /**
     * Connets to a random slave and tries others until if found a server that is alive
     *
     * @return Driver\Connection
     *
     * @throws ConnectionException
     */
    private function connectToSlave()
    {
        $params = $this->_params;

        shuffle($params['slaves']);

        while ($config = array_pop($params['slaves'])) {
            try {
                return $this->connectWithParams($config);
            } catch (ConnectionException $e) {
                // try next one
            }
        }

        $previous = new /** @psalm-immutable */ class() extends Exception implements DriverException {
            public function getErrorCode()
            {
                return null;
            }

            public function getSQLState()
            {
                return null;
            }
        };

        if (isset($e) && $e->getPrevious() instanceof DriverException) {
            $previous = $e->getPrevious();
        }

        throw new ConnectionException('No slaves are available', $previous);
    }
}
