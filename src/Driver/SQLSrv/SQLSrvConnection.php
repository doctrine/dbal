<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\Result as ResultInterface;
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

    public function getServerVersion(): string
    {
        $serverInfo = sqlsrv_server_info($this->conn);

        return $serverInfo['SQLServerVersion'];
    }

    public function prepare(string $sql): DriverStatement
    {
        return new SQLSrvStatement($this->conn, $sql, $this->lastInsertId);
    }

    public function query(string $sql): ResultInterface
    {
        return $this->prepare($sql)->execute();
    }

    public function quote(string $input): string
    {
        return "'" . str_replace("'", "''", $input) . "'";
    }

    public function exec(string $statement): int
    {
        $stmt = sqlsrv_query($this->conn, $statement);

        if ($stmt === false) {
            throw SQLSrvException::fromSqlSrvErrors();
        }

        $rowsAffected = sqlsrv_rows_affected($stmt);

        if ($rowsAffected === false) {
            throw SQLSrvException::fromSqlSrvErrors();
        }

        return $rowsAffected;
    }

    public function lastInsertId(?string $name = null): string
    {
        if ($name !== null) {
            $result = $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?')
                ->execute([$name]);
        } else {
            $result = $this->query('SELECT @@IDENTITY');
        }

        return $result->fetchOne();
    }

    public function beginTransaction(): void
    {
        if (! sqlsrv_begin_transaction($this->conn)) {
            throw SQLSrvException::fromSqlSrvErrors();
        }
    }

    public function commit(): void
    {
        if (! sqlsrv_commit($this->conn)) {
            throw SQLSrvException::fromSqlSrvErrors();
        }
    }

    public function rollBack(): void
    {
        if (! sqlsrv_rollback($this->conn)) {
            throw SQLSrvException::fromSqlSrvErrors();
        }
    }
}
