<?php

namespace Doctrine\DBAL;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use function array_map;
use function bin2hex;
use function implode;
use function is_resource;
use function is_string;
use function json_encode;
use function sprintf;
use function str_split;

class DBALException extends \Exception
{

    /**
     * @param \Doctrine\DBAL\Driver $driver
     * @param \Exception            $driverEx
     * @param string                $sql
     * @param array                 $params
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function driverExceptionDuringQuery(Driver $driver, \Exception $driverEx, $sql, array $params = [])
    {
        $msg = "An exception occurred while executing '".$sql."'";
        if ($params) {
            $msg .= " with params " . self::formatParameters($params);
        }
        $msg .= ":\n\n".$driverEx->getMessage();

        return static::wrapException($driver, $driverEx, $msg);
    }

    /**
     * @param \Doctrine\DBAL\Driver $driver
     * @param \Exception            $driverEx
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function driverException(Driver $driver, \Exception $driverEx)
    {
        return static::wrapException($driver, $driverEx, "An exception occurred in driver: " . $driverEx->getMessage());
    }

    /**
     * @param \Doctrine\DBAL\Driver $driver
     * @param \Exception            $driverEx
     *
     * @return \Doctrine\DBAL\DBALException
     */
    private static function wrapException(Driver $driver, \Exception $driverEx, $msg)
    {
        if ($driverEx instanceof Exception\DriverException) {
            return $driverEx;
        }
        if ($driver instanceof ExceptionConverterDriver && $driverEx instanceof Driver\DriverException) {
            return $driver->convertException($msg, $driverEx);
        }

        return new self($msg, 0, $driverEx);
    }

    /**
     * Returns a human-readable representation of an array of parameters.
     * This properly handles binary data by returning a hex representation.
     *
     * @param array $params
     *
     * @return string
     */
    private static function formatParameters(array $params)
    {
        return '[' . implode(', ', array_map(function ($param) {
            if (is_resource($param)) {
                return (string) $param;
            }
            
            $json = @json_encode($param);

            if (! is_string($json) || $json == 'null' && is_string($param)) {
                // JSON encoding failed, this is not a UTF-8 string.
                return '"\x' . implode('\x', str_split(bin2hex($param), 2)) . '"';
            }

            return $json;
        }, $params)) . ']';
    }
}
