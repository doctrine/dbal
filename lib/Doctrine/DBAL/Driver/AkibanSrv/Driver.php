<?php

namespace Doctrine\DBAL\Driver\AkibanSrv;

use Doctrine\DBAL\Platforms;

/**
 * Driver that connects to Akiban Server through pgsql.
 *
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 * @since  2.3
 */
class Driver implements \Doctrine\DBAL\Driver
{
    /**
     * Attempts to connect to the database and returns a driver connection on success.
     *
     * @return \Doctrine\DBAL\Driver\Connection
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        return new AkibanSrvConnection(
            $this->_constructConnectionString($params, $username, $password)
        );
    }

    /**
     * Constructs the Akiban Server connection string.
     *
     * @return string The connection string.
     */
    private function _constructConnectionString(array $params, $username, $password)
    {
        $connString = '';
        if (isset($params['host']) && $params['host'] != '') {
            $connString .= 'host=' . $params['host'] . ' ';
        }
        if (isset($params['port']) && $params['port'] != '') {
            $connString .= 'port=' . $params['port'] . ' ';
        }
        if (isset($params['dbname'])) {
            $connString .= 'dbname=' . $params['dbname'] . ' ';
        }
        if (isset($username)) {
            $connString .= 'user=' . $username . ' ';
        }
        if (isset($password)) {
            $connString .= 'user=' . $username . ' ';
        }

        return $connString;
    }

    public function getDatabasePlatform()
    {
        return new \Doctrine\DBAL\Platforms\AkibanServerPlatform();
    }

    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new \Doctrine\DBAL\Schema\AkibanServerSchemaManager($conn);
    }

    public function getName()
    {
        return 'akibansrv';
    }

    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();
        return $params['dbname'];
    }
}
