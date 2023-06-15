<?php

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Driver\SQLSrv\Driver as SQLSrvDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;
use stdClass;

use function array_intersect_key;
use function array_merge;
use function get_class;
use function in_array;
use function is_array;

/** @psalm-import-type Params from DriverManager */
class DriverManagerTest extends TestCase
{
    use VerifyDeprecations;

    public function testCheckParams(): void
    {
        $this->expectException(Exception::class);

        DriverManager::getConnection([]);
    }

    /** @psalm-suppress InvalidArgument */
    public function testInvalidDriver(): void
    {
        $this->expectException(Exception::class);

        DriverManager::getConnection(['driver' => 'invalid_driver']);
    }

    /** @requires extension pdo_sqlite */
    public function testCustomPlatform(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $options  = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
            'platform' => $platform,
        ];

        $conn = DriverManager::getConnection($options);
        self::assertSame($platform, $conn->getDatabasePlatform());
    }

    /** @requires extension pdo_sqlite */
    public function testCustomWrapper(): void
    {
        $wrapper      = $this->createMock(Connection::class);
        $wrapperClass = get_class($wrapper);

        $options = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
            'wrapperClass' => $wrapperClass,
        ];

        $conn = DriverManager::getConnection($options);
        self::assertInstanceOf($wrapperClass, $conn);
    }

    /** @requires extension pdo_sqlite */
    public function testDefaultWrapper(): void
    {
        $options = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
            'wrapperClass' => Connection::class,
        ];

        $conn = DriverManager::getConnection($options);
        self::assertSame(Connection::class, get_class($conn));
    }

    /**
     * @requires extension pdo_sqlite
     * @psalm-suppress InvalidArgument
     */
    public function testInvalidWrapperClass(): void
    {
        $this->expectException(Exception::class);

        $options = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
            'wrapperClass' => stdClass::class,
        ];

        DriverManager::getConnection($options);
    }

    /** @psalm-suppress InvalidArgument */
    public function testInvalidDriverClass(): void
    {
        $this->expectException(Exception::class);

        $options = ['driverClass' => stdClass::class];

        DriverManager::getConnection($options);
    }

    public function testValidDriverClass(): void
    {
        $options = ['driverClass' => PDO\MySQL\Driver::class];

        $conn = DriverManager::getConnection($options);
        self::assertInstanceOf(PDO\MySQL\Driver::class, $conn->getDriver());
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

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/5843');
        $conn = DriverManager::getConnection($options);

        $params = $conn->getParams();
        self::assertInstanceOf(PDO\MySQL\Driver::class, $conn->getDriver());

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
                        ],
                    ),
                ],
            ],
            array_intersect_key($params, ['primary' => null, 'replica' => null]),
        );
    }

    /**
     * @param array<string, mixed>|false $expected
     * @psalm-param Params|string $url
     *
     * @dataProvider databaseUrlProvider
     */
    public function testDatabaseUrlDeprecated($url, $expected): void
    {
        $options = is_array($url) ? $url : ['url' => $url];

        if ($expected === false) {
            $this->expectException(Exception::class);
        }

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/5843');
        $conn = DriverManager::getConnection($options);

        self::assertNotFalse($expected);

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
     * @param array<string, mixed>|string $url
     * @param array<string, mixed>|false  $expected
     *
     * @dataProvider databaseUrlProvider
     */
    public function testDatabaseUrl($url, $expected): void
    {
        if (is_array($url)) {
            if (isset($url['driverClass'])) {
                self::markTestSkipped(
                    'Legacy test case: Merging driverClass into the parsed parameters has to be done in userland now.',
                );
            }

            ['url' => $url] = $options = $url;
            unset($options['url']);
        } else {
            $options = [];
        }

        $parser  = new DsnParser(['mysql' => 'pdo_mysql', 'sqlite' => 'pdo_sqlite']);
        $options = array_merge($options, $parser->parse($url));

        if ($expected === false) {
            $this->expectException(Exception::class);
        }

        $conn = DriverManager::getConnection($options);

        self::assertNotFalse($expected);

        $params = $conn->getParams();
        foreach ($expected as $key => $value) {
            if (in_array($key, ['driver', 'driverClass'], true)) {
                self::assertInstanceOf($value, $conn->getDriver());
            } else {
                self::assertEquals($value, $params[$key]);
            }
        }
    }

    /** @psalm-return array<string, array{
     *                    string|array<string, mixed>,
     *                    array<string, mixed>|false,
     *                }>
     */
    public static function databaseUrlProvider(): iterable
    {
        return [
            'simple URL' => [
                'mysql://foo:bar@localhost/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDO\MySQL\Driver::class,
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
                    'driver'   => PDO\MySQL\Driver::class,
                ],
            ],
            'sqlite relative URL with host' => [
                'sqlite://localhost/foo/dbname.sqlite',
                [
                    'path'   => 'foo/dbname.sqlite',
                    'driver' => PDO\SQLite\Driver::class,
                ],
            ],
            'sqlite absolute URL with host' => [
                'sqlite://localhost//tmp/dbname.sqlite',
                [
                    'path'   => '/tmp/dbname.sqlite',
                    'driver' => PDO\SQLite\Driver::class,
                ],
            ],
            'sqlite relative URL without host' => [
                'sqlite:///foo/dbname.sqlite',
                [
                    'path'   => 'foo/dbname.sqlite',
                    'driver' => PDO\SQLite\Driver::class,
                ],
            ],
            'sqlite absolute URL without host' => [
                'sqlite:////tmp/dbname.sqlite',
                [
                    'path'   => '/tmp/dbname.sqlite',
                    'driver' => PDO\SQLite\Driver::class,
                ],
            ],
            'sqlite memory' => [
                'sqlite:///:memory:',
                [
                    'memory' => true,
                    'driver' => PDO\SQLite\Driver::class,
                ],
            ],
            'sqlite memory with host' => [
                'sqlite://localhost/:memory:',
                [
                    'memory' => true,
                    'driver' => PDO\SQLite\Driver::class,
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
                    'driver'   => PDO\MySQL\Driver::class,
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
                    'driver'   => PDO\MySQL\Driver::class,
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
                    'driver'   => PDO\MySQL\Driver::class,
                ],
            ],
            'simple URL with percent encoding' => [
                'mysql://foo%3A:bar%2F@localhost/baz+baz%40',
                [
                    'user'     => 'foo:',
                    'password' => 'bar/',
                    'host'     => 'localhost',
                    'dbname'   => 'baz+baz@',
                    'driver'   => PDO\MySQL\Driver::class,
                ],
            ],
            'simple URL with percent sign in password' => [
                'mysql://foo:bar%25bar@localhost/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar%bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDO\MySQL\Driver::class,
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
                    'driver'   => PDO\MySQL\Driver::class,
                ],
            ],
            'URL without scheme but custom driver' => [
                [
                    'url'         => '//foo:bar@localhost/baz',
                    'driverClass' => PDO\MySQL\Driver::class,
                ],
                [
                    'user'        => 'foo',
                    'password'    => 'bar',
                    'host'        => 'localhost',
                    'dbname'      => 'baz',
                    'driverClass' => PDO\MySQL\Driver::class,
                ],
            ],
            'URL without scheme but driver and custom driver' => [
                [
                    'url'         => '//foo:bar@localhost/baz',
                    'driver'      => 'pdo_mysql',
                    'driverClass' => PDO\MySQL\Driver::class,
                ],
                [
                    'user'        => 'foo',
                    'password'    => 'bar',
                    'host'        => 'localhost',
                    'dbname'      => 'baz',
                    'driverClass' => PDO\MySQL\Driver::class,
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
                    'driver'   => PDO\MySQL\Driver::class,
                ],
            ],
            'URL with default custom driver' => [
                [
                    'url'         => 'mysql://foo:bar@localhost/baz',
                    'driverClass' => PDO\MySQL\Driver::class,
                ],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDO\MySQL\Driver::class,
                ],
            ],
            'URL with default driver and default custom driver' => [
                [
                    'url'         => 'mysql://foo:bar@localhost/baz',
                    'driver'      => 'sqlite',
                    'driverClass' => PDO\MySQL\Driver::class,
                ],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDO\MySQL\Driver::class,
                ],
            ],
        ];
    }
}
