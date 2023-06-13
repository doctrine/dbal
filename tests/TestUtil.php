<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys;
use Doctrine\DBAL\Driver\Mysqli;
use Doctrine\DBAL\Driver\OCI8\Middleware\InitializeSession;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;

use function array_keys;
use function array_map;
use function array_values;
use function assert;
use function extension_loaded;
use function file_exists;
use function implode;
use function in_array;
use function is_string;
use function str_starts_with;
use function strlen;
use function substr;
use function unlink;

/**
 * TestUtil is a class with static utility methods used during tests.
 *
 * @psalm-import-type Params from DriverManager
 */
class TestUtil
{
    /** Whether the database schema is initialized. */
    private static bool $initialized = false;

    /**
     * Creates a new <b>test</b> database connection using the following parameters
     * of the $GLOBALS array:
     *
     * 'db_driver':   The name of the Doctrine DBAL database driver to use.
     * 'db_user':     The username to use for connecting.
     * 'db_password': The password to use for connecting.
     * 'db_host':     The hostname of the database to connect to.
     * 'db_server':   The server name of the database to connect to
     *                (optional, some vendors allow multiple server instances with different names on the same host).
     * 'db_dbname':   The name of the database to connect to.
     * 'db_port':     The port of the database to connect to.
     *
     * Usually these variables of the $GLOBALS array are filled by PHPUnit based
     * on an XML configuration file. If no such parameters exist, an SQLite
     * in-memory database is used.
     *
     * @return Connection The database connection instance.
     */
    public static function getConnection(): Connection
    {
        $params = self::getConnectionParams();

        if (empty($params['memory']) && ! self::$initialized) {
            self::initializeDatabase();
            self::$initialized = true;
        }

        assert(isset($params['driver']));

        return DriverManager::getConnection(
            $params,
            self::createConfiguration($params['driver']),
        );
    }

    /** @return Params */
    public static function getConnectionParams(): array
    {
        $params = self::getTestConnectionParameters();

        if (isset($params['driver'])) {
            return $params;
        }

        if (! extension_loaded('pdo_sqlite')) {
            Assert::markTestSkipped('PDO SQLite extension is not loaded');
        }

        return [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];
    }

    private static function initializeDatabase(): void
    {
        $testConnParams = self::getTestConnectionParameters();
        $privConnParams = self::getPrivilegedConnectionParameters();

        // Connect as a privileged user to create and drop the test database.
        $privConn = DriverManager::getConnection($privConnParams);

        $platform = $privConn->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            if (isset($testConnParams['path']) && file_exists($testConnParams['path'])) {
                unlink($testConnParams['path']);
            }
        } elseif ($platform instanceof DB2Platform) {
            $testConn = DriverManager::getConnection($testConnParams);

            $sm = $testConn->createSchemaManager();

            $schema = $sm->introspectSchema();
            $sm->dropSchemaObjects($schema);

            $testConn->close();
        } else {
            if (! $platform instanceof OraclePlatform) {
                if (! isset($testConnParams['dbname'])) {
                    throw new InvalidArgumentException(
                        'You must have a database configured in your connection.',
                    );
                }

                $dbname = $testConnParams['dbname'];
            } else {
                if (! isset($testConnParams['user'])) {
                    throw new InvalidArgumentException(
                        'You must have a user configured in your connection.',
                    );
                }

                $dbname = $testConnParams['user'];
            }

            $sm = $privConn->createSchemaManager();

            try {
                $sm->dropDatabase($dbname);
            } catch (DatabaseObjectNotFoundException) {
            }

            $sm->createDatabase($dbname);
        }

        $privConn->close();
    }

    private static function createConfiguration(string $driver): Configuration
    {
        $configuration = new Configuration();

        switch ($driver) {
            case 'pdo_oci':
            case 'oci8':
                $configuration->setMiddlewares([new InitializeSession()]);
                break;
            case 'pdo_sqlite':
            case 'sqlite3':
                $configuration->setMiddlewares([new EnableForeignKeys()]);
                break;
        }

        $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        return $configuration;
    }

    /** @return Params */
    private static function getPrivilegedConnectionParameters(): array
    {
        if (isset($GLOBALS['tmpdb_driver'])) {
            return self::mapConnectionParameters($GLOBALS, 'tmpdb_');
        }

        $parameters = self::mapConnectionParameters($GLOBALS, 'db_');
        unset($parameters['dbname']);

        return $parameters;
    }

    /** @return Params */
    private static function getTestConnectionParameters(): array
    {
        return self::mapConnectionParameters($GLOBALS, 'db_');
    }

    /**
     * @param array<string,mixed> $configuration
     *
     * @return Params
     */
    private static function mapConnectionParameters(array $configuration, string $prefix): array
    {
        $parameters = [];

        foreach (
            [
                'driver',
                'user',
                'password',
                'host',
                'dbname',
                'memory',
                'port',
                'server',
                'ssl_key',
                'ssl_cert',
                'ssl_ca',
                'ssl_capath',
                'ssl_cipher',
                'unix_socket',
                'path',
                'charset',
            ] as $parameter
        ) {
            if (! isset($configuration[$prefix . $parameter])) {
                continue;
            }

            $parameters[$parameter] = $configuration[$prefix . $parameter];
        }

        if (isset($parameters['port'])) {
            $parameters['port'] = (int) $parameters['port'];
        }

        foreach ($configuration as $param => $value) {
            if (! str_starts_with($param, $prefix . 'driver_option_')) {
                continue;
            }

            $option = substr($param, strlen($prefix . 'driver_option_'));

            if ($option === Mysqli\Connection::OPTION_FLAGS) {
                $value = (int) $value;
            }

            $parameters['driverOptions'][$option] = $value;
        }

        return $parameters;
    }

    public static function getPrivilegedConnection(): Connection
    {
        return DriverManager::getConnection(self::getPrivilegedConnectionParameters());
    }

    public static function isDriverOneOf(string ...$names): bool
    {
        $params = self::getConnectionParams();
        assert(isset($params['driver']));

        return in_array($params['driver'], $names, true);
    }

    /**
     * Generates a query that will return the given rows without the need to create a temporary table.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    public static function generateResultSetQuery(array $rows, AbstractPlatform $platform): string
    {
        return implode(' UNION ALL ', array_map(static function (array $row) use ($platform): string {
            return $platform->getDummySelectSQL(
                implode(', ', array_map(static function (string $column, $value) use ($platform): string {
                    if (is_string($value)) {
                        $value = $platform->quoteStringLiteral($value);
                    }

                    return $value . ' ' . $platform->quoteIdentifier($column);
                }, array_keys($row), array_values($row))),
            );
        }, $rows));
    }
}
