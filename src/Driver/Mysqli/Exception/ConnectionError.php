<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\AbstractException;
use mysqli;
use mysqli_sql_exception;
use ReflectionProperty;

use function assert;
use function is_string;

/** @internal */
final class ConnectionError extends AbstractException
{
    public static function new(mysqli $connection): self
    {
        $sqlstate = $connection->sqlstate;

        return new self($connection->error, $sqlstate, $connection->errno);
    }

    public static function upcast(mysqli_sql_exception $exception): self
    {
        $p        = new ReflectionProperty(mysqli_sql_exception::class, 'sqlstate');
        $sqlstate = $p->getValue($exception);
        assert(is_string($sqlstate)); // Intermediate variable for phpstan type inference

        return new self($exception->getMessage(), $sqlstate, $exception->getCode(), $exception);
    }
}
