<?php

namespace Doctrine\DBAL\Driver\PDO\DBLib;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver;
use Doctrine\DBAL\Driver\AbstractSQLServerDriver\Exception\PortWithoutHost;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\PDO\Connection as PDOConnection;
use Doctrine\DBAL\Driver\PDO\Exception as PDOException;
use Doctrine\DBAL\Driver\PDO\Exception\InvalidConfiguration;
use Doctrine\DBAL\Driver\PDO\PDOConnect;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Connection;

class Driver extends AbstractSQLServerDriver
{
    use PDOConnect;

    public function connect(
        #[\SensitiveParameter]
        array $params,
    ): DriverConnection {
        $driverOptions = $dsnOptions = [];

        if (isset($params['driverOptions'])) {
            foreach ($params['driverOptions'] as $option => $value) {
                if (\is_int($option)) {
                    $driverOptions[$option] = $value;
                } else {
                    $dsnOptions[$option] = $value;
                }
            }
        }

        if (!empty($params['persistent'])) {
            $driverOptions[\PDO::ATTR_PERSISTENT] = true;
        }

        foreach (['user', 'password'] as $key) {
            if (isset($params[$key]) && !\is_string($params[$key])) {
                throw InvalidConfiguration::notAStringOrNull($key, $params[$key]);
            }
        }

        $safeParams = $params;
        unset($safeParams['password']);

        try {
            $pdo = $this->doConnect(
                $this->constructDsn($safeParams, $dsnOptions),
                $params['user'] ?? '',
                $params['password'] ?? '',
                $driverOptions,
            );
        } catch (\PDOException $exception) {
            throw PDOException::new($exception);
        }

        return new Connection(new PDOConnection($pdo));
    }

    /**
     * Constructs the Sqlsrv PDO DSN.
     *
     * @param mixed[]  $params
     * @param string[] $connectionOptions
     *
     * @throws Exception
     */
    private function constructDsn(array $params, array $connectionOptions): string
    {
        $dsnParams = $connectionOptions;

        if (isset($params['host'])) {
            $dsnParams['host'] = $params['host'];

            if (isset($params['port'])) {
                $dsnParams['port'] = $params['port'];
            }
        } elseif (isset($params['port'])) {
            throw PortWithoutHost::new();
        }

        if (isset($params['dbname'])) {
            $dsnParams['dbname'] = $params['dbname'];
        }

        if (isset($params['charset'])) {
            $dsnParams['charset'] = $params['charset'];
        }

        return 'dblib:'.http_build_query($dsnParams, arg_separator: ';');
    }
}
