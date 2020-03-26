<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver;
use Doctrine\DBAL\Driver\Connection;

/**
 * Driver for ext/sqlsrv.
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
    ) : Connection {
        if (! isset($params['host'])) {
            throw new SQLSrvException('Missing "host" in configuration for sqlsrv driver.');
        }

        $serverName = $params['host'];
        if (isset($params['port'])) {
            $serverName .= ', ' . $params['port'];
        }

        if (isset($params['dbname'])) {
            $driverOptions['Database'] = $params['dbname'];
        }

        if (isset($params['charset'])) {
            $driverOptions['CharacterSet'] = $params['charset'];
        }

        if ($username !== '') {
            $driverOptions['UID'] = $username;
        }

        if ($password !== '') {
            $driverOptions['PWD'] = $password;
        }

        if (! isset($driverOptions['ReturnDatesAsStrings'])) {
            $driverOptions['ReturnDatesAsStrings'] = 1;
        }

        return new SQLSrvConnection($serverName, $driverOptions);
    }
}
