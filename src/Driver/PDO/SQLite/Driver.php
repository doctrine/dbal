<?php

namespace Doctrine\DBAL\Driver\PDO\SQLite;

use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\API\SQLite\UserDefinedFunctions;
use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\Deprecations\Deprecation;
use PDO;
use PDOException;

final class Driver extends AbstractSQLiteDriver
{
    /**
     * {@inheritdoc}
     *
     * @return Connection
     */
    public function connect(array $params)
    {
        $driverOptions        = $params['driverOptions'] ?? [];
        $userDefinedFunctions = [];

        if (isset($driverOptions['userDefinedFunctions'])) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5742',
                'The SQLite-specific driver option "userDefinedFunctions" is deprecated.'
                    . ' Register function directly on the native connection instead.',
            );

            $userDefinedFunctions = $driverOptions['userDefinedFunctions'];
            unset($driverOptions['userDefinedFunctions']);
        }

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

        UserDefinedFunctions::register(
            [$pdo, 'sqliteCreateFunction'],
            $userDefinedFunctions,
        );

        return new Connection($pdo);
    }

    /**
     * Constructs the Sqlite PDO DSN.
     *
     * @param mixed[] $params
     */
    private function constructPdoDsn(array $params): string
    {
        $dsn = 'sqlite:';
        if (isset($params['path'])) {
            $dsn .= $params['path'];
        } elseif (isset($params['memory'])) {
            $dsn .= ':memory:';
        }

        return $dsn;
    }
}
