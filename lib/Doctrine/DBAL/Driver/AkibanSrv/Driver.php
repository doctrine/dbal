<?php

namespace Doctrine\DBAL\Driver\AkibanSrv;

use Doctrine\DBAL\Platforms;

/**
 * Driver that connects to Akiban Server through pgsql.
 *
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 * @since 2.3
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
            $username,
            $password,
            $this->_constructDsn($params)
        );
    }

    /**
     * Constructs the Akiban Server DSN.
     *
     * @return string The DSN.
     */
    private function _constructPdoDsn(array $params)
    {
        $dsn = '';
        if (isset($params['host']) && $params['host'] != '') {
            $dsn .= 'host=' . $params['host'] . ' ';
        }
        if (isset($params['port']) && $params['port'] != '') {
            $dsn .= 'port=' . $params['port'] . ' ';
        }
        if (isset($params['dbname'])) {
            $dsn .= 'dbname=' . $params['dbname'] . ' ';
        }

        return $dsn;
    }

    public function getDatabasePlatform()
    {
        return new \Doctrine\DBAL\Platforms\AkibanSrvPlatform();
    }

    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new \Doctrine\DBAL\Schema\AkibanSrvSchemaManager($conn);
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
