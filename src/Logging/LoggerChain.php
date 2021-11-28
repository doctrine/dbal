<?php

namespace Doctrine\DBAL\Logging;

use Doctrine\Deprecations\Deprecation;

/**
 * Chains multiple SQLLogger.
 *
 * @deprecated
 */
class LoggerChain implements SQLLogger
{
    /** @var iterable<SQLLogger> */
    private $loggers;

    /**
     * @param iterable<SQLLogger> $loggers
     */
    public function __construct(iterable $loggers = [])
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4967',
            'LoggerChain is deprecated'
        );

        $this->loggers = $loggers;
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
