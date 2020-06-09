<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\Types\Type;

/**
 * Interface for SQL loggers.
 */
interface SQLLogger
{
    /**
     * Logs a SQL statement somewhere.
     *
     * @param string                $sql    The SQL to be executed.
     * @param mixed[]               $params The SQL parameters.
     * @param int[]|string[]|Type[] $types  The SQL parameter types.
     */
    public function startQuery(string $sql, array $params = [], array $types = []): void;

    /**
     * Marks the last started query as stopped. This can be used for timing of queries.
     */
    public function stopQuery(): void;
}
