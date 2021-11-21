<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

use Doctrine\Deprecations\Deprecation;

/**
 * Chains multiple SQLLogger.
 *
 * @deprecated
 */
final class LoggerChain implements SQLLogger
{
    /** @var iterable<SQLLogger> */
    private iterable $loggers;

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
