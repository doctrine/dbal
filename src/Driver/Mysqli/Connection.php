<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use mysqli;
use mysqli_sql_exception;

final class Connection implements ConnectionInterface
{
    /**
     * Name of the option to set connection flags
     */
    public const OPTION_FLAGS = 'flags';

    /** @internal The connection can be only instantiated by its driver. */
    public function __construct(private readonly mysqli $connection)
    {
    }

    public function getServerVersion(): string
    {
        return $this->connection->get_server_info();
    }

    public function prepare(string $sql): Statement
    {
        try {
            $stmt = $this->connection->prepare($sql);
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
        }

        if ($stmt === false) {
            throw ConnectionError::new($this->connection);
        }

        return new Statement($stmt);
    }

    public function query(string $sql): Result
    {
        return $this->prepare($sql)->execute();
    }

    public function quote(string $value): string
    {
        return "'" . $this->connection->escape_string($value) . "'";
    }

    public function exec(string $sql): int|string
    {
        try {
            $result = $this->connection->query($sql);
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
        }

        if ($result === false) {
            throw ConnectionError::new($this->connection);
        }

        return $this->connection->affected_rows;
    }

    public function lastInsertId(): int|string
    {
        $lastInsertId = $this->connection->insert_id;

        if ($lastInsertId === 0) {
            throw Exception\NoIdentityValue::new();
        }

        return $this->connection->insert_id;
    }

    public function beginTransaction(): void
    {
        $this->connection->begin_transaction();
    }

    public function commit(): void
    {
        try {
            if (! $this->connection->commit()) {
                throw ConnectionError::new($this->connection);
            }
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
        }
    }

    public function rollBack(): void
    {
        try {
            if (! $this->connection->rollback()) {
                throw ConnectionError::new($this->connection);
            }
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
        }
    }

    public function getNativeConnection(): mysqli
    {
        return $this->connection;
    }
}
