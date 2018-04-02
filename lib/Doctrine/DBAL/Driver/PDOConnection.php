<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\ParameterType;
use PDO;

/**
 * PDO implementation of the Connection interface.
 *
 * Used by all PDO-based drivers.
 */
class PDOConnection implements Connection, ServerInfoAwareConnection
{
    /** @var PDO */
    private $connection;

    /**
     * @param string      $dsn
     * @param string|null $user
     * @param string|null $password
     * @param array|null  $options
     *
     * @throws PDOException in case of an error.
     */
    public function __construct($dsn, $user = null, $password = null, array $options = null)
    {
        try {
            $this->connection = new PDO($dsn, $user, $password, $options);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
    {
        try {
            return $this->connection->exec($statement);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        return $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($prepareString)
    {
        try {
            return $this->createStatement(
                $this->connection->prepare($prepareString)
            );
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql)
    {
        try {
            return $this->createStatement(
                $this->connection->query($sql)
            );
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function quote($input, $type = ParameterType::STRING)
    {
        return $this->connection->quote($input, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        return $this->connection->lastInsertId($name);
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }

    /**
     * Creates a wrapped statement
     *
     * @param \PDOStatement $stmt
     * @return PDOStatement
     */
    protected function createStatement(\PDOStatement $stmt) : PDOStatement
    {
        return new PDOStatement($stmt);
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack()
    {
        return $this->connection->rollBack();
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode()
    {
        return $this->connection->errorCode();
    }

    /**
     * {@inheritDoc}
     */
    public function errorInfo()
    {
        return $this->connection->errorInfo();
    }

    public function getWrappedConnection() : PDO
    {
        return $this->connection;
    }
}
