<?php

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use const SQLSRV_ERR_ERRORS;
use function rtrim;
use function sqlsrv_begin_transaction;
use function sqlsrv_commit;
use function sqlsrv_configure;
use function sqlsrv_connect;
use function sqlsrv_errors;
use function sqlsrv_query;
use function sqlsrv_rollback;
use function sqlsrv_rows_affected;
use function sqlsrv_server_info;
use function str_replace;

/**
 * SQL Server implementation for the Connection interface.
 */
class SQLSrvConnection implements Connection, ServerInfoAwareConnection
{
    /** @var resource */
    protected $conn;

    /** @var LastInsertId */
    protected $lastInsertId;

    /**
     * @param string  $serverName
     * @param mixed[] $connectionOptions
     *
     * @throws DriverException
     */
    public function __construct($serverName, $connectionOptions)
    {
        if (! sqlsrv_configure('WarningsReturnAsErrors', 0)) {
            throw self::exceptionFromSqlSrvErrors();
        }

        $this->conn = sqlsrv_connect($serverName, $connectionOptions);
        if (! $this->conn) {
            throw self::exceptionFromSqlSrvErrors();
        }
        $this->lastInsertId = new LastInsertId();
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        $serverInfo = sqlsrv_server_info($this->conn);

        return $serverInfo['SQLServerVersion'];
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(string $sql) : DriverStatement
    {
        return new SQLSrvStatement($this->conn, $sql, $this->lastInsertId);
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql) : ResultStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    /**
     * {@inheritDoc}
     */
    public function quote(string $value) : string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * {@inheritDoc}
     */
    public function exec(string $statement) : int
    {
        $stmt = sqlsrv_query($this->conn, $statement);

        if ($stmt === false) {
            throw self::exceptionFromSqlSrvErrors();
        }

        return sqlsrv_rows_affected($stmt);
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId() : string
    {
        $stmt = $this->query('SELECT @@IDENTITY');

        $result = $stmt->fetchColumn();

        if ($result === null) {
            throw DriverException::noInsertId();
        }

        return (string) $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getSequenceNumber(string $name) : string
    {
        $stmt = $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?');
        $stmt->execute([$name]);

        $result = $stmt->fetchColumn();

        if ($result === false) {
            throw DriverException::noSuchSequence($name);
        }

        return (string) $result;
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction() : void
    {
        if (! sqlsrv_begin_transaction($this->conn)) {
            throw self::exceptionFromSqlSrvErrors();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function commit() : void
    {
        if (! sqlsrv_commit($this->conn)) {
            throw self::exceptionFromSqlSrvErrors();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack() : void
    {
        if (! sqlsrv_rollback($this->conn)) {
            throw self::exceptionFromSqlSrvErrors();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode()
    {
        $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        if ($errors) {
            return $errors[0]['code'];
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function errorInfo()
    {
        return sqlsrv_errors(SQLSRV_ERR_ERRORS);
    }

    /**
     * Helper method to turn sql server errors into exception.
     */
    public static function exceptionFromSqlSrvErrors() : DriverException
    {
        $errors    = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        $message   = '';
        $sqlState  = null;
        $errorCode = null;

        foreach ($errors as $error) {
            $message .= 'SQLSTATE [' . $error['SQLSTATE'] . ', ' . $error['code'] . ']: ' . $error['message'] . "\n";

            if ($sqlState === null) {
                $sqlState = $error['SQLSTATE'];
            }

            if ($errorCode !== null) {
                continue;
            }

            $errorCode = $error['code'];
        }
        if (! $message) {
            $message = 'SQL Server error occurred but no error message was retrieved from driver.';
        }

        return new DriverException(rtrim($message), $sqlState, $errorCode);
    }
}
