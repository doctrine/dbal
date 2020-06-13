<?php

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\AbstractMySQLDriver;

class Driver extends AbstractMySQLDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        return new MysqliConnection($params, (string) $username, (string) $password, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'mysqli';
    }
}
