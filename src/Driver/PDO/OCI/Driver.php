<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO\OCI;

use Doctrine\DBAL\Driver\AbstractOracleDriver;
use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\PDO\Exception\InvalidConfiguration;
use PDO;
use PDOException;
use SensitiveParameter;

use function is_string;

final class Driver extends AbstractOracleDriver
{
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        $driverOptions = $params['driverOptions'] ?? [];

        if (! empty($params['persistent'])) {
            $driverOptions[PDO::ATTR_PERSISTENT] = true;
        }

        foreach (['user', 'password'] as $key) {
            if (isset($params[$key]) && ! is_string($params[$key])) {
                throw InvalidConfiguration::notAStringOrNull($key, $params[$key]);
            }
        }

        $safeParams = $params;
        unset($safeParams['password']);

        try {
            $pdo = new PDO(
                $this->constructPdoDsn($params),
                $params['user'] ?? '',
                $params['password'] ?? '',
                $driverOptions,
            );
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        return new Connection($pdo);
    }

    /**
     * Constructs the Oracle PDO DSN.
     *
     * @param mixed[] $params
     */
    private function constructPdoDsn(array $params): string
    {
        $dsn = 'oci:dbname=' . $this->getEasyConnectString($params);

        if (isset($params['charset'])) {
            $dsn .= ';charset=' . $params['charset'];
        }

        return $dsn;
    }
}
