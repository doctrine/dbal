<?php

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Initializer\Charset;
use Doctrine\DBAL\Driver\Mysqli\Initializer\Options;
use Doctrine\DBAL\Driver\Mysqli\Initializer\Secure;
use Doctrine\DBAL\Driver\PingableConnection;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use mysqli;

use function count;
use function floor;
use function ini_get;
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
     * @param mixed[] $params
     * @param string  $username
     * @param string  $password
     * @param mixed[] $driverOptions
     *
     * @throws MysqliException
     */
    public function __construct(array $params, $username, $password, array $driverOptions = [])
    {
        $socket = $params['unix_socket'] ?? ini_get('mysqli.default_socket');
        $dbname = $params['dbname'] ?? null;
        $port   = $params['port'] ?? null;

        if (! empty($params['persistent'])) {
            if (! isset($params['host'])) {
                throw HostRequired::forPersistentConnection();
            }

            $host = 'p:' . $params['host'];
        } else {
            $host = $params['host'] ?? null;
        }

        $flags = $driverOptions[static::OPTION_FLAGS] ?? null;
        unset($driverOptions[static::OPTION_FLAGS]);

        $this->conn = mysqli_init();

        $preInitializers = $postInitializers = [];

        $preInitializers  = $this->withOptions($preInitializers, $driverOptions);
        $preInitializers  = $this->withSecure($preInitializers, $params);
        $postInitializers = $this->withCharset($postInitializers, $params);

        foreach ($preInitializers as $initializer) {
            $initializer->initialize($this->conn);
        }

        if (! @$this->conn->real_connect($host, $username, $password, $dbname, $port, $socket, $flags)) {
            throw new MysqliException(
                $this->conn->connect_error,
                $this->conn->sqlstate ?? 'HY000',
                $this->conn->connect_errno
            );
        }

        foreach ($postInitializers as $initializer) {
            $initializer->initialize($this->conn);
        }
    }

    /**
     * Retrieves mysqli native resource handle.
     *
     * Could be used if part of your application is not using DBAL.
     *
     * @return mysqli
     */
    public function getWrappedResourceHandle()
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
    public function getServerVersion()
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

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }

    public function prepare(string $sql): DriverStatement
    {
        return new MysqliStatement($this->conn, $sql);
    }

    public function query(string $sql): ResultInterface
    {
        return $this->prepare($sql)->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function quote($input, $type = ParameterType::STRING)
    {
        return "'" . $this->conn->escape_string($input) . "'";
    }

    public function exec(string $statement): int
    {
        if ($this->conn->query($statement) === false) {
            throw new MysqliException($this->conn->error, $this->conn->sqlstate, $this->conn->errno);
        }

        return $this->conn->affected_rows;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        return $this->conn->insert_id;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->conn->query('START TRANSACTION');

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return $this->conn->commit();
    }

    /**
     * {@inheritdoc}non-PHPdoc)
     */
    public function rollBack()
    {
        return $this->conn->rollback();
    }

    /**
     * Pings the server and re-connects when `mysqli.reconnect = 1`
     *
     * @return bool
     */
    public function ping()
    {
        return $this->conn->ping();
    }

    /**
     * @param list<Initializer> $initializers
     * @param array<int,mixed>  $options
     *
     * @return list<Initializer>
     */
    private function withOptions(array $initializers, array $options): array
    {
        if (count($options) !== 0) {
            $initializers[] = new Options($options);
        }

        return $initializers;
    }

    /**
     * @param list<Initializer>   $initializers
     * @param array<string,mixed> $params
     *
     * @return list<Initializer>
     */
    private function withSecure(array $initializers, array $params): array
    {
        if (
            isset($params['ssl_key']) ||
            isset($params['ssl_cert']) ||
            isset($params['ssl_ca']) ||
            isset($params['ssl_capath']) ||
            isset($params['ssl_cipher'])
        ) {
            $initializers[] = new Secure(
                $params['ssl_key']    ?? null,
                $params['ssl_cert']   ?? null,
                $params['ssl_ca']     ?? null,
                $params['ssl_capath'] ?? null,
                $params['ssl_cipher'] ?? null
            );
        }

        return $initializers;
    }

    /**
     * @param list<Initializer>   $initializers
     * @param array<string,mixed> $params
     *
     * @return list<Initializer>
     */
    private function withCharset(array $initializers, array $params): array
    {
        if (isset($params['charset'])) {
            $initializers[] = new Charset($params['charset']);
        }

        return $initializers;
    }
}
