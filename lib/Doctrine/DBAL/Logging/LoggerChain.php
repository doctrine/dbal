<?php

namespace Doctrine\DBAL\Logging;

/**
 * Chains multiple SQLLogger.
 *
 * @link   www.doctrine-project.org
 * @since  2.2
 * @author Christophe Coevoet <stof@notk.org>
 */
class LoggerChain implements SQLLogger
{
    /**
     * @var \Doctrine\DBAL\Logging\SQLLogger[]
     */
    private $loggers = [];

    /**
     * Adds a logger in the chain.
     *
     * @param \Doctrine\DBAL\Logging\SQLLogger $logger
     *
     * @return void
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
