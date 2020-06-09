<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\AbstractSQLAnywhereDriver;
use Doctrine\DBAL\Driver\Connection;

use function array_keys;
use function array_map;
use function array_merge;
use function implode;
use function sprintf;

/**
 * A Doctrine DBAL driver for the SAP Sybase SQL Anywhere PHP extension.
 */
final class Driver extends AbstractSQLAnywhereDriver
{
    /**
     * {@inheritdoc}
     *
     * @throws DBALException If there was a problem establishing the connection.
     */
    public function connect(
        array $params,
        string $username = '',
        string $password = '',
        array $driverOptions = []
    ): Connection {
        try {
            return new SQLAnywhereConnection(
                $this->buildDsn($params, $username, $password, $driverOptions),
                $params['persistent'] ?? false
            );
        } catch (SQLAnywhereException $e) {
            throw DBALException::driverException($this, $e);
        }
    }

    /**
     * Build the connection string for given connection parameters and driver options.
     *
     * @param mixed[] $params        DBAL connection parameters
     * @param string  $username      User name to use for connection authentication.
     * @param string  $password      Password to use for connection authentication.
     * @param mixed[] $driverOptions Additional parameters to use for the connection.
     */
    private function buildDsn(array $params, string $username, string $password, array $driverOptions = []): string
    {
        $connectionParams = [];

        if (isset($params['host'])) {
            $host = $params['host'];

            if (isset($params['port'])) {
                $host .= sprintf(':%d', $params['port']);
            }

            $connectionParams['HOST'] = $host;
        }

        if (isset($params['server'])) {
            $connectionParams['ServerName'] = $params['server'];
        }

        if (isset($params['dbname'])) {
            $connectionParams['DBN'] = $params['dbname'];
        }

        $connectionParams['UID'] = $username;
        $connectionParams['PWD'] = $password;

        $connectionParams = array_merge($connectionParams, $driverOptions);

        return implode(';', array_map(static function (string $key, string $value): string {
            return sprintf('%s=%s', $key, $value);
        }, array_keys($connectionParams), $connectionParams));
    }
}
