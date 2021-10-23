<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionFailed;
use mysqli;
use mysqli_sql_exception;

use function assert;
use function floor;
use function mysqli_init;
use function stripos;

final class Connection implements ConnectionInterface
{
    /**
     * Name of the option to set connection flags
     */
    public const OPTION_FLAGS = 'flags';

    private mysqli $conn;

    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param iterable<Initializer> $preInitializers
     * @param iterable<Initializer> $postInitializers
     *
     * @throws Exception
     */
    public function __construct(
        string $host = '',
        string $username = '',
        string $password = '',
        string $database = '',
        int $port = 0,
        string $socket = '',
        int $flags = 0,
        iterable $preInitializers = [],
        iterable $postInitializers = []
    ) {
        $connection = mysqli_init();
        assert($connection !== false);

        foreach ($preInitializers as $initializer) {
            $initializer->initialize($connection);
        }

        try {
            $success = @$connection->real_connect($host, $username, $password, $database, $port, $socket, $flags);
        } catch (mysqli_sql_exception $e) {
            throw ConnectionFailed::upcast($e);
        }

        if (! $success) {
            throw ConnectionFailed::new($connection);
        }

        foreach ($postInitializers as $initializer) {
            $initializer->initialize($connection);
        }

        $this->conn = $connection;
    }

    /**
     * Retrieves mysqli native resource handle.
     *
     * Could be used if part of your application is not using DBAL.
     */
    public function getWrappedResourceHandle(): mysqli
    {
        return $this->conn;
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
        $serverInfos = $this->conn->get_server_info();
        if (stripos($serverInfos, 'mariadb') !== false) {
            return $serverInfos;
        }

        $majorVersion = floor($this->conn->server_version / 10000);
        $minorVersion = floor(($this->conn->server_version - $majorVersion * 10000) / 100);
        $patchVersion = floor($this->conn->server_version - $majorVersion * 10000 - $minorVersion * 100);

        return $majorVersion . '.' . $minorVersion . '.' . $patchVersion;
    }

    public function prepare(string $sql): Statement
    {
        try {
            $stmt = $this->conn->prepare($sql);
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
        }

        if ($stmt === false) {
            throw ConnectionError::new($this->conn);
        }

        return new Statement($stmt);
    }

    public function query(string $sql): Result
    {
        return $this->prepare($sql)->execute();
    }

    public function quote(string $value): string
    {
        return "'" . $this->conn->escape_string($value) . "'";
    }

    public function exec(string $sql): int
    {
        try {
            $result = $this->conn->query($sql);
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
        }

        if ($result === false) {
            throw ConnectionError::new($this->conn);
        }

        return $this->conn->affected_rows;
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId()
    {
        $lastInsertId = $this->conn->insert_id;

        if ($lastInsertId === 0) {
            throw Exception\NoIdentityValue::new();
        }

        return $lastInsertId;
    }

    public function beginTransaction(): void
    {
        $this->conn->begin_transaction();
    }

    public function commit(): void
    {
        try {
            if (! $this->conn->commit()) {
                throw ConnectionError::new($this->conn);
            }
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
        }
    }

    public function rollBack(): void
    {
        try {
            if (! $this->conn->rollback()) {
                throw ConnectionError::new($this->conn);
            }
        } catch (mysqli_sql_exception $e) {
            throw ConnectionError::upcast($e);
        }
    }
}
