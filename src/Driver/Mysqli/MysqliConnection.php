<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use Doctrine\DBAL\Driver\PingableConnection;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use mysqli;

use function floor;
use function mysqli_init;
use function stripos;

class MysqliConnection implements PingableConnection, ServerInfoAwareConnection
{
    /**
     * Name of the option to set connection flags
     */
    public const OPTION_FLAGS = 'flags';

    /** @var mysqli */
    private $conn;

    /**
     * @param iterable<Initializer> $preInitializers
     * @param iterable<Initializer> $postInitializers
     *
     * @throws MysqliException
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

        foreach ($preInitializers as $initializer) {
            $initializer->initialize($connection);
        }

        if (! @$connection->real_connect($host, $username, $password, $database, $port, $socket, $flags)) {
            throw new MysqliException(
                $connection->connect_error,
                'HY000',
                $connection->connect_errno
            );
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

    public function prepare(string $sql): DriverStatement
    {
        return new MysqliStatement($this->conn, $sql);
    }

    public function query(string $sql): ResultInterface
    {
        return $this->prepare($sql)->execute();
    }

    public function quote(string $input): string
    {
        return "'" . $this->conn->escape_string($input) . "'";
    }

    public function exec(string $statement): int
    {
        if ($this->conn->query($statement) === false) {
            throw ConnectionError::new($this->conn);
        }

        return $this->conn->affected_rows;
    }

    public function lastInsertId(?string $name = null): string
    {
        return (string) $this->conn->insert_id;
    }

    public function beginTransaction(): void
    {
        $this->conn->query('START TRANSACTION');
    }

    public function commit(): void
    {
        if (! $this->conn->commit()) {
            throw ConnectionError::new($this->conn);
        }
    }

    public function rollBack(): void
    {
        if (! $this->conn->rollback()) {
            throw ConnectionError::new($this->conn);
        }
    }

    /**
     * Pings the server and re-connects when `mysqli.reconnect = 1`
     *
     * {@inheritDoc}
     */
    public function ping(): void
    {
        if (! $this->conn->ping()) {
            throw new MysqliException($this->conn->error, $this->conn->sqlstate, $this->conn->errno);
        }
    }
}
