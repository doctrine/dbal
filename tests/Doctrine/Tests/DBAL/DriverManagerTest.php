<?php

namespace Doctrine\Tests\DBAL;

require_once __DIR__ . '/../TestInit.php';

class DriverManagerTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testInvalidPdoInstance()
    {
        $options = array(
            'pdo' => 'test'
        );
        $test = \Doctrine\DBAL\DriverManager::getConnection($options);
    }

    public function testValidPdoInstance()
    {
        $options = array(
            'pdo' => new \PDO('sqlite::memory:')
        );
        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        $this->assertEquals('sqlite', $conn->getDatabasePlatform()->getName());
    }

    /**
     * @group DBAL-32
     */
    public function testPdoInstanceSetErrorMode()
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
        $options = array(
            'pdo' => $pdo
        );

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        $this->assertEquals(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
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

    public function testCustomPlatform()
    {
        $mockPlatform = new \Doctrine\Tests\DBAL\Mocks\MockPlatform();
        $options = array(
            'pdo' => new \PDO('sqlite::memory:'),
            'platform' => $mockPlatform
        );

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        $this->assertSame($mockPlatform, $conn->getDatabasePlatform());
    }

    public function testCustomWrapper()
    {
        $wrapperClass = 'Doctrine\Tests\Mocks\ConnectionMock';

        $options = array(
            'pdo' => new \PDO('sqlite::memory:'),
            'wrapperClass' => $wrapperClass,
        );

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        $this->assertInstanceOf($wrapperClass, $conn);
    }

    public function testInvalidWrapperClass()
    {
        $this->setExpectedException('\Doctrine\DBAL\DBALException');

        $options = array(
            'pdo' => new \PDO('sqlite::memory:'),
            'wrapperClass' => 'stdClass',
        );

        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
    }

    public function testInvalidDriverClass()
    {
        $this->setExpectedException('\Doctrine\DBAL\DBALException');

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
        $this->assertInstanceOf('Doctrine\DBAL\Driver\PDOMySql\Driver', $conn->getDriver());
    }
    
    /**
     * @dataProvider databaseUrls
     */
    public function testDatabaseUrl($url, $expected)
    {
        $options = is_array($url) ? $url : array(
            'url' => $url,
        );
        
        if ($expected === false) {
            $this->setExpectedException('Doctrine\DBAL\DBALException');
        }
        
        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        
        $params = $conn->getParams();
        foreach ($expected as $key => $value) {
            if ($key == 'driver') {
                $this->assertInstanceOf($value, $conn->getDriver());
            } else {
                $this->assertEquals($value, $params[$key]);
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
                array('dbname' => 'foo/dbname.sqlite', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'),
            ),
            'sqlite absolute URL with host' => array(
                'sqlite://localhost//tmp/dbname.sqlite',
                array('dbname' => '/tmp/dbname.sqlite', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'),
            ),
            'sqlite relative URL without host' => array(
                'sqlite:///foo/dbname.sqlite',
                array('dbname' => 'foo/dbname.sqlite', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'),
            ),
            'sqlite absolute URL without host' => array(
                'sqlite:////tmp/dbname.sqlite',
                array('dbname' => '/tmp/dbname.sqlite', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'),
            ),
            'sqlite memory' => array(
                'sqlite:///:memory:',
                array('dbname' => ':memory:', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'),
            ),
            'sqlite memory with host' => array(
                'sqlite://localhost/:memory:',
                array('dbname' => ':memory:', 'driver' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver'),
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
        );
    }
}