<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver;

/**
 * The PDO-based Sqlsrv driver.
 *
 * @since 2.0
 */
class Driver extends AbstractSQLServerDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        return new Connection(
            $this->_constructPdoDsn($params),
            $username,
            $password,
            $driverOptions
        );
    }

    /**
     * Constructs the Sqlsrv PDO DSN.
     *
     * @param array $params
     *
     * @return string The DSN.
     */
    private function _constructPdoDsn(array $params)
    {
        $dsn = 'sqlsrv:server=';

        if (isset($params['host'])) {
            $dsn .= $params['host'];
        }

        if (isset($params['port']) && !empty($params['port'])) {
            $dsn .= ',' . $params['port'];
        }

        if (isset($params['dbname'])) {
            $dsn .= ';Database=' .  $params['dbname'];
        }

        if (isset($params['MultipleActiveResultSets'])) {
            $dsn .= '; MultipleActiveResultSets=' . ($params['MultipleActiveResultSets'] ? 'true' : 'false');
        }

        return $dsn;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_sqlsrv';
    }
}
