<?php

namespace Doctrine\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\AbstractSQLAnywhereDriver;
use function array_keys;
use function array_map;
use function implode;

/**
 * A Doctrine DBAL driver for the SAP Sybase SQL Anywhere PHP extension.
 */
class Driver extends AbstractSQLAnywhereDriver
{
    /**
     * {@inheritdoc}
     *
     * @throws DBALException If there was a problem establishing the connection.
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        try {
            return new SQLAnywhereConnection(
                $this->buildDsn(
                    $params['host'] ?? null,
                    $params['port'] ?? null,
                    $params['server'] ?? null,
                    $params['dbname'] ?? null,
                    $username,
                    $password,
                    $driverOptions
                ),
                $params['persistent'] ?? false
            );
        } catch (SQLAnywhereException $e) {
            throw DBALException::driverException($this, $e);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated
     */
    public function getName()
    {
        return 'sqlanywhere';
    }

    /**
     * Build the connection string for given connection parameters and driver options.
     *
     * @param string|null $host          Host address to connect to.
     * @param int|null    $port          Port to use for the connection (default to SQL Anywhere standard port 2638).
     * @param string|null $server        Database server name on the host to connect to.
     *                                   SQL Anywhere allows multiple database server instances on the same host,
     *                                   therefore specifying the server instance name to use is mandatory.
     * @param string|null $dbname        Name of the database on the server instance to connect to.
     * @param string|null $username      User name to use for connection authentication.
     * @param string|null $password      Password to use for connection authentication.
     * @param mixed[]     $driverOptions Additional parameters to use for the connection.
     *
     * @return string
     */
    private function buildDsn($host, $port, $server, $dbname, $username = null, $password = null, array $driverOptions = [])
    {
        $host = $host ?? 'localhost';
        $port = $port ?? 2638;

        if (! empty($server)) {
            $server = ';ServerName=' . $server;
        }

        return 'HOST=' . $host . ':' . $port .
            $server .
            ';DBN=' . $dbname .
            ';UID=' . $username .
            ';PWD=' . $password .
            ';' . implode(
                ';',
                array_map(static function ($key, $value) {
                    return $key . '=' . $value;
                }, array_keys($driverOptions), $driverOptions)
            );
    }
}