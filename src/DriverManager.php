<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\IBMDB2;
use Doctrine\DBAL\Driver\Mysqli;
use Doctrine\DBAL\Driver\OCI8;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Driver\SQLite3;
use Doctrine\DBAL\Driver\SQLSrv;
use Doctrine\DBAL\Exception\DriverRequired;
use Doctrine\DBAL\Exception\InvalidDriverClass;
use Doctrine\DBAL\Exception\InvalidWrapperClass;
use Doctrine\DBAL\Exception\MalformedDsnException;
use Doctrine\DBAL\Exception\UnknownDriver;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Deprecations\Deprecation;

use function array_keys;
use function array_merge;
use function class_implements;
use function in_array;
use function is_subclass_of;

/**
 * Factory for creating {@see Connection} instances.
 *
 * @psalm-type OverrideParams = array{
 *     charset?: string,
 *     dbname?: string,
 *     driver?: key-of<self::DRIVER_MAP>,
 *     driverClass?: class-string<Driver>,
 *     driverOptions?: array<mixed>,
 *     host?: string,
 *     password?: string,
 *     path?: string,
 *     pdo?: \PDO,
 *     port?: int,
 *     user?: string,
 *     unix_socket?: string,
 * }
 * @psalm-type Params = array{
 *     charset?: string,
 *     dbname?: string,
 *     defaultTableOptions?: array<string, mixed>,
 *     driver?: key-of<self::DRIVER_MAP>,
 *     driverClass?: class-string<Driver>,
 *     driverOptions?: array<mixed>,
 *     host?: string,
 *     keepSlave?: bool,
 *     keepReplica?: bool,
 *     master?: OverrideParams,
 *     memory?: bool,
 *     password?: string,
 *     path?: string,
 *     pdo?: \PDO,
 *     port?: int,
 *     primary?: OverrideParams,
 *     replica?: array<OverrideParams>,
 *     serverVersion?: string,
 *     sharding?: array<string,mixed>,
 *     slaves?: array<OverrideParams>,
 *     url?: string,
 *     user?: string,
 *     wrapperClass?: class-string<Connection>,
 *     unix_socket?: string,
 * }
 */
final class DriverManager
{
    /**
     * List of supported drivers and their mappings to the driver classes.
     *
     * To add your own driver use the 'driverClass' parameter to {@see DriverManager::getConnection()}.
     */
    private const DRIVER_MAP = [
        'pdo_mysql'  => PDO\MySQL\Driver::class,
        'pdo_sqlite' => PDO\SQLite\Driver::class,
        'pdo_pgsql'  => PDO\PgSQL\Driver::class,
        'pdo_oci'    => PDO\OCI\Driver::class,
        'oci8'       => OCI8\Driver::class,
        'ibm_db2'    => IBMDB2\Driver::class,
        'pdo_sqlsrv' => PDO\SQLSrv\Driver::class,
        'mysqli'     => Mysqli\Driver::class,
        'sqlsrv'     => SQLSrv\Driver::class,
        'sqlite3'    => SQLite3\Driver::class,
    ];

