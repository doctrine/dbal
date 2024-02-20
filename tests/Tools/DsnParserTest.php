<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Tools;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function get_class;
use function ksort;

/** @psalm-import-type Params from DriverManager */
final class DsnParserTest extends TestCase
{
    /** @psalm-param Params $expected */
    #[DataProvider('databaseUrls')]
    public function testDatabaseUrl(string $dsn, array $expected): void
    {
        $parser = new DsnParser(['mysql' => 'mysqli', 'sqlite' => 'sqlite3']);
        $actual = $parser->parse($dsn);

        // We don't care about the order of the array keys, so let's normalize both
        // arrays before comparing them.
        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    /** @psalm-return iterable<string, array{string, Params}> */
    public static function databaseUrls(): iterable
    {
        return [
            'simple URL' => [
                'mysql://foo:bar@localhost/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => 'mysqli',
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
                    'driver'   => 'mysqli',
                ],
            ],
            'sqlite relative URL with host' => [
                'sqlite://localhost/foo/dbname.sqlite',
                [
                    'host' => 'localhost',
                    'path'   => 'foo/dbname.sqlite',
                    'driver' => 'sqlite3',
                ],
            ],
            'sqlite absolute URL with host' => [
                'sqlite://localhost//tmp/dbname.sqlite',
                [
                    'host' => 'localhost',
                    'path'   => '/tmp/dbname.sqlite',
                    'driver' => 'sqlite3',
                ],
            ],
            'sqlite relative URL without host' => [
                'sqlite:///foo/dbname.sqlite',
                [
                    'host' => 'localhost',
                    'path'   => 'foo/dbname.sqlite',
                    'driver' => 'sqlite3',
                ],
            ],
            'pdo-sqlite relative URL without host' => [
                'pdo-sqlite:///foo/dbname.sqlite',
                [
                    'host' => 'localhost',
                    'path'   => 'foo/dbname.sqlite',
                    'driver' => 'pdo_sqlite',
                ],
            ],
            'sqlite absolute URL without host' => [
                'sqlite:////tmp/dbname.sqlite',
                [
                    'host' => 'localhost',
                    'path'   => '/tmp/dbname.sqlite',
                    'driver' => 'sqlite3',
                ],
            ],
            'sqlite memory' => [
                'sqlite:///:memory:',
                [
                    'host' => 'localhost',
                    'memory' => true,
                    'driver' => 'sqlite3',
                ],
            ],
            'sqlite memory with host' => [
                'sqlite://localhost/:memory:',
                [
                    'host' => 'localhost',
                    'memory' => true,
                    'driver' => 'sqlite3',
                ],
            ],
            'query params from URL are used as extra params' => [
                'mysql://foo:bar@localhost/dbname?charset=UTF-8',
                [
                    'user' => 'foo',
                    'password' => 'bar',
                    'host' => 'localhost',
                    'dbname' => 'dbname',
                    'charset' => 'UTF-8',
                    'driver' => 'mysqli',
                ],
            ],
            'simple URL with fallthrough scheme not defined in map' => [
                'sqlsrv://foo:bar@localhost/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => 'sqlsrv',
                ],
            ],
            'simple URL with fallthrough scheme containing dashes works' => [
                'pdo-mysql://foo:bar@localhost/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => 'pdo_mysql',
                ],
            ],
            'simple URL with percent encoding' => [
                'mysql://foo%3A:bar%2F@localhost/baz+baz%40',
                [
                    'user'     => 'foo:',
                    'password' => 'bar/',
                    'host'     => 'localhost',
                    'dbname'   => 'baz+baz@',
                    'driver'   => 'mysqli',
                ],
            ],
            'simple URL with percent sign in password' => [
                'mysql://foo:bar%25bar@localhost/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar%bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                    'driver'   => 'mysqli',
                ],
            ],
            'URL without scheme' => [
                '//foo:bar@localhost/baz',
                [
                    'user'     => 'foo',
                    'password' => 'bar',
                    'host'     => 'localhost',
                    'dbname'   => 'baz',
                ],
            ],
        ];
    }

    public function testDriverClassScheme(): void
    {
        $driverClass = get_class($this->createMock(Driver::class));
        $parser      = new DsnParser(['custom' => $driverClass]);
        $actual      = $parser->parse('custom://foo:bar@localhost/baz');

        self::assertSame(
            [
                'host'        => 'localhost',
                'user'        => 'foo',
                'password'    => 'bar',
                'driverClass' => $driverClass,
                'dbname'      => 'baz',
            ],
            $actual,
        );
    }
}
