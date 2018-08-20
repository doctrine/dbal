<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

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
     * @param string $method
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function notSupported($method)
    {
        return new self("Operation '$method' is not supported by platform.");
    }

    public static function invalidPlatformSpecified() : self
    {
        return new self(
            "Invalid 'platform' option specified, need to give an instance of ".
            "\Doctrine\DBAL\Platforms\AbstractPlatform.");
    }

    /**
     * @param mixed $invalidPlatform
     */
    public static function invalidPlatformType($invalidPlatform) : self
    {
        if (\is_object($invalidPlatform)) {
            return new self(
                sprintf(
                    "Option 'platform' must be a subtype of '%s', instance of '%s' given",
                    AbstractPlatform::class,
                    \get_class($invalidPlatform)
                )
            );
        }

        return new self(
            sprintf(
                "Option 'platform' must be an object and subtype of '%s'. Got '%s'",
                AbstractPlatform::class,
                \gettype($invalidPlatform)
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
     * @return \Doctrine\DBAL\DBALException
     */
    public static function invalidPdoInstance()
    {
        return new self(
            "The 'pdo' option was used in DriverManager::getConnection() but no ".
            "instance of PDO was given."
        );
    }

    /**
     * @param string|null $url The URL that was provided in the connection parameters (if any).
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function driverRequired($url = null)
    {
        if ($url) {
            return new self(
                sprintf(
                    "The options 'driver' or 'driverClass' are mandatory if a connection URL without scheme " .
                    "is given to DriverManager::getConnection(). Given URL: %s",
                    $url
                )
            );
        }

        return new self("The options 'driver' or 'driverClass' are mandatory if no PDO ".
            "instance is given to DriverManager::getConnection().");
    }

    /**
     * @param string $unknownDriverName
     * @param array  $knownDrivers
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function unknownDriver($unknownDriverName, array $knownDrivers)
    {
        return new self("The given 'driver' ".$unknownDriverName." is unknown, ".
            "Doctrine currently supports only the following drivers: ".implode(", ", $knownDrivers));
    }

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

    /**
     * @param string $wrapperClass
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function invalidWrapperClass($wrapperClass)
    {
        return new self("The given 'wrapperClass' ".$wrapperClass." has to be a ".
            "subtype of \Doctrine\DBAL\Connection.");
    }

    /**
     * @param string $driverClass
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function invalidDriverClass($driverClass)
    {
        return new self("The given 'driverClass' ".$driverClass." has to implement the ".
            "\Doctrine\DBAL\Driver interface.");
    }

    /**
     * @param string $tableName
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function invalidTableName($tableName)
    {
        return new self("Invalid table name specified: ".$tableName);
    }

    /**
     * @param string $tableName
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function noColumnsSpecifiedForTable($tableName)
    {
        return new self("No columns specified for table ".$tableName);
    }

    /**
     * @return \Doctrine\DBAL\DBALException
     */
    public static function limitOffsetInvalid()
    {
        return new self("Invalid Offset in Limit Query, it has to be larger than or equal to 0.");
    }

    /**
     * @param string $name
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function typeExists($name)
    {
        return new self('Type '.$name.' already exists.');
    }

    /**
     * @param string $name
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function unknownColumnType($name)
    {
        return new self('Unknown column type "'.$name.'" requested. Any Doctrine type that you use has ' .
            'to be registered with \Doctrine\DBAL\Types\Type::addType(). You can get a list of all the ' .
            'known types with \Doctrine\DBAL\Types\Type::getTypesMap(). If this error occurs during database ' .
            'introspection then you might have forgotten to register all database types for a Doctrine Type. Use ' .
            'AbstractPlatform#registerDoctrineTypeMapping() or have your custom types implement ' .
            'Type#getMappedDatabaseTypes(). If the type name is empty you might ' .
            'have a problem with the cache or forgot some mapping information.'
        );
    }

    /**
     * @param string $name
     *
     * @return \Doctrine\DBAL\DBALException
     */
    public static function typeNotFound($name)
    {
        return new self('Type to be overwritten '.$name.' does not exist.');
    }
}