    /**
     * Private constructor. This class cannot be instantiated.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * Creates a connection object based on the specified parameters.
     * This method returns a Doctrine\DBAL\Connection which wraps the underlying
     * driver connection.
     *
     * $params must contain at least one of the following.
     *
     * Either 'driver' with one of the array keys of {@see DRIVER_MAP},
     * OR 'driverClass' that contains the full class name (with namespace) of the
     * driver class to instantiate.
     *
     * Other (optional) parameters:
     *
     * <b>user (string)</b>:
     * The username to use when connecting.
     *
     * <b>password (string)</b>:
     * The password to use when connecting.
     *
     * <b>driverOptions (array)</b>:
     * Any additional driver-specific options for the driver. These are just passed
     * through to the driver.
     *
     * <b>wrapperClass</b>:
     * You may specify a custom wrapper class through the 'wrapperClass'
     * parameter but this class MUST inherit from Doctrine\DBAL\Connection.
     *
     * <b>driverClass</b>:
     * The driver class to use.
     *
     * @param Configuration|null $config The configuration to use.
     * @psalm-param array{
     *     charset?: string,
     *     dbname?: string,
     *     driver?: key-of<self::DRIVER_MAP>,
     *     driverClass?: class-string<Driver>,
     *     driverOptions?: array<mixed>,
     *     host?: string,
     *     keepSlave?: bool,
     *     keepReplica?: bool,
     *     master?: OverrideParams,
     *     memory?: bool,
     *     password?: string,
     *     path?: string,
     *     pdo?: \PDO,
     *     port?: int,
     *     primary?: OverrideParams,
     *     replica?: array<OverrideParams>,
     *     sharding?: array<string,mixed>,
     *     slaves?: array<OverrideParams>,
     *     user?: string,
     *     wrapperClass?: class-string<T>,
     * } $params
     *
     * @psalm-return ($params is array{wrapperClass:mixed} ? T : Connection)
     *
     * @template T of Connection
     */
    public static function getConnection(array $params, ?Configuration $config = null): Connection
    {
        $config ??= new Configuration();
        $params   = self::parseDatabaseUrl($params);

        // URL support for PrimaryReplicaConnection
        if (isset($params['primary'])) {
            $params['primary'] = self::parseDatabaseUrl($params['primary']);
        }

        if (isset($params['replica'])) {
            foreach ($params['replica'] as $key => $replicaParams) {
                $params['replica'][$key] = self::parseDatabaseUrl($replicaParams);
            }
        }

        $driver = self::createDriver($params);

        foreach ($config->getMiddlewares() as $middleware) {
            $driver = $middleware->wrap($driver);
        }

        $wrapperClass = Connection::class;
        if (isset($params['wrapperClass'])) {
            if (! is_subclass_of($params['wrapperClass'], $wrapperClass)) {
                throw InvalidWrapperClass::new($params['wrapperClass']);
            }

            /** @var class-string<Connection> $wrapperClass */
            $wrapperClass = $params['wrapperClass'];
        }

        return new $wrapperClass($params, $driver, $config);
    }

    /**
     * Returns the list of supported drivers.
     *
     * @return string[]
     */
    public static function getAvailableDrivers(): array
    {
        return array_keys(self::DRIVER_MAP);
    }

    /**
     * @param array<string,mixed> $params
     * @psalm-param Params $params
     */
    private static function createDriver(array $params): Driver
    {
        if (isset($params['driverClass'])) {
            $interfaces = class_implements($params['driverClass']);

            if ($interfaces === false || ! in_array(Driver::class, $interfaces, true)) {
                throw InvalidDriverClass::new($params['driverClass']);
            }

            return new $params['driverClass']();
        }

        if (isset($params['driver'])) {
            if (! isset(self::DRIVER_MAP[$params['driver']])) {
                throw UnknownDriver::new($params['driver'], array_keys(self::DRIVER_MAP));
            }

            $class = self::DRIVER_MAP[$params['driver']];

            return new $class();
        }

        throw DriverRequired::new();
    }

    /**
     * Extracts parts from a database URL, if present, and returns an
     * updated list of parameters.
     *
     * @param mixed[] $params The list of parameters.
     * @psalm-param Params $params
     *
     * @return mixed[] A modified list of parameters with info from a database
     *                 URL extracted into indidivual parameter parts.
     * @psalm-return Params
     */
    private static function parseDatabaseUrl(array $params): array
    {
        if (! isset($params['url'])) {
            return $params;
        }

        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5843',
            'The "url" connection parameter is deprecated. Please use %s to parse a database url before calling %s.',
            DsnParser::class,
            self::class,
        );

        $parser = new DsnParser();
        try {
            $parsedParams = $parser->parse($params['url']);
        } catch (MalformedDsnException $e) {
            throw new InvalidDriverClass('Malformed parameter "url".', 0, $e);
        }

        if (isset($parsedParams['driver'])) {
            // The requested driver from the URL scheme takes precedence
            // over the default custom driver from the connection parameters (if any).
            unset($params['driverClass']);
        }

        $params = array_merge($params, $parsedParams);

        // If a schemeless connection URL is given, we require a default driver or default custom driver
        // as connection parameter.
        if (! isset($params['driverClass']) && ! isset($params['driver'])) {
            throw DriverRequired::new($params['url']);
        }

        return $params;
    }
}
