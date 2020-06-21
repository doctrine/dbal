<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDOMySql;

use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\PDOConnection;
use PDO;

/**
 * PDO MySql driver.
 */
final class Driver extends AbstractMySQLDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params): Connection
    {
        $driverOptions = $params['driver_options'] ?? [];

        if (! empty($params['persistent'])) {
            $driverOptions[PDO::ATTR_PERSISTENT] = true;
        }

        return new PDOConnection(
            $this->constructPdoDsn($params),
            $params['user'] ?? '',
            $params['password'] ?? '',
            $driverOptions
        );
    }

    /**
     * Constructs the MySql PDO DSN.
     *
     * @param mixed[] $params
     *
     * @return string The DSN.
     */
    private function constructPdoDsn(array $params): string
    {
        $dsn = 'mysql:';
        if (isset($params['host']) && $params['host'] !== '') {
            $dsn .= 'host=' . $params['host'] . ';';
        }

        if (isset($params['port'])) {
            $dsn .= 'port=' . $params['port'] . ';';
        }

        if (isset($params['dbname'])) {
            $dsn .= 'dbname=' . $params['dbname'] . ';';
        }

        if (isset($params['unix_socket'])) {
            $dsn .= 'unix_socket=' . $params['unix_socket'] . ';';
        }

        if (isset($params['charset'])) {
            $dsn .= 'charset=' . $params['charset'] . ';';
        }

        return $dsn;
    }
}
