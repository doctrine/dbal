<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Driver\SQLSrv\Driver as SQLSrvDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use stdClass;

use function array_merge;
use function in_array;

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

    #[RequiresPhpExtension('sqlite3')]
    public function testCustomWrapper(): void
    {
        $wrapper      = $this->createMock(Connection::class);
        $wrapperClass = $wrapper::class;

        $options = [
            'driver' => 'sqlite3',
            'memory' => true,
            'wrapperClass' => $wrapperClass,
        ];

        $conn = DriverManager::getConnection($options);
        self::assertInstanceOf($wrapperClass, $conn);
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testDefaultWrapper(): void
    {
        $options = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
            'wrapperClass' => Connection::class,
        ];

        $conn = DriverManager::getConnection($options);
        self::assertSame(Connection::class, $conn::class);
    }

    /** @psalm-suppress InvalidArgument */
    #[RequiresPhpExtension('pdo_sqlite')]
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

    /**
     * @param Params                     $params
     * @param array<string, mixed>|false $expected
     */
    #[DataProvider('databaseUrlProvider')]
    public function testDatabaseUrl(string $url, array $params, array|false $expected): void
    {
        $parser = new DsnParser(['mysql' => 'pdo_mysql', 'sqlite' => 'pdo_sqlite']);
        $params = array_merge($params, $parser->parse($url));

        if ($expected === false) {
            $this->expectException(Exception::class);
        }

        $conn = DriverManager::getConnection($params);

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
     *                    string,
     *                    array<string, mixed>,
     *                    array<string, mixed>|false,
     *                }>
     */
    public static function databaseUrlProvider(): iterable
    {
        return [
            'simple URL' => [
                'pdo-mysql://foo:bar@localhost/baz',
                [],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDO\MySQL\Driver::class,
                ],
            ],
            'simple URL with port' => [
                'pdo-mysql://foo:bar@localhost:11211/baz',
                [],
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
                'pdo-sqlite://localhost/foo/dbname.sqlite',
                [],
                [
                    'path'   => 'foo/dbname.sqlite',
                    'driver' => PDO\SQLite\Driver::class,
                ],
            ],
            'sqlite absolute URL with host' => [
                'pdo-sqlite://localhost//tmp/dbname.sqlite',
                [],
                [
                    'path'   => '/tmp/dbname.sqlite',
                    'driver' => PDO\SQLite\Driver::class,
                ],
            ],
            'sqlite relative URL without host' => [
                'pdo-sqlite:///foo/dbname.sqlite',
                [],
                [
                    'path'   => 'foo/dbname.sqlite',
                    'driver' => PDO\SQLite\Driver::class,
                ],
            ],
            'sqlite absolute URL without host' => [
                'pdo-sqlite:////tmp/dbname.sqlite',
                [],
                [
                    'path'   => '/tmp/dbname.sqlite',
                    'driver' => PDO\SQLite\Driver::class,
                ],
            ],
            'sqlite memory' => [
                'pdo-sqlite:///:memory:',
                [],
                [
                    'memory' => true,
                    'driver' => PDO\SQLite\Driver::class,
                ],
            ],
            'sqlite memory with host' => [
                'pdo-sqlite://localhost/:memory:',
                [],
                [
                    'memory' => true,
                    'driver' => PDO\SQLite\Driver::class,
                ],
            ],
            'params parsed from URL override individual params' => [
                'pdo-mysql://foo:bar@localhost/baz',
                ['password' => 'lulz'],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDO\MySQL\Driver::class,
                ],
            ],
            'params not parsed from URL but individual params are preserved' => [
                'pdo-mysql://foo:bar@localhost/baz',
                ['port' => 1234],
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
                'pdo-mysql://foo:bar@localhost/dbname?charset=UTF-8',
                [],
                ['charset' => 'UTF-8'],
            ],
            'simple URL with fallthrough scheme not defined in map' => [
                'sqlsrv://foo:bar@localhost/baz',
                [],
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
                [],
                false,
            ],
            'simple URL with fallthrough scheme containing dashes works' => [
                'pdo-mysql://foo:bar@localhost/baz',
                [],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDO\MySQL\Driver::class,
                ],
            ],
            'simple URL with percent encoding' => [
                'pdo-mysql://foo%3A:bar%2F@localhost/baz+baz%40',
                [],
                [
                    'user'     => 'foo:',
                    'password' => 'bar/',
                    'host'     => 'localhost',
                    'dbname'   => 'baz+baz@',
                    'driver'   => PDO\MySQL\Driver::class,
                ],
            ],
            'simple URL with percent sign in password' => [
                'pdo-mysql://foo:bar%25bar@localhost/baz',
                [],
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
                '//foo:bar@localhost/baz',
                [],
                false,
            ],
            'URL without scheme but default driver' => [
                '//foo:bar@localhost/baz',
                ['driver' => 'pdo_mysql'],
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => PDO\MySQL\Driver::class,
                ],
            ],
            'URL without scheme but custom driver' => [
                '//foo:bar@localhost/baz',
                ['driverClass' => PDO\MySQL\Driver::class],
                [
                    'user'        => 'foo',
                    'password'    => 'bar',
                    'host'        => 'localhost',
                    'dbname'      => 'baz',
                    'driverClass' => PDO\MySQL\Driver::class,
                ],
            ],
        ];
    }
}
