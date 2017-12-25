<?php

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\ParameterType;

/**
 * SQL Server implementation for the Connection interface.
 *
 * @since 2.3
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class SQLSrvConnection implements Connection, ServerInfoAwareConnection
{
    /**
     * @var resource
     */
    protected $conn;

    /**
     * @var \Doctrine\DBAL\Driver\SQLSrv\LastInsertId
     */
    protected $lastInsertId;

    /**
     * @param string $serverName
     * @param array  $connectionOptions
     *
     * @throws \Doctrine\DBAL\Driver\SQLSrv\SQLSrvException
     */
    public function __construct($serverName, $connectionOptions)
    {
        if ( ! sqlsrv_configure('WarningsReturnAsErrors', 0)) {
            throw SQLSrvException::fromSqlSrvErrors();
        }

        $this->conn = sqlsrv_connect($serverName, $connectionOptions);
        if ( ! $this->conn) {
            throw SQLSrvException::fromSqlSrvErrors();
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
    public function prepare($sql)
    {
        return new SQLSrvStatement($this->conn, $sql, $this->lastInsertId);
    }

    /**
     * {@inheritDoc}
     */
    public function query()
    {
        $args = func_get_args();
        $sql = $args[0];
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    /**
     * {@inheritDoc}
     * @license New BSD, code from Zend Framework
     */
    public function quote($value, $type = ParameterType::STRING)
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_float($value)) {
            return sprintf('%F', $value);
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * {@inheritDoc}
     */
    public function exec($statement)
    {
        $stmt = sqlsrv_query($this->conn, $statement);

        if (false === $stmt) {
            throw SQLSrvException::fromSqlSrvErrors();
        }

        return sqlsrv_rows_affected($stmt);
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name !== null) {
            $stmt = $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?');
            $stmt->execute([$name]);

            return $stmt->fetchColumn();
        }

        return $this->lastInsertId->getId();
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction()
    {
        if ( ! sqlsrv_begin_transaction($this->conn)) {
            throw SQLSrvException::fromSqlSrvErrors();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function commit()
    {
        if ( ! sqlsrv_commit($this->conn)) {
            throw SQLSrvException::fromSqlSrvErrors();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack()
    {
        if ( ! sqlsrv_rollback($this->conn)) {
            throw SQLSrvException::fromSqlSrvErrors();
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
}
