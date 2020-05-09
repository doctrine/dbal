<?php

namespace Doctrine\DBAL\Connections\Connector;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;

abstract class AbstractMasterSlaveConnector implements Connector
{
    /** @var array */
    protected $_params;

    /** @var Driver */
    protected $_driver;

    /**
     * Creates HighAvailabilityConnector.
     *
     * @param array $params
     */
    public function __construct(array $params, Driver $driver)
    {
        $this->_params = $params;
        $this->_driver = $driver;
    }

    /**
     * Connects with arbitrary params to connection.
     *
     * @param array $connectionParams
     *
     * @return DriverConnection
     */
    protected function connectWithParams($connectionParams)
    {
        $driverOptions = $this->_params['driverOptions'] ?? [];

        $connectionParams = $this->updateFromMasterConnfiguration($connectionParams);

        $user     = $connectionParams['user'] ?? null;
        $password = $connectionParams['password'] ?? null;

        return $this->_driver->connect($connectionParams, $user, $password, $driverOptions);
    }

    /**
     * @param array $config
     *
     * @return array
     */
    protected function updateFromMasterConnfiguration($config)
    {
        if (! isset($config['charset']) && isset($this->_params['master']['charset'])) {
            $config['charset'] = $this->_params['master']['charset'];
        }

        return $config;
    }
}
