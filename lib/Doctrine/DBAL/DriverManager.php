<?php

namespace Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Driver\DrizzlePDOMySql;
use Doctrine\DBAL\Driver\IBMDB2;
use Doctrine\DBAL\Driver\Mysqli;
use Doctrine\DBAL\Driver\OCI8;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Driver\SQLAnywhere;
use Doctrine\DBAL\Driver\SQLSrv;

use function array_keys;
use function array_merge;
use function class_implements;
use function in_array;
use function is_string;
use function is_subclass_of;
use function parse_str;
use function parse_url;
use function preg_replace;
use function rawurldecode;
use function str_replace;
use function strpos;
use function substr;

/**
 * Factory for creating {@link Connection} instances.
 *
 * @psalm-type OverrideParams = array{
 *     charset?: string,
 *     dbname?: string,
 *     default_dbname?: string,
 *     driver?: key-of<self::DRIVER_MAP>,
 *     driverClass?: class-string<Driver>,
 *     driverOptions?: array<mixed>,
 *     host?: string,
 *     password?: string,
 *     path?: string,
 *     pdo?: \PDO,
 *     platform?: Platforms\AbstractPlatform,
 *     port?: int,
 *     user?: string,
 * }
 * @psalm-type Params = array{
 *     charset?: string,
 *     dbname?: string,
 *     default_dbname?: string,
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
 *     platform?: Platforms\AbstractPlatform,
 *     port?: int,
 *     primary?: OverrideParams,
 *     replica?: array<OverrideParams>,
 *     sharding?: array<string,mixed>,
 *     slaves?: array<OverrideParams>,
 *     user?: string,
 *     wrapperClass?: class-string<Connection>,
 * }
 */
final class DriverManager
{
    /**
     * List of supported drivers and their mappings to the driver classes.
     *
     * To add your own driver use the 'driverClass' parameter to {@link DriverManager::getConnection()}.
     */
    private const DRIVER_MAP = [
        'pdo_mysql'          => PDO\MySQL\Driver::class,
        'pdo_sqlite'         => PDO\SQLite\Driver::class,
        'pdo_pgsql'          => PDO\PgSQL\Driver::class,
        'pdo_oci'            => PDO\OCI\Driver::class,
        'oci8'               => OCI8\Driver::class,
        'ibm_db2'            => IBMDB2\Driver::class,
        'pdo_sqlsrv'         => PDO\SQLSrv\Driver::class,
        'mysqli'             => Mysqli\Driver::class,
        'drizzle_pdo_mysql'  => DrizzlePDOMySql\Driver::class,
        'sqlanywhere'        => SQLAnywhere\Driver::class,
        'sqlsrv'             => SQLSrv\Driver::class,
    ];

