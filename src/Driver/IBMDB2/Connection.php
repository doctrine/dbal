<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\IBMDB2\Exception\ConnectionError;
use Doctrine\DBAL\Driver\IBMDB2\Exception\ConnectionFailed;
use Doctrine\DBAL\Driver\IBMDB2\Exception\PrepareFailed;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use stdClass;

use function assert;
use function db2_autocommit;
use function db2_commit;
use function db2_connect;
use function db2_escape_string;
use function db2_exec;
use function db2_last_insert_id;
use function db2_num_rows;
use function db2_pconnect;
use function db2_prepare;
use function db2_rollback;
use function db2_server_info;
use function error_get_last;

use const DB2_AUTOCOMMIT_OFF;
use const DB2_AUTOCOMMIT_ON;

final class Connection implements ConnectionInterface
{
    /** @var resource */
    private $conn;

    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param array<string,mixed> $driverOptions
     *
     * @throws Exception
     */
    public function __construct(
        string $database,
        bool $persistent,
        string $username,
        string $password,
        array $driverOptions = []
    ) {
        if ($persistent) {
            $conn = db2_pconnect($database, $username, $password, $driverOptions);
        } else {
            $conn = db2_connect($database, $username, $password, $driverOptions);
        }

        if ($conn === false) {
            throw ConnectionFailed::new();
        }

        $this->conn = $conn;
    }

    public function getServerVersion(): string
    {
        $serverInfo = db2_server_info($this->conn);
        assert($serverInfo instanceof stdClass);

        return $serverInfo->DBMS_VER;
    }

    public function prepare(string $sql): DriverStatement
    {
        $stmt = @db2_prepare($this->conn, $sql);

        if ($stmt === false) {
            throw PrepareFailed::new(error_get_last());
        }

        return new Statement($stmt);
    }

    public function query(string $sql): ResultInterface
    {
        return $this->prepare($sql)->execute();
    }

    public function quote(string $value): string
    {
        return "'" . db2_escape_string($value) . "'";
    }

    public function exec(string $sql): int
    {
        $stmt = @db2_exec($this->conn, $sql);

        if ($stmt === false) {
            throw ConnectionError::new($this->conn);
        }

        return db2_num_rows($stmt);
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId()
    {
        $lastInsertId = db2_last_insert_id($this->conn);

        if ($lastInsertId === null) {
            throw Exception\NoIdentityValue::new();
        }

        return $lastInsertId;
    }

    public function beginTransaction(): void
    {
        if (db2_autocommit($this->conn, DB2_AUTOCOMMIT_OFF) !== true) {
            throw ConnectionError::new($this->conn);
        }
    }

    public function commit(): void
    {
        if (! db2_commit($this->conn)) {
            throw ConnectionError::new($this->conn);
        }

        if (db2_autocommit($this->conn, DB2_AUTOCOMMIT_ON) !== true) {
            throw ConnectionError::new($this->conn);
        }
    }

    public function rollBack(): void
    {
        if (! db2_rollback($this->conn)) {
            throw ConnectionError::new($this->conn);
        }

        if (db2_autocommit($this->conn, DB2_AUTOCOMMIT_ON) !== true) {
            throw ConnectionError::new($this->conn);
        }
    }
}
