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

class DBALException extends Exception
{
    /**
     * @param string  $sql
     * @param mixed[] $params
     *
     * @return self
     */
    public static function driverExceptionDuringQuery(Driver $driver, Throwable $driverEx, $sql, array $params = [])
    {
        $msg = "An exception occurred while executing '" . $sql . "'";
        if ($params) {
            $msg .= ' with params ' . self::formatParameters($params);
        }
        $msg .= ":\n\n" . $driverEx->getMessage();

        return static::wrapException($driver, $driverEx, $msg);
    }

    /**
     * @return self
     */
    public static function driverException(Driver $driver, Throwable $driverEx)
    {
        return static::wrapException($driver, $driverEx, 'An exception occurred in driver: ' . $driverEx->getMessage());
    }

    /**
     * @return self
     */
    private static function wrapException(Driver $driver, Throwable $driverEx, $msg)
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
     * @param mixed[] $params
     *
     * @return string
     */
    private static function formatParameters(array $params)
    {
        return '[' . implode(', ', array_map(static function ($param) {
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
