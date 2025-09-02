<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Psr\Log\LoggerInterface;

final class Connection extends AbstractConnectionMiddleware
{
    /** @internal This connection can be only instantiated by its driver. */
    public function __construct(
        ConnectionInterface $connection,
        private readonly LoggerInterface $logger,
        private readonly LogLevelConfig $logLevelConfig,
    ) {
        parent::__construct($connection);
    }

    public function __destruct()
    {
        $this->logger->log(
            $this->logLevelConfig->getLevel(LogMessage::DISCONNECT),
            'Disconnecting',
        );
    }

    public function prepare(string $sql): DriverStatement
    {
        return new Statement(
            parent::prepare($sql),
            $this->logger,
            $sql,
            $this->logLevelConfig,
        );
    }

    public function query(string $sql): Result
    {
        $this->logger->log(
            $this->logLevelConfig->getLevel(LogMessage::QUERY),
            'Executing query: {sql}',
            ['sql' => $sql],
        );

        return parent::query($sql);
    }

    public function exec(string $sql): int|string
    {
        $this->logger->log(
            $this->logLevelConfig->getLevel(LogMessage::EXECUTE),
            'Executing statement: {sql}',
            ['sql' => $sql],
        );

        return parent::exec($sql);
    }

    public function beginTransaction(): void
    {
        $this->logger->log(
            $this->logLevelConfig->getLevel(LogMessage::BEGIN_TRANSACTION),
            'Beginning transaction',
        );

        parent::beginTransaction();
    }

    public function commit(): void
    {
        $this->logger->log(
            $this->logLevelConfig->getLevel(LogMessage::COMMIT),
            'Committing transaction',
        );

        parent::commit();
    }

    public function rollBack(): void
    {
        $this->logger->log(
            $this->logLevelConfig->getLevel(LogMessage::ROLL_BACK),
            'Rolling back transaction',
        );

        parent::rollBack();
    }
}
