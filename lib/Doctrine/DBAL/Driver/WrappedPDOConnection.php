<?php
declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\ParameterType;
use PDO;
use function assert;
use function func_get_args;

final class WrappedPDOConnection implements Connection, ServerInfoAwareConnection
{
    /** @var PDO */
    private $connection;

    private function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public static function fromInstance(PDO $connection) : self
    {
        try {
            $connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class, []]);
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return new self($connection);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    public function setAttribute(int $attribute, $value) : bool
    {
        return $this->connection->setAttribute($attribute, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($prepareString)
    {
        try {
            $stmt = $this->connection->prepare($prepareString);
            assert($stmt instanceof PDOStatement);

            return $stmt;
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        $args = func_get_args();

        try {
            $stmt = $this->connection->query(...$args);
            assert($stmt instanceof PDOStatement);

            return $stmt;
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
    public function lastInsertId($name = null)
    {
        try {
            if ($name === null) {
                return $this->connection->lastInsertId();
            }

            return $this->connection->lastInsertId($name);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack()
    {
        return $this->connection->rollBack();
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return $this->connection->errorCode();
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return $this->connection->errorInfo();
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
    public function requiresQueryForServerVersion()
    {
        return false;
    }
}
