<?php

namespace Doctrine\DBAL\Driver\PDOPgSql;

use Doctrine\DBAL\Platforms;

/**
 * Driver that connects through pdo_pgsql.
 *
 * @since 2.0
 */
class Driver implements \Doctrine\DBAL\Driver
{
    /**
     * Contains the search_path from database driver config
     *
     * @var string
     */
    private $search_path;
    
    /**
     * Attempts to connect to the database and returns a driver connection on success.
     *
     * @return Doctrine\DBAL\Driver\Connection
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        $realDriverOptions = $this->filterAndSetLocalOptions($driverOptions);
        $connection =  new PgSqlConnection(
            $this->_constructPdoDsn($params),
            $username,
            $password,
            $realDriverOptions
         );
         if ($this->search_path) {
            $connection->setSearchPath($this->search_path);
         }	
        return $connection;
    }
    /**
     * Filter and set options meant for this driver
     *
     * @return driver Options to pass to the connection
     */
    private function filterAndSetLocalOptions($driverOptions)
    {
        $realDriverOptions = array();		
        foreach ( $driverOptions as $key=>$value) {
            if ($key == 'search_path') {
                $this->search_path = $value;
            } else {
                $realDriverOptions[$key]=$value;
            }
        }
        return $realDriverOptions;		
    }
    /**
     * Constructs the Postgres PDO DSN.
     *
     * @return string The DSN.
     */
    private function _constructPdoDsn(array $params)
    {
        $dsn = 'pgsql:';
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
        return new \Doctrine\DBAL\Platforms\PostgreSqlPlatform();
    }

    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new \Doctrine\DBAL\Schema\PostgreSqlSchemaManager($conn);
    }

    public function getName()
    {
        return 'pdo_pgsql';
    }

    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();
        return $params['dbname'];
    }
}
