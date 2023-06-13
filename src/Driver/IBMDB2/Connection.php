<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\NoIdentityValue;
use Doctrine\DBAL\Driver\IBMDB2\Exception\ConnectionError;
use Doctrine\DBAL\Driver\IBMDB2\Exception\PrepareFailed;
use Doctrine\DBAL\Driver\IBMDB2\Exception\StatementError;
use stdClass;

use function assert;
use function db2_autocommit;
use function db2_commit;
use function db2_escape_string;
use function db2_exec;
use function db2_last_insert_id;
use function db2_num_rows;
use function db2_prepare;
use function db2_rollback;
use function db2_server_info;
use function error_get_last;

use const DB2_AUTOCOMMIT_OFF;
use const DB2_AUTOCOMMIT_ON;

final class Connection implements ConnectionInterface
{
    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param resource $connection
     */
    public function __construct(private readonly mixed $connection)
    {
    }

    public function getServerVersion(): string
    {
        $serverInfo = db2_server_info($this->connection);
        assert($serverInfo instanceof stdClass);

        return $serverInfo->DBMS_VER;
    }

    public function prepare(string $sql): Statement
    {
        $stmt = @db2_prepare($this->connection, $sql);

        if ($stmt === false) {
            throw PrepareFailed::new(error_get_last());
        }

        return new Statement($stmt);
    }

    public function query(string $sql): Result
    {
        return $this->prepare($sql)->execute();
    }

    public function quote(string $value): string
    {
        return "'" . db2_escape_string($value) . "'";
    }

    public function exec(string $sql): int|string
    {
        $stmt = @db2_exec($this->connection, $sql);

        if ($stmt === false) {
            throw StatementError::new();
        }

        $numRows = db2_num_rows($stmt);

        if ($numRows === false) {
            throw StatementError::new();
        }

        return $numRows;
    }

    public function lastInsertId(): string
    {
        $lastInsertId = db2_last_insert_id($this->connection);

        if ($lastInsertId === null) {
            throw NoIdentityValue::new();
        }

        return $lastInsertId;
    }

    public function beginTransaction(): void
    {
        if (db2_autocommit($this->connection, DB2_AUTOCOMMIT_OFF) !== true) {
            throw ConnectionError::new($this->connection);
        }
    }

    public function commit(): void
    {
        if (! db2_commit($this->connection)) {
            throw ConnectionError::new($this->connection);
        }

        if (db2_autocommit($this->connection, DB2_AUTOCOMMIT_ON) !== true) {
            throw ConnectionError::new($this->connection);
        }
    }

    public function rollBack(): void
    {
        if (! db2_rollback($this->connection)) {
            throw ConnectionError::new($this->connection);
        }

        if (db2_autocommit($this->connection, DB2_AUTOCOMMIT_ON) !== true) {
            throw ConnectionError::new($this->connection);
        }
    }

    /** @return resource */
    public function getNativeConnection()
    {
        return $this->connection;
    }
}
