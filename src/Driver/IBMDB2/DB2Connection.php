<?php

declare(strict_types=0);

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
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

use const DB2_AUTOCOMMIT_OFF;
use const DB2_AUTOCOMMIT_ON;

final class DB2Connection implements ServerInfoAwareConnection
{
    /** @var resource */
    private $conn;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $driverOptions
     *
     * @throws DB2Exception
     */
    public function __construct(array $params, string $username, string $password, array $driverOptions = [])
    {
        if (isset($params['persistent']) && $params['persistent'] === true) {
            $conn = db2_pconnect($params['dbname'], $username, $password, $driverOptions);
        } else {
            $conn = db2_connect($params['dbname'], $username, $password, $driverOptions);
        }

        if ($conn === false) {
            throw DB2Exception::fromConnectionError();
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
            throw DB2Exception::fromStatementError();
        }

        return new DB2Statement($stmt);
    }

    public function query(string $sql): ResultInterface
    {
        return $this->prepare($sql)->execute();
    }

    public function quote(string $input): string
    {
        return "'" . db2_escape_string($input) . "'";
    }

    public function exec(string $statement): int
    {
        $stmt = @db2_exec($this->conn, $statement);

        if ($stmt === false) {
            throw DB2Exception::fromStatementError();
        }

        return db2_num_rows($stmt);
    }

    public function lastInsertId(?string $name = null): string
    {
        return db2_last_insert_id($this->conn);
    }

    public function beginTransaction(): void
    {
        if (db2_autocommit($this->conn, DB2_AUTOCOMMIT_OFF) !== true) {
            throw DB2Exception::fromConnectionError($this->conn);
        }
    }

    public function commit(): void
    {
        if (! db2_commit($this->conn)) {
            throw DB2Exception::fromConnectionError($this->conn);
        }

        if (db2_autocommit($this->conn, DB2_AUTOCOMMIT_ON) !== true) {
            throw DB2Exception::fromConnectionError($this->conn);
        }
    }

    public function rollBack(): void
    {
        if (! db2_rollback($this->conn)) {
            throw DB2Exception::fromConnectionError($this->conn);
        }

        if (db2_autocommit($this->conn, DB2_AUTOCOMMIT_ON) !== true) {
            throw DB2Exception::fromConnectionError($this->conn);
        }
    }
}
