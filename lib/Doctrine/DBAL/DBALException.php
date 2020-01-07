<?php

namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\DriverException as DriverExceptionInterface;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Exception;
use Throwable;
use function array_map;
use function bin2hex;
use function get_class;
use function gettype;
use function implode;
use function is_object;
use function is_resource;
use function is_string;
use function json_encode;
use function preg_replace;
use function spl_object_hash;
use function sprintf;

/**
 * Exception class responsible for technical errors of DBAL infrastructure
 */
class DBALException extends Exception
{
    // Exception codes. Dedicated 0-99 numbers
    public const NOT_SUPPORTED_METHOD               = 1;
    public const INVALID_PLATFORM_SPECIFIED         = 5;
    public const INVALID_PLATFORM_TYPE              = 10;
    public const INVALID_PLATFORM_VERSION_SPECIFIED = 15;
    public const INVALID_PDO_INSTANCE               = 20;
    public const DRIVER_REQUIRED                    = 25;
    public const UNKNOWN_DRIVER                     = 30;
    public const DRIVER_EXCEPTION_DURING_QUERY      = 35;
    public const DRIVER_EXCEPTION                   = 40;
    public const INVALID_WRAPPER_CLASS              = 45;
    public const INVALID_DRIVER_CLASS               = 50;
    public const INVALID_TABLE_NAME                 = 55;
    public const NO_COLUMNS_SPECIFIED_FOR_TABLE     = 60;
    public const LIMIT_OFFSET_INVALID               = 65;
    public const TYPE_ALREADY_EXISTS                = 70;
    public const UNKNOWN_COLUMN_TYPE                = 75;
    public const TYPE_NOT_FOUND                     = 80;
    public const TYPE_NOT_REGISTERED                = 85;
    public const TYPE_ALREADY_REGISTERED            = 90;

    /**
     * @param string $method
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function notSupported($method)
    {
        return new self(sprintf("Operation '%s' is not supported by platform.", $method), self::NOT_SUPPORTED_METHOD);
    }

    /**
     * @return DBALException
     */
    public static function invalidPlatformSpecified() : self
    {
        return new self(
            "Invalid 'platform' option specified, need to give an instance of " . AbstractPlatform::class . '.',
            self::INVALID_PLATFORM_SPECIFIED
        );
    }

    /**
     * @param mixed $invalidPlatform
     *
     * @return DBALException
     */
    public static function invalidPlatformType($invalidPlatform) : self
    {
        if (is_object($invalidPlatform)) {
            return new self(
                sprintf(
                    "Option 'platform' must be a subtype of '%s', instance of '%s' given",
                    AbstractPlatform::class,
                    get_class($invalidPlatform)
                ),
                self::INVALID_PLATFORM_TYPE
            );
        }

        return new self(
            sprintf(
                "Option 'platform' must be an object and subtype of '%s'. Got '%s'",
                AbstractPlatform::class,
                gettype($invalidPlatform)
            ),
            self::INVALID_PLATFORM_TYPE
        );
    }

    /**
     * Returns a new instance for an invalid specified platform version.
     *
     * @param string $version        The invalid platform version given.
     * @param string $expectedFormat The expected platform version format.
     *
     * @return DBALException
     */
    public static function invalidPlatformVersionSpecified($version, $expectedFormat)
    {
        return new self(
            sprintf(
                'Invalid platform version "%s" specified. ' .
                'The platform version has to be specified in the format: "%s".',
                $version,
                $expectedFormat
            ),
            self::INVALID_PLATFORM_VERSION_SPECIFIED
        );
    }

    /**
     * @return DBALException
     */
    public static function invalidPdoInstance()
    {
        return new self(
            "The 'pdo' option was used in DriverManager::getConnection() but no " .
            'instance of PDO was given.',
            self::INVALID_PDO_INSTANCE
        );
    }

    /**
     * @param string|null $url The URL that was provided in the connection parameters (if any).
     *
     * @return DBALException
     */
    public static function driverRequired($url = null)
    {
        if ($url) {
            return new self(
                sprintf(
                    "The options 'driver' or 'driverClass' are mandatory if a connection URL without scheme " .
                    'is given to DriverManager::getConnection(). Given URL: %s',
                    $url
                ),
                self::DRIVER_REQUIRED
            );
        }

        return new self(
            "The options 'driver' or 'driverClass' are mandatory if no PDO " .
            'instance is given to DriverManager::getConnection().',
            self::DRIVER_REQUIRED
        );
    }

    /**
     * @param string   $unknownDriverName
     * @param string[] $knownDrivers
     *
     * @return DBALException
     */
    public static function unknownDriver($unknownDriverName, array $knownDrivers)
    {
        return new self(
            "The given 'driver' " . $unknownDriverName . ' is unknown, ' .
            'Doctrine currently supports only the following drivers: ' . implode(', ', $knownDrivers),
            self::UNKNOWN_DRIVER
        );
    }

