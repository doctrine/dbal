<?php

namespace Doctrine\DBAL\Logging;

use Doctrine\Deprecations\Deprecation;

/**
 * Chains multiple SQLLogger.
 */
class LoggerChain implements SQLLogger
{
    /** @var SQLLogger[] */
    private $loggers = [];

    /**
     * @param SQLLogger[] $loggers
     */
    public function __construct(array $loggers = [])
    {
        $this->loggers = $loggers;
    }

    /**
     * Adds a logger in the chain.
     *
     * @deprecated Inject list of loggers via constructor instead
     *
     * @return void
     */
    public function addLogger(SQLLogger $logger)
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/3572',
            'LoggerChain::addLogger() is deprecated, use LoggerChain constructor instead.'
        );

        $this->loggers[] = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null)
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
