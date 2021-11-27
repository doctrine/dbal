<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use Doctrine\Deprecations\Deprecation;
use mysqli;
use mysqli_sql_exception;

use function floor;
use function stripos;

final class Connection implements ConnectionInterface
{
    /**
     * Name of the option to set connection flags
     */
    public const OPTION_FLAGS = 'flags';

    private mysqli $connection;

    /**
     * @internal The connection can be only instantiated by its driver.
     */
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
            __METHOD__
        );

        return $this->getNativeConnection();
    }

    /**
     * {@inheritdoc}
     *
     * The server version detection includes a special case for MariaDB
     * to support '5.5.5-' prefixed versions introduced in Maria 10+
     *
     * @link https://jira.mariadb.org/browse/MDEV-4088
     */
    public function getServerVersion(): string
    {
        $serverInfos = $this->connection->get_server_info();
        if (stripos($serverInfos, 'mariadb') !== false) {
            return $serverInfos;
        }

        $majorVersion = floor($this->connection->server_version / 10000);
        $minorVersion = floor(($this->connection->server_version - $majorVersion * 10000) / 100);
        $patchVersion = floor($this->connection->server_version - $majorVersion * 10000 - $minorVersion * 100);

        return $majorVersion . '.' . $minorVersion . '.' . $patchVersion;
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
    public function lastInsertId()
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
