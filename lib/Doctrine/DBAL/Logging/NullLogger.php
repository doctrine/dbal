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
    public function startQuery($sql, array $params = null, array $types = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {
    }
}
