<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Override;

abstract readonly class AbstractConnectionMiddleware implements Connection
{
    public function __construct(private Connection $wrappedConnection)
    {
    }

    #[Override]
    public function prepare(string $sql): Statement
    {
        return $this->wrappedConnection->prepare($sql);
    }

    #[Override]
    public function query(string $sql): Result
    {
        return $this->wrappedConnection->query($sql);
    }

    #[Override]
    public function quote(string $value): string
    {
        return $this->wrappedConnection->quote($value);
    }

    #[Override]
    public function exec(string $sql): int|string
    {
        return $this->wrappedConnection->exec($sql);
    }

    #[Override]
    public function lastInsertId(): int|string
    {
        return $this->wrappedConnection->lastInsertId();
    }

    #[Override]
    public function beginTransaction(): void
    {
        $this->wrappedConnection->beginTransaction();
    }

    #[Override]
    public function commit(): void
    {
        $this->wrappedConnection->commit();
    }

    #[Override]
    public function rollBack(): void
    {
        $this->wrappedConnection->rollBack();
    }

    #[Override]
    public function getServerVersion(): string
    {
        return $this->wrappedConnection->getServerVersion();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getNativeConnection()
    {
        return $this->wrappedConnection->getNativeConnection();
    }
}
