<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDO\Connection as PDOConnection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use PDO;

use function is_string;
use function strpos;
use function substr;

final class Connection implements ServerInfoAwareConnection
{
    /** @var PDOConnection */
    private $connection;

    public function __construct(PDOConnection $connection)
    {
        $this->connection = $connection;
    }

    public function prepare(string $sql): StatementInterface
    {
        return new Statement(
            $this->connection->prepare($sql)
        );
    }

    public function query(string $sql): Result
    {
        return $this->connection->query($sql);
    }

    /**
     * {@inheritDoc}
     */
    public function quote($value, $type = ParameterType::STRING)
    {
        $val = $this->connection->quote($value, $type);

        // Fix for a driver version terminating all values with null byte
        if (is_string($val) && strpos($val, "\0") !== false) {
            $val = substr($val, 0, -1);
        }

        return $val;
    }

    public function exec(string $statement): int
    {
        return $this->connection->exec($statement);
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name === null) {
            return $this->connection->lastInsertId($name);
        }

        return $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?')
            ->execute([$name])
            ->fetchOne();
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
    public function getServerVersion()
    {
        return $this->connection->getServerVersion();
    }

    public function getWrappedConnection(): PDO
    {
        return $this->connection->getWrappedConnection();
    }
}
