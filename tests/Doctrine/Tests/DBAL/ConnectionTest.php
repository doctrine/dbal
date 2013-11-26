<?php

namespace Doctrine\Tests\DBAL;

require_once __DIR__ . '/../TestInit.php';

use Doctrine\DBAL\Connection;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Events;
use Doctrine\Tests\Mocks\DriverConnectionMock;

class ConnectionTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var Doctrine\DBAL\Connection
     */
    protected $_conn = null;

    public function setUp()
    {
        $params = array(
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'user' => 'root',
            'password' => 'password',
            'port' => '1234'
        );
        $this->_conn = \Doctrine\DBAL\DriverManager::getConnection($params);
    }

    public function testIsConnected()
    {
        $this->assertFalse($this->_conn->isConnected());
    }

    public function testNoTransactionActiveByDefault()
    {
        $this->assertFalse($this->_conn->isTransactionActive());
    }

    public function testCommitWithNoActiveTransaction_ThrowsException()
    {
        $this->setExpectedException('Doctrine\DBAL\ConnectionException');
        $this->_conn->commit();
    }

    public function testRollbackWithNoActiveTransaction_ThrowsException()
    {
        $this->setExpectedException('Doctrine\DBAL\ConnectionException');
        $this->_conn->rollback();
    }

    public function testSetRollbackOnlyNoActiveTransaction_ThrowsException()
    {
        $this->setExpectedException('Doctrine\DBAL\ConnectionException');
        $this->_conn->setRollbackOnly();
    }

    public function testIsRollbackOnlyNoActiveTransaction_ThrowsException()
    {
        $this->setExpectedException('Doctrine\DBAL\ConnectionException');
        $this->_conn->isRollbackOnly();
    }

    public function testGetConfiguration()
    {
        $config = $this->_conn->getConfiguration();

        $this->assertInstanceOf('Doctrine\DBAL\Configuration', $config);
    }

    public function testGetHost()
    {
        $this->assertEquals('localhost', $this->_conn->getHost());
    }

    public function testGetPort()
    {
        $this->assertEquals('1234', $this->_conn->getPort());
    }

    public function testGetUsername()
    {
        $this->assertEquals('root', $this->_conn->getUsername());
    }

    public function testGetPassword()
    {
        $this->assertEquals('password', $this->_conn->getPassword());
    }

    public function testGetDriver()
    {
        $this->assertInstanceOf('Doctrine\DBAL\Driver\PDOMySql\Driver', $this->_conn->getDriver());
    }

    public function testGetEventManager()
    {
        $this->assertInstanceOf('Doctrine\Common\EventManager', $this->_conn->getEventManager());
    }

    public function testConnectDispatchEvent()
    {
        $listenerMock = $this->getMock('ConnectDispatchEventListener', array('postConnect'));
        $listenerMock->expects($this->once())->method('postConnect');

        $eventManager = new EventManager();
        $eventManager->addEventListener(array(Events::postConnect), $listenerMock);

        $driverMock = $this->getMock('Doctrine\DBAL\Driver');
        $driverMock->expects(($this->at(0)))
                   ->method('connect');
        $platform = new Mocks\MockPlatform();

        $conn = new Connection(array('platform' => $platform), $driverMock, new Configuration(), $eventManager);
        $conn->connect();
    }

    public function testEventManagerPassedToPlatform()
    {
        $this->assertInstanceOf('Doctrine\Common\EventManager', $this->_conn->getDatabasePlatform()->getEventManager());
        $this->assertSame($this->_conn->getEventManager(), $this->_conn->getDatabasePlatform()->getEventManager());
    }

    /**
     * @expectedException Doctrine\DBAL\DBALException
     * @dataProvider getQueryMethods
     */
    public function testDriverExceptionIsWrapped($method)
    {
        $this->setExpectedException('Doctrine\DBAL\DBALException', "An exception occurred while executing 'MUUHAAAAHAAAA':

SQLSTATE[HY000]: General error: 1 near \"MUUHAAAAHAAAA\"");

        $con = \Doctrine\DBAL\DriverManager::getConnection(array(
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ));

        $con->$method('MUUHAAAAHAAAA');
    }

    public function getQueryMethods()
    {
        return array(
            array('exec'),
            array('query'),
            array('executeQuery'),
            array('executeUpdate'),
            array('prepare'),
        );
    }

    /**
     * Pretty dumb test, however we want to check that the EchoSQLLogger correctly implements the interface.
     *
     * @group DBAL-11
     */
    public function testEchoSQLLogger()
    {
        $logger = new \Doctrine\DBAL\Logging\EchoSQLLogger();
        $this->_conn->getConfiguration()->setSQLLogger($logger);
        $this->assertSame($logger, $this->_conn->getConfiguration()->getSQLLogger());
    }

    /**
     * Pretty dumb test, however we want to check that the DebugStack correctly implements the interface.
     *
     * @group DBAL-11
     */
    public function testDebugSQLStack()
    {
        $logger = new \Doctrine\DBAL\Logging\DebugStack();
        $this->_conn->getConfiguration()->setSQLLogger($logger);
        $this->assertSame($logger, $this->_conn->getConfiguration()->getSQLLogger());
    }

    public function testEmptyInsert()
    {
        $driverMock = $this->getMock('Doctrine\DBAL\Driver');

        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(new DriverConnectionMock()));

        $conn = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->setMethods(array('executeUpdate'))
            ->setConstructorArgs(array(array('platform' => new Mocks\MockPlatform()), $driverMock))
            ->getMock();

        $conn->expects($this->once())
            ->method('executeUpdate')
            ->with('INSERT INTO footable () VALUES ()');

        $conn->insert('footable', array());
    }
}
