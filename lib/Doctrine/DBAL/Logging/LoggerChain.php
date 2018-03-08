<?php

namespace Doctrine\DBAL\Logging;

/**
 * Chains multiple SQLLogger.
 */
final class LoggerChain implements SQLLogger
{
    /** @var SQLLogger[] */
    private $loggers = [];

    /**
     * Adds a logger in the chain.
     */
    public function addLogger(SQLLogger $logger) : void
    {
        $this->loggers[] = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function startQuery(string $sql, array $params = [], array $types = []) : void
    {
        foreach ($this->loggers as $logger) {
            $logger->startQuery($sql, $params, $types);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery() : void
    {
        foreach ($this->loggers as $logger) {
            $logger->stopQuery();
        }
    }
}
