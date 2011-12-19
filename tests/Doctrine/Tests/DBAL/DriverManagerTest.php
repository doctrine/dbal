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
}