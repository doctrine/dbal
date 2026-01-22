<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use mysqli;
use mysqli_sql_exception;

use function assert;

final readonly class Connection implements ConnectionInterface
{
    /**
     * Name of the option to set connection flags
     */
    public const string OPTION_FLAGS = 'flags';

    /** @internal The connection can be only instantiated by its driver. */
    public function __construct(private mysqli $connection)
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

        assert($stmt !== false);

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
            $this->connection->query($sql);
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
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
        try {
            // Prior to PHP 8.5.2, PHP 8.4.17 and PHP 8.3.30, mysqli::begin_transaction() didn't respect error reporting
            // configuration and reported failure as false return value
            // See: https://github.com/php/php-src/commit/dbf56e0eba68c61385e9a2d15a3e3f5066f80ec4
            if (! $this->connection->begin_transaction()) {
                throw ConnectionError::new($this->connection);
            }
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
        }
    }

    public function commit(): void
    {
        try {
            $this->connection->commit();
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
        }
    }

    public function rollBack(): void
    {
        try {
            $this->connection->rollback();
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
        }
    }

    public function getNativeConnection(): mysqli
    {
        return $this->connection;
    }
}
