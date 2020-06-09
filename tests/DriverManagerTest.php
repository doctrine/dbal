<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\MasterSlaveConnection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDOMySql\Driver as PDOMySQLDriver;
use Doctrine\DBAL\Driver\PDOSqlite\Driver as PDOSqliteDriver;
use Doctrine\DBAL\Driver\SQLSrv\Driver as SQLSrvDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;
use stdClass;

use function get_class;
use function in_array;
use function is_array;

class DriverManagerTest extends TestCase
{
    public function testCheckParams(): void
    {
        $this->expectException(DBALException::class);

        DriverManager::getConnection([]);
    }

    public function testInvalidDriver(): void
    {
        $this->expectException(DBALException::class);

        DriverManager::getConnection(['driver' => 'invalid_driver']);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testCustomPlatform(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $options  = [
            'url' => 'sqlite::memory:',
            'platform' => $platform,
        ];

        $conn = DriverManager::getConnection($options);
        self::assertSame($platform, $conn->getDatabasePlatform());
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testCustomWrapper(): void
    {
        $wrapper      = $this->createMock(Connection::class);
        $wrapperClass = get_class($wrapper);

        $options = [
            'url' => 'sqlite::memory:',
            'wrapperClass' => $wrapperClass,
        ];

        $conn = DriverManager::getConnection($options);
        self::assertInstanceOf($wrapperClass, $conn);
    }

    /**
     * @requires extension pdo_sqlite
     * @psalm-suppress InvalidArgument
     */
    public function testInvalidWrapperClass(): void
    {
        $this->expectException(DBALException::class);

        $options = [
            'url' => 'sqlite::memory:',
            'wrapperClass' => stdClass::class,
        ];

        DriverManager::getConnection($options);
    }

    public function testInvalidDriverClass(): void
    {
        $this->expectException(DBALException::class);

        $options = ['driverClass' => stdClass::class];

        DriverManager::getConnection($options);
    }

    public function testValidDriverClass(): void
    {
        $options = ['driverClass' => PDOMySQLDriver::class];

        $conn = DriverManager::getConnection($options);
        self::assertInstanceOf(PDOMySQLDriver::class, $conn->getDriver());
    }

    public function testDatabaseUrlMasterSlave(): void
    {
        $options = [
            'driver' => 'pdo_mysql',
            'master' => ['url' => 'mysql://foo:bar@localhost:11211/baz'],
            'slaves' => [
                'slave1' => ['url' => 'mysql://foo:bar@localhost:11211/baz_slave'],
            ],
            'wrapperClass' => MasterSlaveConnection::class,
        ];

        $conn = DriverManager::getConnection($options);

        $params = $conn->getParams();
        self::assertInstanceOf(PDOMySQLDriver::class, $conn->getDriver());

        $expected = [
            'user'     => 'foo',
            'password' => 'bar',
            'host'     => 'localhost',
            'port'     => 11211,
        ];

        foreach ($expected as $key => $value) {
            self::assertEquals($value, $params['master'][$key]);
            self::assertEquals($value, $params['slaves']['slave1'][$key]);
        }

        self::assertEquals('baz', $params['master']['dbname']);
        self::assertEquals('baz_slave', $params['slaves']['slave1']['dbname']);
    }

    /**
     * @param mixed $url
     * @param mixed $expected
     *
     * @dataProvider databaseUrls
     */
    public function testDatabaseUrl($url, $expected): void
    {
        $options = is_array($url) ? $url : ['url' => $url];

        if ($expected === false) {
            $this->expectException(DBALException::class);
        }

        $conn = DriverManager::getConnection($options);

        $params = $conn->getParams();
        foreach ($expected as $key => $value) {
            if (in_array($key, ['driver', 'driverClass'], true)) {
                self::assertInstanceOf($value, $conn->getDriver());
            } else {
                self::assertEquals($value, $params[$key]);
            }
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function databaseUrls(): iterable
    {
        $driver      = $this->createMock(Driver::class);
        $driverClass = get_class($driver);

        return [
            'simple URL' => [
                'mysql://foo:bar@localhost/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],
            'simple URL with port' => [
                'mysql://foo:bar@localhost:11211/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'port'     => 11211,
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],
            'sqlite relative URL with host' => [
                'sqlite://localhost/foo/dbname.sqlite',
                [
                    'path'   => 'foo/dbname.sqlite',
                    'driver' => PDOSqliteDriver::class,
                ],
            ],
            'sqlite absolute URL with host' => [
                'sqlite://localhost//tmp/dbname.sqlite',
                [
                    'path'   => '/tmp/dbname.sqlite',
                    'driver' => PDOSqliteDriver::class,
                ],
            ],
            'sqlite relative URL without host' => [
                'sqlite:///foo/dbname.sqlite',
                [
                    'path'   => 'foo/dbname.sqlite',
                    'driver' => PDOSqliteDriver::class,
                ],
            ],
            'sqlite absolute URL without host' => [
                'sqlite:////tmp/dbname.sqlite',
                [
                    'path'   => '/tmp/dbname.sqlite',
                    'driver' => PDOSqliteDriver::class,
                ],
            ],
            'sqlite memory' => [
                'sqlite:///:memory:',
                [
                    'memory' => true,
                    'driver' => PDOSqliteDriver::class,
                ],
            ],
            'sqlite memory with host' => [
                'sqlite://localhost/:memory:',
                [
                    'memory' => true,
                    'driver' => PDOSqliteDriver::class,
                ],
            ],
            'params parsed from URL override individual params' => [
                [
                    'url'      => 'mysql://foo:bar@localhost/baz',
                    'password' => 'lulz',
                ],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],
            'params not parsed from URL but individual params are preserved' => [
                [
                    'url'  => 'mysql://foo:bar@localhost/baz',
                    'port' => 1234,
                ],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'port'     => 1234,
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],
            'query params from URL are used as extra params' => [
                'mysql://foo:bar@localhost/dbname?charset=UTF-8',
                ['charset' => 'UTF-8'],
            ],
            'simple URL with fallthrough scheme not defined in map' => [
                'sqlsrv://foo:bar@localhost/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => SQLSrvDriver::class,
                ],
            ],
            'simple URL with fallthrough scheme containing underscores fails' => [
                'pdo_mysql://foo:bar@localhost/baz',
                false,
            ],
            'simple URL with fallthrough scheme containing dashes works' => [
                'pdo-mysql://foo:bar@localhost/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],
            'simple URL with percent encoding' => [
                'mysql://foo%3A:bar%2F@localhost/baz+baz%40',
                [
                    'user'     => 'foo:',
                    'password' => 'bar/',
                    'host'     => 'localhost',
                    'dbname'   => 'baz+baz@',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],
            'simple URL with percent sign in password' => [
                'mysql://foo:bar%25bar@localhost/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar%bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],

            // DBAL-1234
            'URL without scheme and without any driver information' => [
                ['url' => '//foo:bar@localhost/baz'],
                false,
            ],
            'URL without scheme but default driver' => [
                [
                    'url'    => '//foo:bar@localhost/baz',
                    'driver' => 'pdo_mysql',
                ],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],
            'URL without scheme but custom driver' => [
                [
                    'url'         => '//foo:bar@localhost/baz',
                    'driverClass' => $driverClass,
                ],
                [
                    'user'        => 'foo',
                    'password'    => 'bar',
                    'host'        => 'localhost',
                    'dbname'      => 'baz',
                    'driverClass' => $driverClass,
                ],
            ],
            'URL without scheme but driver and custom driver' => [
                [
                    'url'         => '//foo:bar@localhost/baz',
                    'driver'      => 'pdo_mysql',
                    'driverClass' => $driverClass,
                ],
                [
                    'user'        => 'foo',
                    'password'    => 'bar',
                    'host'        => 'localhost',
                    'dbname'      => 'baz',
                    'driverClass' => $driverClass,
                ],
            ],
            'URL with default driver' => [
                [
                    'url'    => 'mysql://foo:bar@localhost/baz',
                    'driver' => 'sqlite',
                ],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],
            'URL with default custom driver' => [
                [
                    'url'         => 'mysql://foo:bar@localhost/baz',
                    'driverClass' => $driverClass,
                ],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],
            'URL with default driver and default custom driver' => [
                [
                    'url'         => 'mysql://foo:bar@localhost/baz',
                    'driver'      => 'sqlite',
                    'driverClass' => $driverClass,
                ],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],
        ];
    }
}
