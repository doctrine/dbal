<?php

namespace Doctrine\DBAL\Logging;

use Psr\Log\LoggerInterface;

/**
 * Logs every query as a debug message
 */
final class PsrAdapter implements SQLLogger
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        $this->logger->debug($sql, [
            'params' => $params,
            'types' => $types,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function stopQuery()
    {
    }
}
