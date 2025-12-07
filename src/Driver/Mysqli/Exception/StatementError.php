<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\AbstractException;
use mysqli_sql_exception;
use mysqli_stmt;
use ReflectionProperty;

use function assert;
use function is_string;

/** @internal */
final class StatementError extends AbstractException
{
    public static function new(mysqli_stmt $statement): self
    {
        $sqlstate = $statement->sqlstate;
        assert($sqlstate !== '');

        return new self($statement->error, $sqlstate, $statement->errno);
    }

    public static function upcast(mysqli_sql_exception $exception): self
    {
        $p        = new ReflectionProperty(mysqli_sql_exception::class, 'sqlstate');
        $sqlstate = $p->getValue($exception);
        assert(is_string($sqlstate) && $sqlstate !== '');

        return new self($exception->getMessage(), $sqlstate, $exception->getCode(), $exception);
    }
}
