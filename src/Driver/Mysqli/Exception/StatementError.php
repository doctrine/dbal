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
        /** @var non-empty-string $sqlstate */
        $sqlstate = $statement->sqlstate;
        return new self($statement->error, $sqlstate, $statement->errno);
    }

    public static function upcast(mysqli_sql_exception $exception): self
    {
        $p = new ReflectionProperty(mysqli_sql_exception::class, 'sqlstate');
        /** @var non-empty-string $sqlstate */
        $sqlstate = $p->getValue($exception);

        return new self($exception->getMessage(), $sqlstate, $exception->getCode(), $exception);
    }
}
