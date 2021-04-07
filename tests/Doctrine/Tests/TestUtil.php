<?php

declare(strict_types=1);

namespace Doctrine\Tests;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use UnexpectedValueException;

use function array_keys;
use function array_map;
use function array_values;
use function explode;
use function fwrite;
use function get_class;
use function implode;
use function is_string;
use function sprintf;
use function strlen;
use function strpos;
use function substr;

use const STDERR;

/**
 * TestUtil is a class with static utility methods used during tests.
 *
 * @psalm-type OverrideParams = array{
 *     charset?: string,
 *     dbname?: string,
 *     default_dbname?: string,
 *     driver?: key-of<\Doctrine\DBAL\DriverManager::DRIVER_MAP>,
 *     driverClass?: class-string<\Doctrine\DBAL\Driver>,
 *     driverOptions?: array<mixed>,
 *     host?: string,
 *     password?: string,
 *     path?: string,
 *     pdo?: \PDO,
 *     platform?: \Doctrine\DBAL\Platforms\AbstractPlatform,
 *     port?: int,
 *     user?: string,
 * }
 * @psalm-type ConnectionParams = array{
 *     charset?: string,
 *     dbname?: string,
 *     default_dbname?: string,
 *     driver?: key-of<\Doctrine\DBAL\DriverManager::DRIVER_MAP>,
 *     driverClass?: class-string<\Doctrine\DBAL\Driver>,
 *     driverOptions?: array<mixed>,
 *     host?: string,
 *     keepSlave?: bool,
 *     keepReplica?: bool,
 *     master?: OverrideParams,
 *     memory?: bool,
 *     password?: string,
 *     path?: string,
 *     pdo?: \PDO,
 *     platform?: \Doctrine\DBAL\Platforms\AbstractPlatform,
 *     port?: int,
 *     primary?: OverrideParams,
 *     replica?: array<OverrideParams>,
 *     sharding?: array<string,mixed>,
 *     slaves?: array<OverrideParams>,
 *     user?: string,
 *     wrapperClass?: class-string<Connection>,
 * }
 */
class TestUtil
{
    /** @var bool Whether the database schema is initialized. */
    private static $initialized = false;

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
     * These variables of the $GLOBALS array are filled by PHPUnit based on an XML configuration file.
     *
     * @return Connection The database connection instance.
     */
    public static function getConnection(): Connection
    {
        if (! self::$initialized) {
            self::initializeDatabase();
            self::$initialized = true;
        }

        $conn = DriverManager::getConnection(self::getTestConnectionParameters());

        self::addDbEventSubscribers($conn);

        return $conn;
    }

    public static function getPrivilegedConnection(): Connection
    {
        return DriverManager::getConnection(self::getPrivilegedConnectionParameters());
    }

    private static function initializeDatabase(): void
    {
        $testConnParams = self::getTestConnectionParameters();
        $privConnParams = self::getPrivilegedConnectionParameters();

        $testConn = DriverManager::getConnection($testConnParams);

        // Note, writes direct to STDOUT to prevent phpunit detecting output - otherwise this would cause either an
        // "unexpected output" warning or a failure on the first test case to call this method.
        fwrite(STDOUT, sprintf("\nUsing DB driver %s\n", get_class($testConn->getDriver())));

        // Connect as a privileged user to create and drop the test database.
        $privConn = DriverManager::getConnection($privConnParams);

        $platform = $privConn->getDatabasePlatform();

        if ($platform->supportsCreateDropDatabase()) {
            $dbname = $testConn->getDatabase();
            $testConn->close();

            $privConn->getSchemaManager()->dropAndCreateDatabase($dbname);

            $privConn->close();
        } else {
            $sm = $testConn->getSchemaManager();

            $schema = $sm->createSchema();
            $stmts  = $schema->toDropSql($testConn->getDatabasePlatform());

            foreach ($stmts as $stmt) {
                $testConn->exec($stmt);
            }
        }
    }

    private static function addDbEventSubscribers(Connection $conn): void
    {
        if (! isset($GLOBALS['db_event_subscribers'])) {
            return;
        }

        $evm = $conn->getEventManager();

        /** @psalm-var class-string<EventSubscriber> $subscriberClass */
        foreach (explode(',', $GLOBALS['db_event_subscribers']) as $subscriberClass) {
            $subscriberInstance = new $subscriberClass();
            $evm->addEventSubscriber($subscriberInstance);
        }
    }

    /**
     * @return array<string,mixed>
     * @psalm-return ConnectionParams
     */
    private static function getPrivilegedConnectionParameters(): array
    {
        if (isset($GLOBALS['privileged_db_driver'])) {
            return self::mapConnectionParameters($GLOBALS, 'privileged_db_');
        }

        $parameters = self::mapConnectionParameters($GLOBALS, 'db_');
        unset($parameters['dbname']);

        return $parameters;
    }

    /**
     * @return array<string,mixed>
     * @psalm-return ConnectionParams
     */
    public static function getTestConnectionParameters(): array
    {
        if (! isset($GLOBALS['db_driver'])) {
            throw new UnexpectedValueException(
                'You must provide db connection params including a db_driver value. See phpunit.xml.dist for details'
            );
        }

        return self::mapConnectionParameters($GLOBALS, 'db_');
    }

    /**
     * @param array<string,mixed> $configuration
     *
     * @return array<string,mixed>
     * @psalm-return ConnectionParams
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
                'port',
                'server',
                'memory',
                'ssl_key',
                'ssl_cert',
                'ssl_ca',
                'ssl_capath',
                'ssl_cipher',
                'unix_socket',
            ] as $parameter
        ) {
            if (! isset($configuration[$prefix . $parameter])) {
                continue;
            }

            $parameters[$parameter] = $configuration[$prefix . $parameter];
        }

        foreach ($configuration as $param => $value) {
            if (strpos($param, $prefix . 'driver_option_') !== 0) {
                continue;
            }

            $parameters['driverOptions'][substr($param, strlen($prefix . 'driver_option_'))] = $value;
        }

        return $parameters;
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
                }, array_keys($row), array_values($row)))
            );
        }, $rows));
    }
}
