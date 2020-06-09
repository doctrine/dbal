<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver\PDO\Result;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use PDO;

use function assert;

/**
 * PDO implementation of the Connection interface.
 *
 * Used by all PDO-based drivers.
 */
class PDOConnection implements ServerInfoAwareConnection
{
    /** @var PDO */
    private $connection;

    /**
     * @param array<int, mixed> $options
     *
     * @throws PDOException In case of an error.
     */
    public function __construct(string $dsn, string $username = '', string $password = '', array $options = [])
    {
        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    public function exec(string $statement): int
    {
        try {
            return $this->connection->exec($statement);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    public function getServerVersion(): string
    {
        return $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    public function prepare(string $sql): Statement
    {
        try {
            return $this->createStatement(
                $this->connection->prepare($sql)
            );
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    public function query(string $sql): ResultInterface
    {
        try {
            $stmt = $this->connection->query($sql);
            assert($stmt instanceof \PDOStatement);

            return new Result($stmt);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    public function quote(string $input): string
    {
        return $this->connection->quote($input);
    }

    public function lastInsertId(?string $name = null): string
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
     * Creates a wrapped statement
     */
    protected function createStatement(\PDOStatement $stmt): PDOStatement
    {
        return new PDOStatement($stmt);
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
    }

    public function getWrappedConnection(): PDO
    {
        return $this->connection;
    }
}
