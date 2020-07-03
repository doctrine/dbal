<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver;
use Doctrine\DBAL\Driver\AbstractSQLServerDriver\Exception\PortWithoutHost;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\PDO\Connection as PDOConnection;
use PDO;

use function is_int;
use function sprintf;

/**
 * The PDO-based Sqlsrv driver.
 */
class Driver extends AbstractSQLServerDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params)
    {
        $pdoOptions = $dsnOptions = [];

        if (isset($params['driver_options'])) {
            foreach ($params['driver_options'] as $option => $value) {
                if (is_int($option)) {
                    $pdoOptions[$option] = $value;
                } else {
                    $dsnOptions[$option] = $value;
                }
            }
        }

        if (! empty($params['persistent'])) {
            $pdoOptions[PDO::ATTR_PERSISTENT] = true;
        }

        return new Connection(
            new PDOConnection(
                $this->_constructPdoDsn($params, $dsnOptions),
                $params['user'] ?? '',
                $params['password'] ?? '',
                $pdoOptions
            )
        );
    }

    /**
     * Constructs the Sqlsrv PDO DSN.
     *
     * @param mixed[]  $params
     * @param string[] $connectionOptions
     *
     * @return string The DSN.
     *
     * @throws Exception
     */
    private function _constructPdoDsn(array $params, array $connectionOptions)
    {
        $dsn = 'sqlsrv:server=';

        if (isset($params['host'])) {
            $dsn .= $params['host'];

            if (isset($params['port'])) {
                $dsn .= ',' . $params['port'];
            }
        } elseif (isset($params['port'])) {
            throw PortWithoutHost::new();
        }

        if (isset($params['dbname'])) {
            $connectionOptions['Database'] = $params['dbname'];
        }

        if (isset($params['MultipleActiveResultSets'])) {
            $connectionOptions['MultipleActiveResultSets'] = $params['MultipleActiveResultSets'] ? 'true' : 'false';
        }

        return $dsn . $this->getConnectionOptionsDsn($connectionOptions);
    }

    /**
     * Converts a connection options array to the DSN
     *
     * @param string[] $connectionOptions
     */
    private function getConnectionOptionsDsn(array $connectionOptions): string
    {
        $connectionOptionsDsn = '';

        foreach ($connectionOptions as $paramName => $paramValue) {
            $connectionOptionsDsn .= sprintf(';%s=%s', $paramName, $paramValue);
        }

        return $connectionOptionsDsn;
    }
}