    /**
     * @param string  $sql
     * @param mixed[] $params
     *
     * @return DBALException|DriverException|Throwable
     */
    public static function driverExceptionDuringQuery(Driver $driver, Throwable $driverEx, $sql, array $params = [])
    {
        $msg = "An exception occurred while executing '" . $sql . "'";
        if ($params) {
            $msg .= ' with params ' . self::formatParameters($params);
        }
        $msg .= ":\n\n" . $driverEx->getMessage();

        return static::wrapException($driver, $driverEx, $msg, self::DRIVER_EXCEPTION_DURING_QUERY);
    }

    /**
     * @param Driver    $driver
     * @param Throwable $driverEx
     *
     * @return DBALException|DriverException|Throwable
     */
    public static function driverException(Driver $driver, Throwable $driverEx)
    {
        return static::wrapException(
            $driver,
            $driverEx,
            'An exception occurred in driver: ' . $driverEx->getMessage(),
            self::DRIVER_EXCEPTION
        );
    }

    /**
     * @param Driver    $driver
     * @param Throwable $driverEx
     * @param string    $msg
     * @param int       $code
     *
     * @return DBALException|DriverException|Throwable
     */
    private static function wrapException(Driver $driver, Throwable $driverEx, $msg, $code = 0)
    {
        if ($driverEx instanceof DriverException) {
            return $driverEx;
        }
        if ($driver instanceof ExceptionConverterDriver && $driverEx instanceof DriverExceptionInterface) {
            return $driver->convertException($msg, $driverEx);
        }

        return new self($msg, $code, $driverEx);
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

    /**
     * @param string $wrapperClass
     *
     * @return DBALException
     */
    public static function invalidWrapperClass($wrapperClass)
    {
        return new self(
            "The given 'wrapperClass' " . $wrapperClass . ' has to be a ' . 'subtype of \Doctrine\DBAL\Connection.',
            self::INVALID_WRAPPER_CLASS
        );
    }

    /**
     * @param string $driverClass
     *
     * @return DBALException
     */
    public static function invalidDriverClass($driverClass)
    {
        return new self(
            "The given 'driverClass' " . $driverClass . ' has to implement the ' . Driver::class . ' interface.',
            self::INVALID_DRIVER_CLASS
        );
    }

    /**
     * @param string $tableName
     *
     * @return DBALException
     */
    public static function invalidTableName($tableName)
    {
        return new self('Invalid table name specified: ' . $tableName, self::INVALID_TABLE_NAME);
    }

    /**
     * @param string $tableName
     *
     * @return DBALException
     */
    public static function noColumnsSpecifiedForTable($tableName)
    {
        return new self('No columns specified for table ' . $tableName, self::NO_COLUMNS_SPECIFIED_FOR_TABLE);
    }

    /**
     * @return DBALException
     */
    public static function limitOffsetInvalid()
    {
        return new self(
            'Invalid Offset in Limit Query, it has to be larger than or equal to 0.',
            self::LIMIT_OFFSET_INVALID
        );
    }

    /**
     * @param string $name
     *
     * @return DBALException
     */
    public static function typeExists($name)
    {
        return new self('Type ' . $name . ' already exists.', self::TYPE_ALREADY_EXISTS);
    }

    /**
     * @param string $name
     *
     * @return DBALException
     */
    public static function unknownColumnType($name)
    {
        return new self(
            'Unknown column type "' . $name . '" requested. Any Doctrine type that you use has ' .
            'to be registered with \Doctrine\DBAL\Types\Type::addType(). You can get a list of all the ' .
            'known types with \Doctrine\DBAL\Types\Type::getTypesMap(). If this error occurs during database ' .
            'introspection then you might have forgotten to register all database types for a Doctrine Type. Use ' .
            'AbstractPlatform#registerDoctrineTypeMapping() or have your custom types implement ' .
            'Type#getMappedDatabaseTypes(). If the type name is empty you might ' .
            'have a problem with the cache or forgot some mapping information.',
            self::UNKNOWN_COLUMN_TYPE
        );
    }

    /**
     * @param string $name
     *
     * @return DBALException
     */
    public static function typeNotFound($name)
    {
        return new self('Type to be overwritten ' . $name . ' does not exist.', self::TYPE_NOT_FOUND);
    }

    /**
     * @param Type $type
     *
     * @return DBALException
     */
    public static function typeNotRegistered(Type $type) : self
    {
        return new self(
            sprintf('Type of the class %s@%s is not registered.', get_class($type), spl_object_hash($type)),
            self::TYPE_NOT_REGISTERED
        );
    }

    /**
     * @param Type $type
     *
     * @return DBALException
     */
    public static function typeAlreadyRegistered(Type $type) : self
    {
        return new self(
            sprintf('Type of the class %s@%s is already registered.', get_class($type), spl_object_hash($type)),
            self::TYPE_ALREADY_REGISTERED
        );
    }
}
