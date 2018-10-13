<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Connections\MasterSlaveConnection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\DrizzlePDOMySql\Driver as DrizzlePDOMySqlDriver;
use Doctrine\DBAL\Driver\PDOMySql\Driver as PDOMySQLDriver;
use Doctrine\DBAL\Driver\PDOSqlite\Driver as PDOSqliteDriver;
use Doctrine\DBAL\Driver\SQLSrv\Driver as SQLSrvDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Sharding\PoolingShardConnection;
use Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;
use Doctrine\Tests\Mocks\ConnectionMock;
use Doctrine\Tests\Mocks\DriverMock;
use PDO;
use stdClass;
use function extension_loaded;
use function in_array;
use function is_array;

class DriverManagerTest extends DbalTestCase
{
    /**
     * @requires extension pdo_sqlite
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testInvalidPdoInstance()
    {
        DriverManager::getConnection(['pdo' => 'test']);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testValidPdoInstance()
    {
        $conn = DriverManager::getConnection([
            'pdo' => new PDO('sqlite::memory:'),
        ]);

        self::assertEquals('sqlite', $conn->getDatabasePlatform()->getName());
    }

    /**
     * @group DBAL-32
     * @requires extension pdo_sqlite
     */
    public function testPdoInstanceSetErrorMode()
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $options = ['pdo' => $pdo];

        DriverManager::getConnection($options);
        self::assertEquals(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testCheckParams()
    {
        DriverManager::getConnection([]);
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testInvalidDriver()
    {
        DriverManager::getConnection(['driver' => 'invalid_driver']);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testCustomPlatform()
    {
        $mockPlatform = new MockPlatform();
        $options      = [
            'pdo'      => new PDO('sqlite::memory:'),
            'platform' => $mockPlatform,
        ];

        $conn = DriverManager::getConnection($options);
        self::assertSame($mockPlatform, $conn->getDatabasePlatform());
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testCustomWrapper()
    {
        $wrapperClass = ConnectionMock::class;

        $options = [
            'pdo' => new PDO('sqlite::memory:'),
            'wrapperClass' => $wrapperClass,
        ];

        $conn = DriverManager::getConnection($options);
        self::assertInstanceOf($wrapperClass, $conn);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testInvalidWrapperClass()
    {
        $this->expectException(DBALException::class);

        $options = [
            'pdo' => new PDO('sqlite::memory:'),
            'wrapperClass' => stdClass::class,
        ];

        DriverManager::getConnection($options);
    }

    public function testInvalidDriverClass()
    {
        $this->expectException(DBALException::class);

        $options = ['driverClass' => stdClass::class];

        DriverManager::getConnection($options);
    }

    public function testValidDriverClass()
    {
        $options = ['driverClass' => PDOMySQLDriver::class];

        $conn = DriverManager::getConnection($options);
        self::assertInstanceOf(PDOMySQLDriver::class, $conn->getDriver());
    }

    public function testDatabaseUrlMasterSlave()
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

    public function testDatabaseUrlShard()
    {
        $options = [
            'driver' => 'pdo_mysql',
            'shardChoser' => MultiTenantShardChoser::class,
            'global' => ['url' => 'mysql://foo:bar@localhost:11211/baz'],
            'shards' => [
                [
                    'id' => 1,
                    'url' => 'mysql://foo:bar@localhost:11211/baz_slave',
                ],
            ],
            'wrapperClass' => PoolingShardConnection::class,
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
            self::assertEquals($value, $params['global'][$key]);
            self::assertEquals($value, $params['shards'][0][$key]);
        }

        self::assertEquals('baz', $params['global']['dbname']);
        self::assertEquals('baz_slave', $params['shards'][0]['dbname']);
    }

    /**
     * @dataProvider databaseUrls
     */
    public function testDatabaseUrl($url, $expected)
    {
        $options = is_array($url) ? $url : ['url' => $url];

        if (isset($options['pdo'])) {
            if (! extension_loaded('pdo')) {
                $this->markTestSkipped('PDO is not installed');
            }

            $options['pdo'] = $this->createMock(PDO::class);
        }

        $options = is_array($url) ? $url : ['url' => $url];

        if ($expected === false) {
            $this->expectException(DBALException::class);
        }

        $conn = DriverManager::getConnection($options);

        $params = $conn->getParams();
        foreach ($expected as $key => $value) {
            if (in_array($key, ['pdo', 'driver', 'driverClass'], true)) {
                self::assertInstanceOf($value, $conn->getDriver());
            } else {
                self::assertEquals($value, $params[$key]);
            }
        }
    }

    public function databaseUrls()
    {
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
                'url' => 'mysql://foo:bar@localhost/dbname?charset=UTF-8',
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
                'drizzle_pdo_mysql://foo:bar@localhost/baz',
                false,
            ],
            'simple URL with fallthrough scheme containing dashes works' => [
                'drizzle-pdo-mysql://foo:bar@localhost/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => DrizzlePDOMySqlDriver::class,
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
            'URL without scheme but default PDO driver' => [
                [
                    'url' => '//foo:bar@localhost/baz',
                    'pdo' => true,
                ],
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
                    'driverClass' => DriverMock::class,
                ],
                [
                    'user'        => 'foo',
                    'password'    => 'bar',
                    'host'        => 'localhost',
                    'dbname'      => 'baz',
                    'driverClass' => DriverMock::class,
                ],
            ],
            'URL without scheme but default PDO driver and default driver' => [
                [
                    'url'    => '//foo:bar@localhost/baz',
                    'pdo'    => true,
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
            'URL without scheme but driver and custom driver' => [
                [
                    'url'         => '//foo:bar@localhost/baz',
                    'driver'      => 'pdo_mysql',
                    'driverClass' => DriverMock::class,
                ],
                [
                    'user'        => 'foo',
                    'password'    => 'bar',
                    'host'        => 'localhost',
                    'dbname'      => 'baz',
                    'driverClass' => DriverMock::class,
                ],
            ],
            'URL with default PDO driver' => [
                [
                    'url' => 'mysql://foo:bar@localhost/baz',
                    'pdo' => true,
                ],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
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
                    'driverClass' => DriverMock::class,
                ],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],
            'URL with default PDO driver and default driver' => [
                [
                    'url'    => 'mysql://foo:bar@localhost/baz',
                    'pdo'    => true,
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
            'URL with default driver and default custom driver' => [
                [
                    'url'         => 'mysql://foo:bar@localhost/baz',
                    'driver'      => 'sqlite',
                    'driverClass' => DriverMock::class,
                ],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDOMySQLDriver::class,
                ],
            ],
            'URL with default PDO driver and default driver and default custom driver' => [
                [
                    'url'         => 'mysql://foo:bar@localhost/baz',
                    'pdo'         => true,
                    'driver'      => 'sqlite',
                    'driverClass' => DriverMock::class,
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
