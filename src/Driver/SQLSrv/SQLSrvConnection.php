<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use function sqlsrv_begin_transaction;
use function sqlsrv_commit;
use function sqlsrv_configure;
use function sqlsrv_connect;
use function sqlsrv_query;
use function sqlsrv_rollback;
use function sqlsrv_rows_affected;
use function sqlsrv_server_info;
use function str_replace;

/**
 * SQL Server implementation for the Connection interface.
 */
final class SQLSrvConnection implements ServerInfoAwareConnection
{
    /** @var resource */
    private $conn;

    /** @var LastInsertId */
    private $lastInsertId;

    /**
     * @param array<string, mixed> $connectionOptions
     *
     * @throws SQLSrvException
     */
    public function __construct(string $serverName, array $connectionOptions)
    {
        if (! sqlsrv_configure('WarningsReturnAsErrors', 0)) {
            throw SQLSrvException::fromSqlSrvErrors();
        }

        $conn = sqlsrv_connect($serverName, $connectionOptions);

        if ($conn === false) {
            throw SQLSrvException::fromSqlSrvErrors();
        }

        $this->conn         = $conn;
        $this->lastInsertId = new LastInsertId();
    }

    public function getServerVersion() : string
    {
        $serverInfo = sqlsrv_server_info($this->conn);

        return $serverInfo['SQLServerVersion'];
    }

    public function prepare(string $sql) : DriverStatement
    {
        return new SQLSrvStatement($this->conn, $sql, $this->lastInsertId);
    }

    public function query(string $sql) : ResultStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    public function quote(string $input) : string
    {
        return "'" . str_replace("'", "''", $input) . "'";
    }

    public function exec(string $statement) : int
    {
        $stmt = sqlsrv_query($this->conn, $statement);

        if ($stmt === false) {
            throw SQLSrvException::fromSqlSrvErrors();
        }

        $rowsAffected = sqlsrv_rows_affected($stmt);

        if ($rowsAffected === false) {
            throw SQLSrvException::fromSqlSrvErrors();
        }

        if (stripos($statement, 'INSERT INTO ') === 0) {
            if ($lastInsertStmt = sqlsrv_query($this->conn, 'SELECT SCOPE_IDENTITY() AS LastInsertId;')) {
                sqlsrv_fetch($lastInsertStmt);
                $this->lastInsertId->setId(sqlsrv_get_field($lastInsertStmt, 0));
            }
        }

        return $rowsAffected;
    }

    public function lastInsertId(?string $name = null): string
    {
        if ($name !== null) {
            $stmt = $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?');
            $stmt->execute([$name]);

            return $stmt->fetchColumn();
        }

        return $this->lastInsertId->getId();
    }

    public function beginTransaction() : void
    {
        if (! sqlsrv_begin_transaction($this->conn)) {
            throw SQLSrvException::fromSqlSrvErrors();
        }
    }

    public function commit() : void
    {
        if (! sqlsrv_commit($this->conn)) {
            throw SQLSrvException::fromSqlSrvErrors();
        }
    }

    public function rollBack() : void
    {
        if (! sqlsrv_rollback($this->conn)) {
            throw SQLSrvException::fromSqlSrvErrors();
        }
    }
}
