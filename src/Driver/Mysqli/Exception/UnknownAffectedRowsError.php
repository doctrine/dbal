<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\AbstractException;
use mysqli;
use mysqli_sql_exception;
use mysqli_stmt;
use ReflectionProperty;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class UnknownAffectedRowsError extends AbstractException
{
    public static function new(mysqli|mysqli_stmt $executedResource): self
    {
        return new self($executedResource->error, $executedResource->sqlstate, $executedResource->errno);
    }

    public static function upcast(mysqli_sql_exception $exception): self
    {
        $p = new ReflectionProperty(mysqli_sql_exception::class, 'sqlstate');

        return new self($exception->getMessage(), $p->getValue($exception), (int) $exception->getCode(), $exception);
    }
}
