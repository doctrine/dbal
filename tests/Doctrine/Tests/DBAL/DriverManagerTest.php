<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\DBALException;
use Doctrine\Tests\Mocks\PDOMock;

class DriverManagerTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testInvalidPdoInstance()
    {
        $options = [
            'pdo' => 'test'
        ];
        $test = \Doctrine\DBAL\DriverManager::getConnection($options);
    }

    public function testValidPdoInstance()
    {
        $options = [
            'pdo' => new \PDO('sqlite::memory:')
        ];
        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        self::assertEquals('sqlite', $conn->getDatabasePlatform()->getName());
    }

    /**
     * @group DBAL-32
     */
    public function testPdoInstanceSetErrorMode()
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
        $options = [
            'pdo' => $pdo
        ];

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        self::assertEquals(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testCheckParams()
    {
        $conn = \Doctrine\DBAL\DriverManager::getConnection([]);
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testInvalidDriver()
    {
        $conn = \Doctrine\DBAL\DriverManager::getConnection(['driver' => 'invalid_driver']);
    }

    public function testCustomPlatform()
    {
        $mockPlatform = new \Doctrine\Tests\DBAL\Mocks\MockPlatform();
        $options = [
            'pdo' => new \PDO('sqlite::memory:'),
            'platform' => $mockPlatform
        ];

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        self::assertSame($mockPlatform, $conn->getDatabasePlatform());
    }

    public function testCustomWrapper()
    {
        $wrapperClass = 'Doctrine\Tests\Mocks\ConnectionMock';

        $options = [
            'pdo' => new \PDO('sqlite::memory:'),
            'wrapperClass' => $wrapperClass,
        ];

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        self::assertInstanceOf($wrapperClass, $conn);
    }

    public function testInvalidWrapperClass()
    {
        $this->expectException(DBALException::class);

        $options = [
            'pdo' => new \PDO('sqlite::memory:'),
            'wrapperClass' => 'stdClass',
        ];

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
    }

    public function testInvalidDriverClass()
    {
        $this->expectException(DBALException::class);

        $options = [
            'driverClass' => 'stdClass'
        ];

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
    }

    public function testValidDriverClass()
    {
        $options = [
            'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver',
        ];

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        self::assertInstanceOf('Doctrine\DBAL\Driver\PDOMySql\Driver', $conn->getDriver());
    }

    /**
     * @dataProvider databaseUrls
     */
    public function testDatabaseUrl($url, $expected)
    {
        $options = is_array($url) ? $url : [
            'url' => $url,
        ];

        if ($expected === false) {
            $this->expectException(DBALException::class);
        }

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);

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
        $pdoMock = $this->createMock(PDOMock::class);

        return [
            'simple URL' => [
                'mysql://foo:bar@localhost/baz',
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
            'simple URL with port' => [
                'mysql://foo:bar@localhost:11211/baz',
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'port' => 11211, 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
            'sqlite relative URL with host' => [
                'sqlite://localhost/foo/dbname.sqlite',
                ['path' => 'foo/dbname.sqlite', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'],
            ],
            'sqlite absolute URL with host' => [
                'sqlite://localhost//tmp/dbname.sqlite',
                ['path' => '/tmp/dbname.sqlite', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'],
            ],
            'sqlite relative URL without host' => [
                'sqlite:///foo/dbname.sqlite',
                ['path' => 'foo/dbname.sqlite', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'],
            ],
            'sqlite absolute URL without host' => [
                'sqlite:////tmp/dbname.sqlite',
                ['path' => '/tmp/dbname.sqlite', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'],
            ],
            'sqlite memory' => [
                'sqlite:///:memory:',
                ['memory' => true, 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'],
            ],
            'sqlite memory with host' => [
                'sqlite://localhost/:memory:',
                ['memory' => true, 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'],
            ],
            'params parsed from URL override individual params' => [
                ['url' => 'mysql://foo:bar@localhost/baz', 'password' => 'lulz'],
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
            'params not parsed from URL but individual params are preserved' => [
                ['url' => 'mysql://foo:bar@localhost/baz', 'port' => 1234],
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'port' => 1234, 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
            'query params from URL are used as extra params' => [
                'url' => 'mysql://foo:bar@localhost/dbname?charset=UTF-8',
                ['charset' => 'UTF-8'],
            ],
            'simple URL with fallthrough scheme not defined in map' => [
                'sqlsrv://foo:bar@localhost/baz',
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\SQLSrv\Driver'],
            ],
            'simple URL with fallthrough scheme containing underscores fails' => [
                'drizzle_pdo_mysql://foo:bar@localhost/baz',
                false,
            ],
            'simple URL with fallthrough scheme containing dashes works' => [
                'drizzle-pdo-mysql://foo:bar@localhost/baz',
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\DrizzlePDOMySql\Driver'],
            ],
            'simple URL with percent encoding' => [
                'mysql://foo%3A:bar%2F@localhost/baz+baz%40',
                ['user' => 'foo:', 'password' => 'bar/', 'host' => 'localhost', 'dbname' => 'baz+baz@', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
            'simple URL with percent sign in password' => [
                'mysql://foo:bar%25bar@localhost/baz',
                ['user' => 'foo', 'password' => 'bar%bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],

            // DBAL-1234
            'URL without scheme and without any driver information' => [
                ['url' => '//foo:bar@localhost/baz'],
                false,
            ],
            'URL without scheme but default PDO driver' => [
                ['url' => '//foo:bar@localhost/baz', 'pdo' => $pdoMock],
                false,
            ],
            'URL without scheme but default driver' => [
                ['url' => '//foo:bar@localhost/baz', 'driver' => 'pdo_mysql'],
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
            'URL without scheme but custom driver' => [
                ['url' => '//foo:bar@localhost/baz', 'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'],
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'],
            ],
            'URL without scheme but default PDO driver and default driver' => [
                ['url' => '//foo:bar@localhost/baz', 'pdo' => $pdoMock, 'driver' => 'pdo_mysql'],
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
            'URL without scheme but driver and custom driver' => [
                ['url' => '//foo:bar@localhost/baz', 'driver' => 'pdo_mysql', 'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'],
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'],
            ],
            'URL with default PDO driver' => [
                ['url' => 'mysql://foo:bar@localhost/baz', 'pdo' => $pdoMock],
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
            'URL with default driver' => [
                ['url' => 'mysql://foo:bar@localhost/baz', 'driver' => 'sqlite'],
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
            'URL with default custom driver' => [
                ['url' => 'mysql://foo:bar@localhost/baz', 'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'],
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
            'URL with default PDO driver and default driver' => [
                ['url' => 'mysql://foo:bar@localhost/baz', 'pdo' => $pdoMock, 'driver' => 'sqlite'],
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
            'URL with default driver and default custom driver' => [
                ['url' => 'mysql://foo:bar@localhost/baz', 'driver' => 'sqlite',  'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'],
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
            'URL with default PDO driver and default driver and default custom driver' => [
                ['url' => 'mysql://foo:bar@localhost/baz', 'pdo' => $pdoMock, 'driver' => 'sqlite', 'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'],
                ['user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'],
            ],
        ];
    }
}
