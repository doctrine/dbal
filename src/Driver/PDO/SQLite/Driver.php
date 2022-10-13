<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO\SQLite;

use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use PDO;
use PDOException;

final class Driver extends AbstractSQLiteDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params): Connection
    {
        try {
            $pdo = new PDO(
                $this->constructPdoDsn($params),
                $params['user'] ?? '',
                $params['password'] ?? '',
                $params['driverOptions'] ?? [],
            );
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

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
