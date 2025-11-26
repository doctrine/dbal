<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Async\Exception;

use Doctrine\DBAL\Exception;

/**
 * Exception thrown when async query execution is not supported.
 *
 * This can happen when:
 * - PHP version is below 8.1
 * - The driver does not support async queries (e.g., PDO)
 * - Async queries are attempted inside a transaction
 */
class AsyncNotSupported extends Exception
{
    public static function phpVersionTooOld(): self
    {
        return new self(
            'Async query execution requires PHP 8.1 or higher. Current version: ' . PHP_VERSION
        );
    }

    public static function driverNotSupported(string $driverClass): self
    {
        return new self(
            sprintf(
                'The driver "%s" does not support async queries. '
                . 'Only pgsql and mysqli drivers support async execution.',
                $driverClass
            )
        );
    }

    public static function notAllowedInTransaction(): self
    {
        return new self(
            'Async queries cannot be executed inside a transaction. '
            . 'Each async query runs on a separate connection.'
        );
    }

    public static function emptyQueryBatch(): self
    {
        return new self('Cannot execute an empty batch of async queries.');
    }
}

