<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\IBMDB2;
use Doctrine\DBAL\Driver\Mysqli;
use Doctrine\DBAL\Driver\OCI8;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Driver\PgSQL;
use Doctrine\DBAL\Driver\SQLite3;
use Doctrine\DBAL\Driver\SQLSrv;
use Doctrine\DBAL\Exception\DriverRequired;
use Doctrine\DBAL\Exception\InvalidDriverClass;
use Doctrine\DBAL\Exception\InvalidWrapperClass;
use Doctrine\DBAL\Exception\UnknownDriver;
use SensitiveParameter;

use function array_keys;
use function is_a;

/**
 * Factory for creating {@see Connection} instances.
 *
 * @psalm-type OverrideParams = array{
 *     application_name?: string,
 *     charset?: string,
 *     dbname?: string,
 *     defaultTableOptions?: array<string, mixed>,
 *     driver?: key-of<self::DRIVER_MAP>,
 *     driverClass?: class-string<Driver>,
 *     driverOptions?: array<mixed>,
 *     host?: string,
 *     memory?: bool,
 *     password?: string,
 *     path?: string,
 *     persistent?: bool,
 *     port?: int,
 *     serverVersion?: string,
 *     sessionMode?: int,
 *     user?: string,
 *     unix_socket?: string,
 *     wrapperClass?: class-string<Connection>,
 * }
 * @psalm-type Params = array{
 *     application_name?: string,
 *     charset?: string,
 *     dbname?: string,
 *     defaultTableOptions?: array<string, mixed>,
 *     driver?: key-of<self::DRIVER_MAP>,
 *     driverClass?: class-string<Driver>,
 *     driverOptions?: array<mixed>,
 *     host?: string,
 *     keepReplica?: bool,
 *     memory?: bool,
 *     password?: string,
 *     path?: string,
 *     persistent?: bool,
 *     port?: int,
 *     primary?: OverrideParams,
 *     replica?: array<OverrideParams>,
 *     serverVersion?: string,
 *     sessionMode?: int,
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
        'pgsql'      => PgSQL\Driver::class,
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
     * @psalm-param Params $params
     *
     * @psalm-return ($params is array{wrapperClass: class-string<T>} ? T : Connection)
     *
     * @template T of Connection
     */
    public static function getConnection(
        #[SensitiveParameter]
        array $params,
        ?Configuration $config = null,
    ): Connection {
        $config ??= new Configuration();
        $driver   = self::createDriver($params['driver'] ?? null, $params['driverClass'] ?? null);

        foreach ($config->getMiddlewares() as $middleware) {
            $driver = $middleware->wrap($driver);
        }

        /** @var class-string<Connection> $wrapperClass */
        $wrapperClass = $params['wrapperClass'] ?? Connection::class;
        if (! is_a($wrapperClass, Connection::class, true)) {
            throw InvalidWrapperClass::new($wrapperClass);
        }

        return new $wrapperClass($params, $driver, $config);
    }

    /**
     * Returns the list of supported drivers.
     *
     * @return string[]
     * @psalm-return list<key-of<self::DRIVER_MAP>>
     */
    public static function getAvailableDrivers(): array
    {
        return array_keys(self::DRIVER_MAP);
    }

    /**
     * @param class-string<Driver>|null     $driverClass
     * @param key-of<self::DRIVER_MAP>|null $driver
     */
    private static function createDriver(?string $driver, ?string $driverClass): Driver
    {
        if ($driverClass === null) {
            if ($driver === null) {
                throw DriverRequired::new();
            }

            if (! isset(self::DRIVER_MAP[$driver])) {
                throw UnknownDriver::new($driver, array_keys(self::DRIVER_MAP));
            }

            $driverClass = self::DRIVER_MAP[$driver];
        } elseif (! is_a($driverClass, Driver::class, true)) {
            throw InvalidDriverClass::new($driverClass);
        }

        return new $driverClass();
    }
}
