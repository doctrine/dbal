<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\Exception as ExceptionInterface;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\Deprecations\Deprecation;
use PDO;
use PDOException;
use PDOStatement;

use function assert;

final class Connection implements ServerInfoAwareConnection
{
    /** @var PDO */
    private $connection;

    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param array<int, mixed> $options
     *
     * @throws ExceptionInterface
     */
    public function __construct(string $dsn, string $username = '', string $password = '', array $options = [])
    {
        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function exec(string $sql): int
    {
        try {
            $result = $this->connection->exec($sql);

            assert($result !== false);

            return $result;
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function getServerVersion(): string
    {
        return $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * {@inheritDoc}
     *
     * @return Statement
     */
    public function prepare(string $sql): StatementInterface
    {
        try {
            $stmt = $this->connection->prepare($sql);
            assert($stmt instanceof PDOStatement);

            return $this->createStatement($stmt);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function query(string $sql): ResultInterface
    {
        try {
            $stmt = $this->connection->query($sql);
            assert($stmt instanceof PDOStatement);

            return new Result($stmt);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function quote(string $value): string
    {
        return $this->connection->quote($value);
    }

    public function lastInsertId(?string $name = null): string
    {
        try {
            if ($name === null) {
                return $this->connection->lastInsertId();
            }

            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/4687',
                'The usage of Connection::lastInsertId() with a sequence name is deprecated.'
            );

            return $this->connection->lastInsertId($name);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * Creates a wrapped statement
     */
    protected function createStatement(PDOStatement $stmt): Statement
    {
        return new Statement($stmt);
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
