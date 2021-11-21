<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\Types\Type;

/**
 * Interface for SQL loggers.
 *
 * @deprecated Use {@link \Doctrine\DBAL\Logging\Middleware} or implement
 *            {@link \Doctrine\DBAL\Driver\Middleware} instead.
 */
interface SQLLogger
{
    /**
     * Logs a SQL statement somewhere.
     *
     * @param string                                                               $sql    The SQL to be executed.
     * @param list<mixed>|array<string, mixed>                                     $params Statement parameters
     * @param array<int, Type|int|string|null>|array<string, Type|int|string|null> $types  Parameter types
     */
    public function startQuery(string $sql, array $params = [], array $types = []): void;

    /**
     * Marks the last started query as stopped. This can be used for timing of queries.
     */
    public function stopQuery(): void;
}
