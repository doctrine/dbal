<?php

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use mysqli;
use mysqli_sql_exception;

use function floor;
use function stripos;

final class Connection implements ServerInfoAwareConnection
{
    /**
     * Name of the option to set connection flags
     */
    public const OPTION_FLAGS = 'flags';

    /** @var mysqli */
    private $connection;

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
     */
    public function getWrappedResourceHandle(): mysqli
    {
        return $this->connection;
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name !== null) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/4687',
                'The usage of Connection::lastInsertId() with a sequence name is deprecated.'
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
}
