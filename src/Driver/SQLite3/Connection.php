<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLite3;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\NoIdentityValue;
use Override;
use SQLite3;

use function assert;
use function sprintf;

final readonly class Connection implements ConnectionInterface
{
    /** @internal The connection can be only instantiated by its driver. */
    public function __construct(private SQLite3 $connection)
    {
    }

    #[Override]
    public function prepare(string $sql): Statement
    {
        try {
            $statement = $this->connection->prepare($sql);
        } catch (\Exception $e) {
            throw Exception::new($e);
        }

        assert($statement !== false);

        return new Statement($this->connection, $statement);
    }

    #[Override]
    public function query(string $sql): Result
    {
        try {
            $result = $this->connection->query($sql);
        } catch (\Exception $e) {
            throw Exception::new($e);
        }

        assert($result !== false);

        return new Result($result, $this->connection->changes());
    }

    #[Override]
    public function quote(string $value): string
    {
        return sprintf('\'%s\'', SQLite3::escapeString($value));
    }

    #[Override]
    public function exec(string $sql): int
    {
        try {
            $this->connection->exec($sql);
        } catch (\Exception $e) {
            throw Exception::new($e);
        }

        return $this->connection->changes();
    }

    #[Override]
    public function lastInsertId(): int
    {
        $value = $this->connection->lastInsertRowID();
        if ($value === 0) {
            throw NoIdentityValue::new();
        }

        return $value;
    }

    #[Override]
    public function beginTransaction(): void
    {
        $this->exec('BEGIN');
    }

    #[Override]
    public function commit(): void
    {
        $this->exec('COMMIT');
    }

    #[Override]
    public function rollBack(): void
    {
        $this->exec('ROLLBACK');
    }

    #[Override]
    public function getNativeConnection(): SQLite3
    {
        return $this->connection;
    }

    #[Override]
    public function getServerVersion(): string
    {
        return SQLite3::version()['versionString'];
    }
}
