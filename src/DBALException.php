<?php

namespace Doctrine\DBAL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Exception;

use function get_class;
use function gettype;
use function implode;
use function is_object;
use function spl_object_hash;
use function sprintf;

/**
 * @psalm-immutable
 */
class DBALException extends Exception
{
    /**
     * @param string $method
     *
     * @return DBALException
     */
    public static function notSupported($method)
    {
        return new self(sprintf("Operation '%s' is not supported by platform.", $method));
    }

    /**
     * @param mixed $invalidPlatform
     */
    public static function invalidPlatformType($invalidPlatform): self
    {
        if (is_object($invalidPlatform)) {
            return new self(
                sprintf(
                    "Option 'platform' must be a subtype of '%s', instance of '%s' given",
                    AbstractPlatform::class,
                    get_class($invalidPlatform)
                )
            );
        }

        return new self(
            sprintf(
                "Option 'platform' must be an object and subtype of '%s'. Got '%s'",
                AbstractPlatform::class,
                gettype($invalidPlatform)
            )
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
            )
        );
    }

    /**
     * @param string|null $url The URL that was provided in the connection parameters (if any).
     *
     * @return DBALException
     */
    public static function driverRequired($url = null)
    {
        if ($url !== null) {
            return new self(
                sprintf(
                    "The options 'driver' or 'driverClass' are mandatory if a connection URL without scheme " .
                    'is given to DriverManager::getConnection(). Given URL: %s',
                    $url
                )
            );
        }

        return new self("The options 'driver' or 'driverClass' are mandatory if no PDO " .
            'instance is given to DriverManager::getConnection().');
    }

    /**
     * @param string   $unknownDriverName
     * @param string[] $knownDrivers
     *
     * @return DBALException
     */
    public static function unknownDriver($unknownDriverName, array $knownDrivers)
    {
        return new self("The given 'driver' " . $unknownDriverName . ' is unknown, ' .
            'Doctrine currently supports only the following drivers: ' . implode(', ', $knownDrivers));
    }

    /**
     * @param string $wrapperClass
     *
     * @return DBALException
     */
    public static function invalidWrapperClass($wrapperClass)
    {
        return new self("The given 'wrapperClass' " . $wrapperClass . ' has to be a ' .
            'subtype of \Doctrine\DBAL\Connection.');
    }

    /**
     * @param string $driverClass
     *
     * @return DBALException
     */
    public static function invalidDriverClass($driverClass)
    {
        return new self("The given 'driverClass' " . $driverClass . ' has to implement the ' . Driver::class . ' interface.');
    }

    /**
     * @param string $tableName
     *
     * @return DBALException
     */
    public static function noColumnsSpecifiedForTable($tableName)
    {
        return new self('No columns specified for table ' . $tableName);
    }

    /**
     * @return DBALException
     */
    public static function limitOffsetInvalid()
    {
        return new self('Invalid Offset in Limit Query, it has to be larger than or equal to 0.');
    }

    /**
     * @param string $name
     *
     * @return DBALException
     */
    public static function typeExists($name)
    {
        return new self('Type ' . $name . ' already exists.');
    }

    /**
     * @param string $name
     *
     * @return DBALException
     */
    public static function unknownColumnType($name)
    {
        return new self('Unknown column type "' . $name . '" requested. Any Doctrine type that you use has ' .
            'to be registered with \Doctrine\DBAL\Types\Type::addType(). You can get a list of all the ' .
            'known types with \Doctrine\DBAL\Types\Type::getTypesMap(). If this error occurs during database ' .
            'introspection then you might have forgotten to register all database types for a Doctrine Type. Use ' .
            'AbstractPlatform#registerDoctrineTypeMapping() or have your custom types implement ' .
            'Type#getMappedDatabaseTypes(). If the type name is empty you might ' .
            'have a problem with the cache or forgot some mapping information.');
    }

    /**
     * @param string $name
     *
     * @return DBALException
     */
    public static function typeNotFound($name)
    {
        return new self('Type to be overwritten ' . $name . ' does not exist.');
    }

    public static function typeNotRegistered(Type $type): self
    {
        return new self(sprintf('Type of the class %s@%s is not registered.', get_class($type), spl_object_hash($type)));
    }

    public static function typeAlreadyRegistered(Type $type): self
    {
        return new self(
            sprintf('Type of the class %s@%s is already registered.', get_class($type), spl_object_hash($type))
        );
    }
}
