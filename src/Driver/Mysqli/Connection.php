<?php

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\API\AsyncConnection;
use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use mysqli;
use mysqli_sql_exception;

use function mysqli_poll;

use const MYSQLI_ASYNC;

final class Connection implements ServerInfoAwareConnection, AsyncConnection
{
    /**
     * Name of the option to set connection flags
     */
    public const OPTION_FLAGS = 'flags';

    private mysqli $connection;

    /** @internal The connection can be only instantiated by its driver. */
    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Retrieves mysqli native resource handle.
     *
     * Could be used if part of your application is not using DBAL.
     *
     * @deprecated Call {@see getNativeConnection()} instead.
     */
    public function getWrappedResourceHandle(): mysqli
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5037',
            '%s is deprecated, call getNativeConnection() instead.',
            __METHOD__,
        );

        return $this->getNativeConnection();
    }

    public function getServerVersion(): string
    {
        return $this->connection->get_server_info();
    }

    public function prepare(string $sql): DriverStatement
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

    public function query(string $sql): ResultInterface
    {
        return $this->prepare($sql)->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function quote($value, $type = ParameterType::STRING)
    {
        return "'" . $this->connection->escape_string($value) . "'";
    }

    public function exec(string $sql): int
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

    /**
     * {@inheritDoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name !== null) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/4687',
                'The usage of Connection::lastInsertId() with a sequence name is deprecated.',
            );
        }

        return $this->connection->insert_id;
    }

    public function beginTransaction(): bool
    {
        $this->connection->begin_transaction();

        return true;
    }

    public function commit(): bool
    {
        try {
            return $this->connection->commit();
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    public function rollBack(): bool
    {
        try {
            return $this->connection->rollback();
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    public function getNativeConnection(): mysqli
    {
        return $this->connection;
    }

    /**
     * Sends a query asynchronously without waiting for results.
     *
     * Note: mysqli async queries do not support prepared statements.
     * Parameters must be escaped and embedded in the query string.
     *
     * @param string      $sql    The SQL query to execute (with params already embedded)
     * @param list<mixed> $params Not used for mysqli - params must be in SQL string
     *
     * @throws ConnectionError If sending the query fails
     */
    public function sendQueryAsync(string $sql, array $params = []): bool
    {
        // Note: mysqli MYSQLI_ASYNC does not support prepared statements
        // The caller must ensure parameters are properly escaped in the SQL string
        try {
            $result = $this->connection->query($sql, MYSQLI_ASYNC);
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
        }

        // For async queries, query() returns true immediately
        if ($result === false) {
            throw ConnectionError::new($this->connection);
        }

        return true;
    }

    /**
     * Checks if the connection is busy processing an async query.
     *
     * Uses mysqli_poll with 0 timeout to check without blocking.
     */
    public function isBusy(): bool
    {
        $read   = [$this->connection];
        $error  = [];
        $reject = [];

        // Poll with 0 second timeout to check status without blocking
        $result = mysqli_poll($read, $error, $reject, 0, 0);

        // If connection is in $read array, it means result is ready (not busy)
        // If $result is 0, connection is still busy
        return $result === 0;
    }

    /**
     * Retrieves the result of the last async query.
     *
     * @throws ConnectionError If retrieving the result fails
     */
    public function getAsyncResult(): ResultInterface
    {
        $result = $this->connection->reap_async_query();

        if ($result === false) {
            throw ConnectionError::new($this->connection);
        }

        // reap_async_query returns mysqli_result|bool
        // For SELECT queries, it returns mysqli_result
        // For INSERT/UPDATE/DELETE, it returns true
        if ($result === true) {
            // For non-SELECT queries, return AsyncResult with null
            return new AsyncResult(null, $this->connection);
        }

        return new AsyncResult($result, $this->connection);
    }
}
