<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO\SQLSrv;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\PDO\Connection as PDOConnection;
use Doctrine\DBAL\Driver\PDO\Result;
use Doctrine\Deprecations\Deprecation;
use PDO;

final class Connection implements ConnectionInterface
{
    private PDOConnection $connection;

    public function __construct(PDOConnection $connection)
    {
        $this->connection = $connection;
    }

    public function prepare(string $sql): Statement
    {
        return new Statement(
            $this->connection->prepare($sql)
        );
    }

    public function query(string $sql): Result
    {
        return $this->connection->query($sql);
    }

    public function quote(string $value): string
    {
        return $this->connection->quote($value);
    }

    public function exec(string $sql): int
    {
        return $this->connection->exec($sql);
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
    }

    public function getServerVersion(): string
    {
        return $this->connection->getServerVersion();
    }

    public function getNativeConnection(): PDO
    {
        return $this->connection->getNativeConnection();
    }

    /**
     * @deprecated Call {@see getNativeConnection()} instead.
     */
    public function getWrappedConnection(): PDO
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5037',
            '%s is deprecated, call getNativeConnection() instead.',
            __METHOD__
        );

        return $this->connection->getWrappedConnection();
    }
}
