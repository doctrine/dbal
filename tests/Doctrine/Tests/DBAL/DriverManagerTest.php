<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\DrizzlePDOMySql\Driver as DrizzlePDOMySqlDriver;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PDOMySQLDriver;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as PDOSQLiteDriver;
use Doctrine\DBAL\Driver\SQLSrv\Driver as SQLSrvDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Sharding\PoolingShardConnection;
use Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser;
use Doctrine\Tests\DbalTestCase;
use PDO;
use stdClass;

use function array_intersect_key;
use function array_merge;
use function extension_loaded;
use function get_class;
use function in_array;
use function is_array;

class DriverManagerTest extends DbalTestCase
{
    /**
     * @requires extension pdo_sqlite
     */
    public function testInvalidPdoInstance(): void
    {
        $this->expectException(Exception::class);
        DriverManager::getConnection(['pdo' => 'test']);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testValidPdoInstance(): void
    {
        $conn = DriverManager::getConnection([
            'pdo' => new PDO('sqlite::memory:'),
        ]);

        self::assertEquals('sqlite', $conn->getDatabasePlatform()->getName());
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testPdoInstanceSetErrorMode(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $options = ['pdo' => $pdo];

        DriverManager::getConnection($options);
        self::assertEquals(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testCheckParams(): void
    {
        $this->expectException(Exception::class);

        DriverManager::getConnection([]);
    }

    public function testInvalidDriver(): void
    {
        $this->expectException(Exception::class);

        DriverManager::getConnection(['driver' => 'invalid_driver']);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testCustomPlatform(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $options  = [
            'pdo'      => new PDO('sqlite::memory:'),
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
            'pdo' => new PDO('sqlite::memory:'),
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
        $this->expectException(Exception::class);

        $options = [
            'pdo' => new PDO('sqlite::memory:'),
            'wrapperClass' => stdClass::class,
        ];

        DriverManager::getConnection($options);
    }

    public function testInvalidDriverClass(): void
    {
        $this->expectException(Exception::class);

        $options = ['driverClass' => stdClass::class];

        DriverManager::getConnection($options);
    }

    public function testValidDriverClass(): void
    {
        $options = ['driverClass' => PDOMySQLDriver::class];

        $conn = DriverManager::getConnection($options);
        self::assertInstanceOf(PDOMySQLDriver::class, $conn->getDriver());
    }

    public function testDatabaseUrlPrimaryReplica(): void
    {
        $options = [
            'driver' => 'pdo_mysql',
            'primary' => ['url' => 'mysql://foo:bar@localhost:11211/baz'],
            'replica' => [
                'replica1' => ['url' => 'mysql://foo:bar@localhost:11211/baz_replica'],
            ],
            'wrapperClass' => PrimaryReadReplicaConnection::class,
        ];

        $conn = DriverManager::getConnection($options);

        $params = $conn->getParams();
        self::assertInstanceOf(PDOMySQLDriver::class, $conn->getDriver());

        $expected = [
            'user'     => 'foo',
            'password' => 'bar',
            'host'     => 'localhost',
            'port'     => 11211,
            'dbname'   => 'baz',
            'driver'   => 'pdo_mysql',
            'url'      => 'mysql://foo:bar@localhost:11211/baz',
        ];

        self::assertEquals(
            [
                'primary' => $expected,
                'replica' => [
                    'replica1' => array_merge(
                        $expected,
                        [
                            'dbname' => 'baz_replica',
                            'url'    => 'mysql://foo:bar@localhost:11211/baz_replica',
                        ]
                    ),
                ],
            ],
            array_intersect_key($params, ['primary' => null, 'replica' => null])
        );
    }

    public function testDatabaseUrlShard(): void
    {
        $options = [
            'driver' => 'pdo_mysql',
            'shardChoser' => MultiTenantShardChoser::class,
            'global' => ['url' => 'mysql://foo:bar@localhost:11211/baz'],
            'shards' => [
                [
                    'id' => 1,
                    'url' => 'mysql://foo:bar@localhost:11211/baz_replica',
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
            'dbname'   => 'baz',
            'driver'   => 'pdo_mysql',
            'url'      => 'mysql://foo:bar@localhost:11211/baz',
        ];

        self::assertEquals(
            [
                'global' => $expected,
                'shards' => [
                    array_merge(
                        $expected,
                        [
                            'dbname' => 'baz_replica',
                            'id'     => 1,
                            'url'    => 'mysql://foo:bar@localhost:11211/baz_replica',
                        ]
                    ),
                ],
            ],
            array_intersect_key($params, ['global' => null, 'shards' => null])
        );
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

        if (isset($options['pdo'])) {
            if (! extension_loaded('pdo')) {
                $this->markTestSkipped('PDO is not installed');
            }

            $options['pdo'] = $this->createMock(PDO::class);
        }

        $options = is_array($url) ? $url : ['url' => $url];

        if ($expected === false) {
            $this->expectException(Exception::class);
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
                    'driver' => PDOSQLiteDriver::class,
                ],
            ],
            'sqlite absolute URL with host' => [
                'sqlite://localhost//tmp/dbname.sqlite',
                [
                    'path'   => '/tmp/dbname.sqlite',
                    'driver' => PDOSQLiteDriver::class,
                ],
            ],
            'sqlite relative URL without host' => [
                'sqlite:///foo/dbname.sqlite',
                [
                    'path'   => 'foo/dbname.sqlite',
                    'driver' => PDOSQLiteDriver::class,
                ],
            ],
            'sqlite absolute URL without host' => [
                'sqlite:////tmp/dbname.sqlite',
                [
                    'path'   => '/tmp/dbname.sqlite',
                    'driver' => PDOSQLiteDriver::class,
                ],
            ],
            'sqlite memory' => [
                'sqlite:///:memory:',
                [
                    'memory' => true,
                    'driver' => PDOSQLiteDriver::class,
                ],
            ],
            'sqlite memory with host' => [
                'sqlite://localhost/:memory:',
                [
                    'memory' => true,
                    'driver' => PDOSQLiteDriver::class,
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
            'URL with default PDO driver and default driver and default custom driver' => [
                [
                    'url'         => 'mysql://foo:bar@localhost/baz',
                    'pdo'         => true,
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
