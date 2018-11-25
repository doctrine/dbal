<?php

namespace Doctrine\DBAL\Driver;

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
     * @param string       $dsn
     * @param string|null  $user
     * @param string|null  $password
     * @param mixed[]|null $options
     *
     * @throws PDOException In case of an error.
     */
    public function __construct($dsn, $user = null, $password = null, ?array $options = null)
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
    public function exec(string $statement) : int
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
    public function prepare(string $sql) : Statement
    {
        try {
            return $this->createStatement(
                $this->connection->prepare($sql)
            );
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql) : ResultStatement
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
    public function quote(string $input) : string
    {
        return $this->connection->quote($input);
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(?string $name = null) : string
    {
        $lastInsertId = $this->connection->lastInsertId($name);

        if ($lastInsertId === '0') {
            // WIP regarding exceptions, see:
            // https://github.com/doctrine/dbal/pull/3335#discussion_r234381175
            throw new class ('The last statement did not return an insert ID.') extends AbstractDriverException {
            };
        }

        return $lastInsertId;
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
     */
    protected function createStatement(\PDOStatement $stmt) : PDOStatement
    {
        return new PDOStatement($stmt);
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction() : void
    {
        $this->connection->beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function commit() : void
    {
        $this->connection->commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack() : void
    {
        $this->connection->rollBack();
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
