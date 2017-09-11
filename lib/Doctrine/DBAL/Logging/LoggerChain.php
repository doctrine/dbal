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
    public function addLogger(SQLLogger $logger)
    {
        $this->loggers[] = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        foreach ($this->loggers as $logger) {
            $logger->startQuery($sql, $params, $types);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {
        foreach ($this->loggers as $logger) {
            $logger->stopQuery();
        }
    }
}
