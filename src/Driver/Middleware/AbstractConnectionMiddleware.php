<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use LogicException;

use function get_class;
use function method_exists;
use function sprintf;

abstract class AbstractConnectionMiddleware implements Connection
{
    private Connection $wrappedConnection;

    public function __construct(Connection $wrappedConnection)
    {
        $this->wrappedConnection = $wrappedConnection;
    }

    public function prepare(string $sql): Statement
    {
        return $this->wrappedConnection->prepare($sql);
    }

    public function query(string $sql): Result
    {
        return $this->wrappedConnection->query($sql);
    }

    public function quote(string $value): string
    {
        return $this->wrappedConnection->quote($value);
    }

    public function exec(string $sql): int
    {
        return $this->wrappedConnection->exec($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId()
    {
        return $this->wrappedConnection->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->wrappedConnection->beginTransaction();
    }

    public function commit(): void
    {
        $this->wrappedConnection->commit();
    }

    public function rollBack(): void
    {
        $this->wrappedConnection->rollBack();
    }

    public function getServerVersion(): string
    {
        return $this->wrappedConnection->getServerVersion();
    }

    /**
     * @return resource|object
     */
    public function getNativeConnection()
    {
        if (! method_exists($this->wrappedConnection, 'getNativeConnection')) {
            throw new LogicException(sprintf(
                'The driver connection %s does not support accessing the native connection.',
                get_class($this->wrappedConnection)
            ));
        }

        return $this->wrappedConnection->getNativeConnection();
    }
}
