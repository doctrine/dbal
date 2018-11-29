<?php

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\DriverException;

class Driver extends AbstractMySQLDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        try {
            return new MysqliConnection($params, $username, $password, $driverOptions);
        } catch (DriverException $e) {
            throw DBALException::driverException($this, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'mysqli';
    }
}
