<?php

namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\ArrayParameterType;

/**
 * Interface for SQL loggers.
 *
 * @deprecated Use {@see \Doctrine\DBAL\Logging\Middleware} or implement
 *            {@see \Doctrine\DBAL\Driver\Middleware} instead.
 *
 * @psalm-import-type ArrayParameterTypeOfValue from ArrayParameterType
 */
interface SQLLogger
{
    /**
     * Logs a SQL statement somewhere.
     *
     * @param string                                                                              $sql    SQL statement
     * @param list<mixed>|array<string, mixed>|null                                               $params Statement
     *                                                                                                    parameters
     * @param array<int, ArrayParameterTypeOfValue>|array<string, ArrayParameterTypeOfValue>|null $types  Parameter
     *                                                                                                    types
     *
     * @return void
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null);

    /**
     * Marks the last started query as stopped. This can be used for timing of queries.
     *
     * @return void
     */
    public function stopQuery();
}
