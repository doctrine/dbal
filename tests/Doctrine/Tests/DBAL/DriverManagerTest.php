<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\DBALException;

class DriverManagerTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @requires extension pdo_sqlite
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testInvalidPdoInstance()
    {
        $options = array(
            'pdo' => 'test'
        );
        $test = \Doctrine\DBAL\DriverManager::getConnection($options);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testValidPdoInstance()
    {
        $options = array(
            'pdo' => new \PDO('sqlite::memory:')
        );
        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        self::assertEquals('sqlite', $conn->getDatabasePlatform()->getName());
    }

    /**
     * @group DBAL-32
     * @requires extension pdo_sqlite
     */
    public function testPdoInstanceSetErrorMode()
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
        $options = array(
            'pdo' => $pdo
        );

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        self::assertEquals(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testCheckParams()
    {
        $conn = \Doctrine\DBAL\DriverManager::getConnection(array());
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testInvalidDriver()
    {
        $conn = \Doctrine\DBAL\DriverManager::getConnection(array('driver' => 'invalid_driver'));
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testCustomPlatform()
    {
        $mockPlatform = new \Doctrine\Tests\DBAL\Mocks\MockPlatform();
        $options = array(
            'pdo' => new \PDO('sqlite::memory:'),
            'platform' => $mockPlatform
        );

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        self::assertSame($mockPlatform, $conn->getDatabasePlatform());
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testCustomWrapper()
    {
        $wrapperClass = 'Doctrine\Tests\Mocks\ConnectionMock';

        $options = array(
            'pdo' => new \PDO('sqlite::memory:'),
            'wrapperClass' => $wrapperClass,
        );

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        self::assertInstanceOf($wrapperClass, $conn);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testInvalidWrapperClass()
    {
        $this->expectException(DBALException::class);

        $options = array(
            'pdo' => new \PDO('sqlite::memory:'),
            'wrapperClass' => 'stdClass',
        );

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
    }

    public function testInvalidDriverClass()
    {
        $this->expectException(DBALException::class);

        $options = array(
            'driverClass' => 'stdClass'
        );

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
    }

    public function testValidDriverClass()
    {
        $options = array(
            'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver',
        );

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        self::assertInstanceOf('Doctrine\DBAL\Driver\PDOMySql\Driver', $conn->getDriver());
    }

    /**
     * @dataProvider databaseUrls
     */
    public function testDatabaseUrl($url, $expected)
    {
        $options = is_array($url) ? $url : array(
            'url' => $url,
        );

        if (isset($options['pdo'])) {
            if ( ! extension_loaded('pdo')) {
                $this->markTestSkipped('PDO is not installed');
            }

            $options['pdo'] = $this->createMock(\PDO::class);
        }

        $options = is_array($url) ? $url : array(
            'url' => $url,
        );

        if ($expected === false) {
            $this->expectException(DBALException::class);
        }

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);

        $params = $conn->getParams();
        foreach ($expected as $key => $value) {
            if (in_array($key, array('pdo', 'driver', 'driverClass'), true)) {
                self::assertInstanceOf($value, $conn->getDriver());
            } else {
                self::assertEquals($value, $params[$key]);
            }
        }
    }

    public function databaseUrls()
    {
        return array(
            'simple URL' => array(
                'mysql://foo:bar@localhost/baz',
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
            'simple URL with port' => array(
                'mysql://foo:bar@localhost:11211/baz',
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'port' => 11211, 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
            'sqlite relative URL with host' => array(
                'sqlite://localhost/foo/dbname.sqlite',
                array('path' => 'foo/dbname.sqlite', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'),
            ),
            'sqlite absolute URL with host' => array(
                'sqlite://localhost//tmp/dbname.sqlite',
                array('path' => '/tmp/dbname.sqlite', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'),
            ),
            'sqlite relative URL without host' => array(
                'sqlite:///foo/dbname.sqlite',
                array('path' => 'foo/dbname.sqlite', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'),
            ),
            'sqlite absolute URL without host' => array(
                'sqlite:////tmp/dbname.sqlite',
                array('path' => '/tmp/dbname.sqlite', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'),
            ),
            'sqlite memory' => array(
                'sqlite:///:memory:',
                array('memory' => true, 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'),
            ),
            'sqlite memory with host' => array(
                'sqlite://localhost/:memory:',
                array('memory' => true, 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'),
            ),
            'params parsed from URL override individual params' => array(
                array('url' => 'mysql://foo:bar@localhost/baz', 'password' => 'lulz'),
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
            'params not parsed from URL but individual params are preserved' => array(
                array('url' => 'mysql://foo:bar@localhost/baz', 'port' => 1234),
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'port' => 1234, 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
            'query params from URL are used as extra params' => array(
                'url' => 'mysql://foo:bar@localhost/dbname?charset=UTF-8',
                array('charset' => 'UTF-8'),
            ),
            'simple URL with fallthrough scheme not defined in map' => array(
                'sqlsrv://foo:bar@localhost/baz',
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\SQLSrv\Driver'),
            ),
            'simple URL with fallthrough scheme containing underscores fails' => array(
                'drizzle_pdo_mysql://foo:bar@localhost/baz',
                false,
            ),
            'simple URL with fallthrough scheme containing dashes works' => array(
                'drizzle-pdo-mysql://foo:bar@localhost/baz',
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\DrizzlePDOMySql\Driver'),
            ),
            'simple URL with percent encoding' => array(
                'mysql://foo%3A:bar%2F@localhost/baz+baz%40',
                array('user' => 'foo:', 'password' => 'bar/', 'host' => 'localhost', 'dbname' => 'baz+baz@', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
            'simple URL with percent sign in password' => array(
                'mysql://foo:bar%25bar@localhost/baz',
                array('user' => 'foo', 'password' => 'bar%bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),

            // DBAL-1234
            'URL without scheme and without any driver information' => array(
                array('url' => '//foo:bar@localhost/baz'),
                false,
            ),
            'URL without scheme but default PDO driver' => array(
                array('url' => '//foo:bar@localhost/baz', 'pdo' => true),
                false,
            ),
            'URL without scheme but default driver' => array(
                array('url' => '//foo:bar@localhost/baz', 'driver' => 'pdo_mysql'),
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
            'URL without scheme but custom driver' => array(
                array('url' => '//foo:bar@localhost/baz', 'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'),
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'),
            ),
            'URL without scheme but default PDO driver and default driver' => array(
                array('url' => '//foo:bar@localhost/baz', 'pdo' => true, 'driver' => 'pdo_mysql'),
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
            'URL without scheme but driver and custom driver' => array(
                array('url' => '//foo:bar@localhost/baz', 'driver' => 'pdo_mysql', 'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'),
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'),
            ),
            'URL with default PDO driver' => array(
                array('url' => 'mysql://foo:bar@localhost/baz', 'pdo' => true),
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
            'URL with default driver' => array(
                array('url' => 'mysql://foo:bar@localhost/baz', 'driver' => 'sqlite'),
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
            'URL with default custom driver' => array(
                array('url' => 'mysql://foo:bar@localhost/baz', 'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'),
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
            'URL with default PDO driver and default driver' => array(
                array('url' => 'mysql://foo:bar@localhost/baz', 'pdo' => true, 'driver' => 'sqlite'),
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
            'URL with default driver and default custom driver' => array(
                array('url' => 'mysql://foo:bar@localhost/baz', 'driver' => 'sqlite',  'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'),
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
            'URL with default PDO driver and default driver and default custom driver' => array(
                array('url' => 'mysql://foo:bar@localhost/baz', 'pdo' => true, 'driver' => 'sqlite', 'driverClass' => 'Doctrine\Tests\Mocks\DriverMock'),
                array('user' => 'foo', 'password' => 'bar', 'host' => 'localhost', 'dbname' => 'baz', 'driver' => 'Doctrine\DBAL\Driver\PDOMySQL\Driver'),
            ),
        );
    }
}
