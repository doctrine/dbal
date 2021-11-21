<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Psr\Log\LoggerInterface;

final class Connection implements ConnectionInterface
{
    private ConnectionInterface $connection;

    private LoggerInterface $logger;

    /**
     * @internal This connection can be only instantiated by its driver.
     */
    public function __construct(ConnectionInterface $connection, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->logger     = $logger;
    }

    public function __destruct()
    {
        $this->logger->info('Disconnecting');
    }

    public function prepare(string $sql): DriverStatement
    {
        return new Statement(
            $this->connection->prepare($sql),
            $this->logger,
            $sql
        );
    }

    public function query(string $sql): Result
    {
        $this->logger->debug('Executing query: {sql}', ['sql' => $sql]);

        return $this->connection->query($sql);
    }

    public function quote(string $value): string
    {
        return $this->connection->quote($value);
    }

    public function exec(string $sql): int
    {
        $this->logger->debug('Executing statement: {sql}', ['sql' => $sql]);

        return $this->connection->exec($sql);
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->logger->debug('Beginning transaction');

        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->logger->debug('Committing transaction');

        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->logger->debug('Rolling back transaction');

        $this->connection->rollBack();
    }

    public function getServerVersion(): string
    {
        return $this->connection->getServerVersion();
    }
}
