<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\AbstractException;
use mysqli;
use mysqli_sql_exception;
use ReflectionProperty;

/** @internal */
final class ConnectionError extends AbstractException
{
    public static function new(mysqli $connection): self
    {
        /** @var string $sqlstate */ 
        $sqlstate = $connection->sqlstate; // We need a intermediate variable, so phpstan could infer the type returned
        return new self($connection->error, $sqlstate, $connection->errno);
    }

    public static function upcast(mysqli_sql_exception $exception): self
    {
        $p = new ReflectionProperty(mysqli_sql_exception::class, 'sqlstate');
        /** @var string $sqlstate */
        $sqlstate = $p->getValue($exception); // Intermediate variable for phpstan type inference

        return new self($exception->getMessage(), $sqlstate, $exception->getCode(), $exception);
    }
}
