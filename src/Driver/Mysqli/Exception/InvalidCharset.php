<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\AbstractException;
use mysqli_sql_exception;
use ReflectionProperty;

use function sprintf;

/** @internal */
final class InvalidCharset extends AbstractException
{
    public static function upcast(mysqli_sql_exception $exception, string $charset): self
    {
        $p = new ReflectionProperty(mysqli_sql_exception::class, 'sqlstate');

        return new self(
            sprintf('Failed to set charset "%s": %s', $charset, $exception->getMessage()),
            $p->getValue($exception),
            $exception->getCode(),
            $exception,
        );
    }
}
