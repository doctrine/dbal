<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

/**
 * A SQL logger that does nothing.
 *
 * @codeCoverageIgnore
 */
final class NullLogger implements SQLLogger
{
    /**
     * {@inheritdoc}
     */
    public function startQuery(string $sql, ?array $params = [], ?array $types = []): void
    {
    }

    public function stopQuery(): void
    {
    }
}
