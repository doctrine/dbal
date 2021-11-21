<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

use Doctrine\Deprecations\Deprecation;

use function microtime;

/**
 * Includes executed SQLs in a Debug Stack.
 *
 * @deprecated
 */
final class DebugStack implements SQLLogger
{
    /**
     * Executed SQL queries.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $queries = [];

    /**
     * If Debug Stack is enabled (log queries) or not.
     */
    public bool $enabled = true;

    public ?float $start = null;

    public int $currentQuery = 0;

    public function __construct()
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4967',
            'DebugStack is deprecated.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function startQuery(string $sql, array $params = [], array $types = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->start = microtime(true);

        $this->queries[++$this->currentQuery] = [
            'sql' => $sql,
            'params' => $params,
            'types' => $types,
            'executionMS' => 0,
        ];
    }

    public function stopQuery(): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->queries[$this->currentQuery]['executionMS'] = microtime(true) - $this->start;
    }
}
