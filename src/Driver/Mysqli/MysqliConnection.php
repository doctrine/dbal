<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use Doctrine\DBAL\Driver\PingableConnection;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use mysqli;

use function defined;
use function floor;
use function in_array;
use function ini_get;
use function mysqli_init;
use function mysqli_options;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function stripos;

use const MYSQLI_INIT_COMMAND;
use const MYSQLI_OPT_CONNECT_TIMEOUT;
use const MYSQLI_OPT_LOCAL_INFILE;
use const MYSQLI_READ_DEFAULT_FILE;
use const MYSQLI_READ_DEFAULT_GROUP;
use const MYSQLI_SERVER_PUBLIC_KEY;

class MysqliConnection implements PingableConnection, ServerInfoAwareConnection
{
    /**
     * Name of the option to set connection flags
     */
    public const OPTION_FLAGS = 'flags';

    /** @var mysqli */
    private $conn;

    /**
     * @param array<string, mixed> $params
     * @param array<int, mixed>    $driverOptions
     *
     * @throws MysqliException
     */
    public function __construct(array $params, string $username, string $password, array $driverOptions = [])
    {
        $socket = $params['unix_socket'] ?? ini_get('mysqli.default_socket');
        $dbname = $params['dbname'] ?? '';
        $port   = $params['port'] ?? 0;

        if (! empty($params['persistent'])) {
            if (! isset($params['host'])) {
                throw HostRequired::forPersistentConnection();
            }

            $host = 'p:' . $params['host'];
        } else {
            $host = $params['host'] ?? null;
        }

        $flags = $driverOptions[static::OPTION_FLAGS] ?? 0;

        $this->conn = mysqli_init();

        $this->setSecureConnection($params);
        $this->setDriverOptions($driverOptions);

        set_error_handler(static function (): bool {
            return true;
        });

        try {
            if (! $this->conn->real_connect($host, $username, $password, $dbname, $port, $socket, $flags)) {
                throw ConnectionError::new($this->conn);
            }
        } finally {
            restore_error_handler();
        }

        if (! isset($params['charset'])) {
            return;
        }

        $this->conn->set_charset($params['charset']);
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
     * Apply the driver options to the connection.
     *
     * @param array<int, mixed> $driverOptions
     *
     * @throws MysqliException When one of of the options is not supported.
     * @throws MysqliException When applying doesn't work - e.g. due to incorrect value.
     */
    private function setDriverOptions(array $driverOptions = []): void
    {
        $supportedDriverOptions = [
            MYSQLI_OPT_CONNECT_TIMEOUT,
            MYSQLI_OPT_LOCAL_INFILE,
            MYSQLI_INIT_COMMAND,
            MYSQLI_READ_DEFAULT_FILE,
            MYSQLI_READ_DEFAULT_GROUP,
        ];

        if (defined('MYSQLI_SERVER_PUBLIC_KEY')) {
            $supportedDriverOptions[] = MYSQLI_SERVER_PUBLIC_KEY;
        }

        $exceptionMsg = "%s option '%s' with value '%s'";

        foreach ($driverOptions as $option => $value) {
            if ($option === static::OPTION_FLAGS) {
                continue;
            }

            if (! in_array($option, $supportedDriverOptions, true)) {
                throw new MysqliException(
                    sprintf($exceptionMsg, 'Unsupported', $option, $value)
                );
            }

            if (@mysqli_options($this->conn, $option, $value)) {
                continue;
            }

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

    /**
     * Establish a secure connection
     *
     * @param array<string, mixed> $params
     *
     * @throws MysqliException
     */
    private function setSecureConnection(array $params): void
    {
        if (
            ! isset($params['ssl_key']) &&
            ! isset($params['ssl_cert']) &&
            ! isset($params['ssl_ca']) &&
            ! isset($params['ssl_capath']) &&
            ! isset($params['ssl_cipher'])
        ) {
            return;
        }

        $this->conn->ssl_set(
            $params['ssl_key']    ?? null,
            $params['ssl_cert']   ?? null,
            $params['ssl_ca']     ?? null,
            $params['ssl_capath'] ?? null,
            $params['ssl_cipher'] ?? null
        );
    }
}
