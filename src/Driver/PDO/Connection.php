<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\IdentityColumnsNotSupported;
use Doctrine\DBAL\Driver\Exception\NoIdentityValue;
use PDO;
use PDOException;
use PDOStatement;

use function assert;

final class Connection implements ConnectionInterface
{
    /** @internal The connection can be only instantiated by its driver. */
    public function __construct(private readonly PDO $connection)
    {
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

    public function prepare(string $sql): Statement
    {
        try {
            $stmt = $this->connection->prepare($sql);
            assert($stmt instanceof PDOStatement);

            return new Statement($stmt);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function query(string $sql): Result
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

    public function lastInsertId(): int|string
    {
        try {
            $value = $this->connection->lastInsertId();
        } catch (PDOException $exception) {
            assert($exception->errorInfo !== null);
            [$sqlState] = $exception->errorInfo;

            // if the PDO driver does not support this capability, PDO::lastInsertId() triggers an IM001 SQLSTATE
            // see https://www.php.net/manual/en/pdo.lastinsertid.php
            if ($sqlState === 'IM001') {
                throw IdentityColumnsNotSupported::new();
            }

            // PDO PGSQL throws a 'lastval is not yet defined in this session' error when no identity value is
            // available, with SQLSTATE 55000 'Object Not In Prerequisite State'
            if ($sqlState === '55000' && $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                throw NoIdentityValue::new($exception);
            }

            throw Exception::new($exception);
        }

        // pdo_mysql & pdo_sqlite return '0', pdo_sqlsrv returns '' or false depending on the PHP version
        if ($value === '0' || $value === '' || $value === false) {
            throw NoIdentityValue::new();
        }

        return $value;
    }

    public function beginTransaction(): void
    {
        try {
            $this->connection->beginTransaction();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function commit(): void
    {
        try {
            $this->connection->commit();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function rollBack(): void
    {
        try {
            $this->connection->rollBack();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function getNativeConnection(): PDO
    {
        return $this->connection;
    }
}