    /**
     * List of URL schemes from a database URL and their mappings to driver.
     *
     * @var string[]
     */
    private static $driverSchemeAliases = [
        'db2'        => 'ibm_db2',
        'mssql'      => 'pdo_sqlsrv',
        'mysql'      => 'pdo_mysql',
        'mysql2'     => 'pdo_mysql', // Amazon RDS, for some weird reason
        'postgres'   => 'pdo_pgsql',
        'postgresql' => 'pdo_pgsql',
        'pgsql'      => 'pdo_pgsql',
        'sqlite'     => 'pdo_sqlite',
        'sqlite3'    => 'pdo_sqlite',
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
     * Either 'driver' with one of the array keys of {@link DRIVER_MAP},
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
     * <b>pdo</b>:
     * You can pass an existing PDO instance through this parameter. The PDO
     * instance will be wrapped in a Doctrine\DBAL\Connection.
     * This feature is deprecated and no longer supported in 3.0.x version.
     *
     * <b>wrapperClass</b>:
     * You may specify a custom wrapper class through the 'wrapperClass'
     * parameter but this class MUST inherit from Doctrine\DBAL\Connection.
     *
     * <b>driverClass</b>:
     * The driver class to use.
     *
     * @param array<string,mixed> $params
     * @param Configuration|null  $config       The configuration to use.
     * @param EventManager|null   $eventManager The event manager to use.
     * @psalm-param array{
     *     charset?: string,
     *     dbname?: string,
     *     default_dbname?: string,
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
     *     platform?: Platforms\AbstractPlatform,
     *     port?: int,
     *     primary?: OverrideParams,
     *     replica?: array<OverrideParams>,
     *     sharding?: array<string,mixed>,
     *     slaves?: array<OverrideParams>,
     *     user?: string,
     *     wrapperClass?: class-string<T>,
     * } $params
     * @phpstan-param array<string,mixed> $params
     *
     * @psalm-return ($params is array{wrapperClass:mixed} ? T : Connection)
     *
     * @throws Exception
     *
     * @template T of Connection
     */
    public static function getConnection(
        array $params,
        ?Configuration $config = null,
        ?EventManager $eventManager = null
    ): Connection {
        // create default config and event manager, if not set
        if (! $config) {
            $config = new Configuration();
        }

        if (! $eventManager) {
            $eventManager = new EventManager();
        }

        $params = self::parseDatabaseUrl($params);

        // @todo: deprecated, notice thrown by connection constructor
        if (isset($params['master'])) {
            $params['master'] = self::parseDatabaseUrl($params['master']);
        }

        // @todo: deprecated, notice thrown by connection constructor
        if (isset($params['slaves'])) {
            foreach ($params['slaves'] as $key => $slaveParams) {
                $params['slaves'][$key] = self::parseDatabaseUrl($slaveParams);
            }
        }

        // URL support for PrimaryReplicaConnection
        if (isset($params['primary'])) {
            $params['primary'] = self::parseDatabaseUrl($params['primary']);
        }

        if (isset($params['replica'])) {
            foreach ($params['replica'] as $key => $replicaParams) {
                $params['replica'][$key] = self::parseDatabaseUrl($replicaParams);
            }
        }

        // URL support for PoolingShardConnection
        if (isset($params['global'])) {
            $params['global'] = self::parseDatabaseUrl($params['global']);
        }

        if (isset($params['shards'])) {
            foreach ($params['shards'] as $key => $shardParams) {
                $params['shards'][$key] = self::parseDatabaseUrl($shardParams);
            }
        }

        // check for existing pdo object
        if (isset($params['pdo']) && ! $params['pdo'] instanceof \PDO) {
            throw Exception::invalidPdoInstance();
        }

        if (isset($params['pdo'])) {
            $params['pdo']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $params['driver'] = 'pdo_' . $params['pdo']->getAttribute(\PDO::ATTR_DRIVER_NAME);
        }

        $driver = self::createDriver($params);

        $wrapperClass = Connection::class;
        if (isset($params['wrapperClass'])) {
            if (! is_subclass_of($params['wrapperClass'], $wrapperClass)) {
                throw Exception::invalidWrapperClass($params['wrapperClass']);
            }

            /** @var class-string<Connection> $wrapperClass */
            $wrapperClass = $params['wrapperClass'];
        }

        return new $wrapperClass($params, $driver, $config, $eventManager);
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
     * @phpstan-param array<string,mixed> $params
     *
     * @throws Exception
     */
    private static function createDriver(array $params): Driver
    {
        if (isset($params['driverClass'])) {
            $interfaces = class_implements($params['driverClass'], true);

            if ($interfaces === false || ! in_array(Driver::class, $interfaces)) {
                throw Exception::invalidDriverClass($params['driverClass']);
            }

            return new $params['driverClass']();
        }

        if (isset($params['driver'])) {
            if (! isset(self::DRIVER_MAP[$params['driver']])) {
                throw Exception::unknownDriver($params['driver'], array_keys(self::DRIVER_MAP));
            }

            $class = self::DRIVER_MAP[$params['driver']];

            return new $class();
        }

        throw Exception::driverRequired();
    }

    /**
     * Normalizes the given connection URL path.
     *
     * @return string The normalized connection URL path
     */
    private static function normalizeDatabaseUrlPath(string $urlPath): string
    {
        // Trim leading slash from URL path.
        return substr($urlPath, 1);
    }

    /**
     * Extracts parts from a database URL, if present, and returns an
     * updated list of parameters.
     *
     * @param mixed[] $params The list of parameters.
     * @psalm-param Params $params
     * @phpstan-param array<string,mixed> $params
     *
     * @return mixed[] A modified list of parameters with info from a database
     *                 URL extracted into indidivual parameter parts.
     * @psalm-return Params
     * @phpstan-return array<string,mixed>
     *
     * @throws Exception
     */
    private static function parseDatabaseUrl(array $params): array
    {
        if (! isset($params['url'])) {
            return $params;
        }

        // (pdo_)?sqlite3?:///... => (pdo_)?sqlite3?://localhost/... or else the URL will be invalid
        $url = preg_replace('#^((?:pdo_)?sqlite3?):///#', '$1://localhost/', $params['url']);
        $url = parse_url($url);

        if ($url === false) {
            throw new Exception('Malformed parameter "url".');
        }

        foreach ($url as $param => $value) {
            if (! is_string($value)) {
                continue;
            }

            $url[$param] = rawurldecode($value);
        }

        // If we have a connection URL, we have to unset the default PDO instance connection parameter (if any)
        // as we cannot merge connection details from the URL into the PDO instance (URL takes precedence).
        unset($params['pdo']);

        $params = self::parseDatabaseUrlScheme($url['scheme'] ?? null, $params);

        if (isset($url['host'])) {
            $params['host'] = $url['host'];
        }

        if (isset($url['port'])) {
            $params['port'] = $url['port'];
        }

        if (isset($url['user'])) {
            $params['user'] = $url['user'];
        }

        if (isset($url['pass'])) {
            $params['password'] = $url['pass'];
        }

        $params = self::parseDatabaseUrlPath($url, $params);
        $params = self::parseDatabaseUrlQuery($url, $params);

        return $params;
    }

    /**
     * Parses the given connection URL and resolves the given connection parameters.
     *
     * Assumes that the connection URL scheme is already parsed and resolved into the given connection parameters
     * via {@link parseDatabaseUrlScheme}.
     *
     * @see parseDatabaseUrlScheme
     *
     * @param mixed[] $url    The URL parts to evaluate.
     * @param mixed[] $params The connection parameters to resolve.
     *
     * @return mixed[] The resolved connection parameters.
     */
    private static function parseDatabaseUrlPath(array $url, array $params): array
    {
        if (! isset($url['path'])) {
            return $params;
        }

        $url['path'] = self::normalizeDatabaseUrlPath($url['path']);

        // If we do not have a known DBAL driver, we do not know any connection URL path semantics to evaluate
        // and therefore treat the path as regular DBAL connection URL path.
        if (! isset($params['driver'])) {
            return self::parseRegularDatabaseUrlPath($url, $params);
        }

        if (strpos($params['driver'], 'sqlite') !== false) {
            return self::parseSqliteDatabaseUrlPath($url, $params);
        }

        return self::parseRegularDatabaseUrlPath($url, $params);
    }

    /**
     * Parses the query part of the given connection URL and resolves the given connection parameters.
     *
     * @param mixed[] $url    The connection URL parts to evaluate.
     * @param mixed[] $params The connection parameters to resolve.
     *
     * @return mixed[] The resolved connection parameters.
     */
    private static function parseDatabaseUrlQuery(array $url, array $params): array
    {
        if (! isset($url['query'])) {
            return $params;
        }

        $query = [];

        parse_str($url['query'], $query); // simply ingest query as extra params, e.g. charset or sslmode

        return array_merge($params, $query); // parse_str wipes existing array elements
    }

    /**
     * Parses the given regular connection URL and resolves the given connection parameters.
     *
     * Assumes that the "path" URL part is already normalized via {@link normalizeDatabaseUrlPath}.
     *
     * @see normalizeDatabaseUrlPath
     *
     * @param mixed[] $url    The regular connection URL parts to evaluate.
     * @param mixed[] $params The connection parameters to resolve.
     *
     * @return mixed[] The resolved connection parameters.
     */
    private static function parseRegularDatabaseUrlPath(array $url, array $params): array
    {
        $params['dbname'] = $url['path'];

        return $params;
    }

    /**
     * Parses the given SQLite connection URL and resolves the given connection parameters.
     *
     * Assumes that the "path" URL part is already normalized via {@link normalizeDatabaseUrlPath}.
     *
     * @see normalizeDatabaseUrlPath
     *
     * @param mixed[] $url    The SQLite connection URL parts to evaluate.
     * @param mixed[] $params The connection parameters to resolve.
     *
     * @return mixed[] The resolved connection parameters.
     */
    private static function parseSqliteDatabaseUrlPath(array $url, array $params): array
    {
        if ($url['path'] === ':memory:') {
            $params['memory'] = true;

            return $params;
        }

        $params['path'] = $url['path']; // pdo_sqlite driver uses 'path' instead of 'dbname' key

        return $params;
    }

    /**
     * Parses the scheme part from given connection URL and resolves the given connection parameters.
     *
     * @param string|null $scheme The connection URL scheme, if available
     * @param mixed[]     $params The connection parameters to resolve.
     *
     * @return mixed[] The resolved connection parameters.
     *
     * @throws Exception If parsing failed or resolution is not possible.
     */
    private static function parseDatabaseUrlScheme($scheme, array $params): array
    {
        if ($scheme !== null) {
            // The requested driver from the URL scheme takes precedence
            // over the default custom driver from the connection parameters (if any).
            unset($params['driverClass']);

            // URL schemes must not contain underscores, but dashes are ok
            $driver = str_replace('-', '_', $scheme);

            // The requested driver from the URL scheme takes precedence over the
            // default driver from the connection parameters. If the driver is
            // an alias (e.g. "postgres"), map it to the actual name ("pdo-pgsql").
            // Otherwise, let checkParams decide later if the driver exists.
            $params['driver'] = self::$driverSchemeAliases[$driver] ?? $driver;

            return $params;
        }

        // If a schemeless connection URL is given, we require a default driver or default custom driver
        // as connection parameter.
        if (! isset($params['driverClass']) && ! isset($params['driver'])) {
            throw Exception::driverRequired($params['url']);
        }

        return $params;
    }
}
