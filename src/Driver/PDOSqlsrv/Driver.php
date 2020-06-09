<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use PDO;

use function is_int;
use function sprintf;

/**
 * The PDO-based Sqlsrv driver.
 */
final class Driver extends AbstractSQLServerDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(
        array $params,
        string $username = '',
        string $password = '',
        array $driverOptions = []
    ): DriverConnection {
        $pdoOptions = $dsnOptions = [];

        foreach ($driverOptions as $option => $value) {
            if (is_int($option)) {
                $pdoOptions[$option] = $value;
            } else {
                $dsnOptions[$option] = $value;
            }
        }

        if (! empty($params['persistent'])) {
            $pdoOptions[PDO::ATTR_PERSISTENT] = true;
        }

        return new Connection(
            $this->constructPdoDsn($params, $dsnOptions),
            $username,
            $password,
            $pdoOptions
        );
    }

    /**
     * Constructs the Sqlsrv PDO DSN.
     *
     * @param mixed[]  $params
     * @param string[] $connectionOptions
     *
     * @return string The DSN.
     */
    private function constructPdoDsn(array $params, array $connectionOptions): string
    {
        $dsn = 'sqlsrv:server=';

        if (isset($params['host'])) {
            $dsn .= $params['host'];
        }

        if (isset($params['port'])) {
            $dsn .= ',' . $params['port'];
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
