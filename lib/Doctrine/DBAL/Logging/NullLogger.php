<?php

namespace Doctrine\DBAL\Logging;

/**
 * A SQL logger that does nothing.
 */
final class NullLogger implements SQLLogger
{
    /**
     * {@inheritdoc}
     */
    public function startQuery(string $sql, ?array $params = [], ?array $types = []) : void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery() : void
    {
    }
}
