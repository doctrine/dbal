<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\DriverException as DriverExceptionInterface;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Exception\DriverException;
use Exception;
use Throwable;

use function array_map;
use function bin2hex;
use function implode;
use function is_resource;
use function is_string;
use function json_encode;
use function preg_replace;
use function sprintf;

/**
 * @psalm-immutable
 */
class DBALException extends Exception
{
    /**
     * @param mixed[] $params
     */
    public static function driverExceptionDuringQuery(Driver $driver, Throwable $driverEx, string $sql, array $params = []): self
    {
        $messageFormat = <<<'MESSAGE'
An exception occurred while executing "%s"%s:

%s
MESSAGE;

        $message = sprintf(
            $messageFormat,
            $sql,
            $params !== [] ? sprintf(' with params %s', self::formatParameters($params)) : '',
            $driverEx->getMessage()
        );

        return static::wrapException($driver, $driverEx, $message);
    }

    public static function driverException(Driver $driver, Throwable $driverEx): self
    {
        return static::wrapException($driver, $driverEx, sprintf('An exception occurred in driver with message: %s', $driverEx->getMessage()));
    }

    private static function wrapException(Driver $driver, Throwable $driverEx, string $msg): self
    {
        if ($driverEx instanceof DriverException) {
            return $driverEx;
        }

        if ($driver instanceof ExceptionConverterDriver && $driverEx instanceof DriverExceptionInterface) {
            return $driver->convertException($msg, $driverEx);
        }

        return new self($msg, 0, $driverEx);
    }

    /**
     * Returns a human-readable representation of an array of parameters.
     * This properly handles binary data by returning a hex representation.
     *
     * @param array<mixed, mixed> $params
     */
    private static function formatParameters(array $params): string
    {
        return '[' . implode(', ', array_map(static function ($param): string {
            if (is_resource($param)) {
                return (string) $param;
            }

            $json = @json_encode($param);

            if (! is_string($json) || $json === 'null' && is_string($param)) {
                // JSON encoding failed, this is not a UTF-8 string.
                return sprintf('"%s"', preg_replace('/.{2}/', '\\x$0', bin2hex($param)));
            }

            return $json;
        }, $params)) . ']';
    }
}
