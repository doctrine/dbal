<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\API;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Result;

/**
 * Interface for driver connections that support async query execution.
 *
 * Drivers implementing this interface can send queries without blocking
 * and retrieve results later, enabling parallel query execution.
 */
interface AsyncConnection
{
    /**
     * Sends a query asynchronously without waiting for results.
     *
     * @param string       $sql    The SQL query to execute
     * @param list<mixed>  $params Query parameters (positional only for async)
     *
     * @return bool True if the query was sent successfully
     *
     * @throws Exception If sending the query fails
     */
    public function sendQueryAsync(string $sql, array $params = []): bool;

    /**
     * Checks if the connection is busy processing an async query.
     */
    public function isBusy(): bool;

    /**
     * Retrieves the result of the last async query.
     *
     * This method should be called after sendQueryAsync() and after
     * confirming the connection is no longer busy.
     *
     * @throws Exception If retrieving the result fails
     */
    public function getAsyncResult(): Result;
}

