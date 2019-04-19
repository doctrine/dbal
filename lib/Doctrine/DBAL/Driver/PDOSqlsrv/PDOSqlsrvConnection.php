<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\ParameterType;
use function func_get_args;
use function strpos;
use function substr;

/**
 * Sqlsrv Connection implementation.
 */
class PDOSqlsrvConnection implements Connection, ServerInfoAwareConnection
{
    /** @var PDOConnection */
    protected $conn;

    /** @var LastInsertId */
    protected $lastInsertId;

    /**
     * {@inheritdoc}
     */
    public function __construct($dsn, $user = null, $password = null, ?array $options = null)
    {
        $this->conn         = new PDOConnection($dsn, $user, $password, $options);
        $this->lastInsertId = new LastInsertId();
    }

    /**
     * {@inheritDoc}
     */
    public function prepare($sql)
    {
        $this->lastInsertId = new LastInsertId();

        return new PDOSqlsrvStatement($this->conn, $sql, $this->lastInsertId);
    }

    /**
     * {@inheritDoc}
     */
    public function query()
    {
        $args = func_get_args();
        $sql  = $args[0];
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    /**
     * {@inheritDoc}
     */
    public function exec($statement)
    {
        return $this->conn->exec($statement);
    }

    /**
     * {@inheritDoc}
     */
    public function quote($value, $type = ParameterType::STRING)
    {
        $val = $this->conn->quote($value, $type);

        // Fix for a driver version terminating all values with null byte
        if (strpos($val, "\0") !== false) {
            $val = substr($val, 0, -1);
        }

        return $val;
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId($name = null)
    {
        if ($this->lastInsertId->getId()) {
            return $this->lastInsertId->getId();
        }

        if ($name === null) {
            return $this->conn->lastInsertId();
        }

        $stmt = $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?');
        $stmt->execute([$name]);

        return (string) $stmt->fetchColumn();
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction()
    {
        return $this->conn->beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function commit()
    {
        return $this->conn->commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack()
    {
        return $this->conn->rollBack();
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode()
    {
        return $this->conn->errorCode();
    }

    /**
     * {@inheritDoc}
     */
    public function errorInfo()
    {
        return $this->conn->errorInfo();
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        return $this->conn->getServerVersion();
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return $this->conn->requiresQueryForServerVersion();
    }
}
