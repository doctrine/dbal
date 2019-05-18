<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\PDOException;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement;
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
    public function prepare($sql) : Statement
    {
        $this->lastInsertId = new LastInsertId();

        try {
            return new PDOSqlsrvStatement($this->conn, $sql, $this->lastInsertId);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql) : ResultStatement
    {
        try {
            $stmt = $this->prepare($sql);
            $stmt->execute();

            return $stmt;
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function exec($statement) : int
    {
        return $this->conn->exec($statement);
    }

    /**
     * {@inheritDoc}
     */
    public function quote(string $input) : string
    {
        $val = $this->conn->quote($input);

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
    public function beginTransaction() : void
    {
        $this->conn->beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function commit() : void
    {
        $this->conn->commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack() : void
    {
        $this->conn->rollBack();
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
