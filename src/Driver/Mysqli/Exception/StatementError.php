<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\AbstractException;
use mysqli_sql_exception;
use mysqli_stmt;
use ReflectionProperty;

/** @internal */
final class StatementError extends AbstractException
{
    public static function new(mysqli_stmt $statement): self
    {
        return new self($statement->error, $statement->sqlstate, $statement->errno);
    }

    public static function upcast(mysqli_sql_exception $exception): self
    {
        $p = new ReflectionProperty(mysqli_sql_exception::class, 'sqlstate');

        return new self($exception->getMessage(), $p->getValue($exception), $exception->getCode(), $exception);
    }
}
