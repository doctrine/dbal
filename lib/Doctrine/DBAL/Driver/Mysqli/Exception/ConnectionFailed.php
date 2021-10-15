<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Mysqli\MysqliException;
use mysqli;
use mysqli_sql_exception;
use ReflectionProperty;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class ConnectionFailed extends MysqliException
{
    public static function new(mysqli $connection): self
    {
        return new self($connection->connect_error, 'HY000', $connection->connect_errno);
    }

    public static function upcast(mysqli_sql_exception $exception): self
    {
        $p = new ReflectionProperty(mysqli_sql_exception::class, 'sqlstate');
        $p->setAccessible(true);

        return new self($exception->getMessage(), $p->getValue($exception), $exception->getCode(), $exception);
    }
}
