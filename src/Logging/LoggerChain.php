<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

/**
 * Chains multiple SQLLogger.
 */
final class LoggerChain implements SQLLogger
{
    /** @var iterable<SQLLogger> */
    private $loggers = [];

    /**
     * @param iterable<SQLLogger> $loggers
     */
    public function __construct(iterable $loggers = [])
    {
        $this->loggers = $loggers;
    }

    /**
     * {@inheritdoc}
     */
    public function startQuery(string $sql, array $params = [], array $types = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->startQuery($sql, $params, $types);
        }
    }

    public function stopQuery(): void
    {
        foreach ($this->loggers as $logger) {
            $logger->stopQuery();
        }
    }
}
