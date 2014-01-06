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

    /**
     * @group DBAL-81
     */
    public function testIsAutoCommit()
    {
        $this->assertTrue($this->_conn->isAutoCommit());
    }

    /**
     * @group DBAL-81
     */
    public function testSetAutoCommit()
    {
        $this->_conn->setAutoCommit(false);
        $this->assertFalse($this->_conn->isAutoCommit());
        $this->_conn->setAutoCommit(0);
        $this->assertFalse($this->_conn->isAutoCommit());
    }

    /**
     * @group DBAL-81
     */
    public function testConnectStartsTransactionInNoAutoCommitMode()
    {
        $driverMock = $this->getMock('Doctrine\DBAL\Driver');
        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(new DriverConnectionMock()));
        $conn = new Connection(array('platform' => new Mocks\MockPlatform()), $driverMock);

        $conn->setAutoCommit(false);

        $this->assertFalse($conn->isTransactionActive());

        $conn->connect();

        $this->assertTrue($conn->isTransactionActive());
    }

    /**
     * @group DBAL-81
     */
    public function testCommitStartsTransactionInNoAutoCommitMode()
    {
        $driverMock = $this->getMock('Doctrine\DBAL\Driver');
        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(new DriverConnectionMock()));
        $conn = new Connection(array('platform' => new Mocks\MockPlatform()), $driverMock);

        $conn->setAutoCommit(false);
        $conn->connect();
        $conn->commit();

        $this->assertTrue($conn->isTransactionActive());
    }

    /**
     * @group DBAL-81
     */
    public function testRollBackStartsTransactionInNoAutoCommitMode()
    {
        $driverMock = $this->getMock('Doctrine\DBAL\Driver');
        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(new DriverConnectionMock()));
        $conn = new Connection(array('platform' => new Mocks\MockPlatform()), $driverMock);

        $conn->setAutoCommit(false);
        $conn->connect();
        $conn->rollBack();

        $this->assertTrue($conn->isTransactionActive());
    }

    /**
     * @group DBAL-81
     */
    public function testSwitchingAutoCommitModeCommitsAllCurrentTransactions()
    {
        $driverMock = $this->getMock('Doctrine\DBAL\Driver');
        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(new DriverConnectionMock()));
        $conn = new Connection(array('platform' => new Mocks\MockPlatform()), $driverMock);

        $conn->connect();
        $conn->beginTransaction();
        $conn->beginTransaction();
        $conn->setAutoCommit(false);

        $this->assertSame(1, $conn->getTransactionNestingLevel());

        $conn->beginTransaction();
        $conn->beginTransaction();
        $conn->setAutoCommit(true);

        $this->assertFalse($conn->isTransactionActive());
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

    public function testQuotesInsert()
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
            ->with('INSERT INTO "insert" (foo, "bar", "create") VALUES (?, ?, ?)');

        $conn->insert('insert', array('foo' => 1, '`bar`' => 2, 'create' => 3));
    }

    public function testQuotesDelete()
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
            ->with('DELETE FROM "delete" WHERE foo = ? AND "bar" = ? AND "select" = ?');

        $conn->delete('delete', array('foo' => 1, '`bar`' => 2, 'select' => 3));
    }

    public function testQuotesUpdate()
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
            ->with('UPDATE "update" SET foo = ?, "bar" = ?, "alter" = ? WHERE foo = ? AND "bar" = ? AND "drop" = ?');

        $conn->update(
            'update',
            array('foo' => 1, '`bar`' => 2, 'alter' => 3),
            array('foo' => 4, '`bar`' => 5, 'drop' => 6)
        );
    }

    public function testFetchAssoc()
    {
        $statement = 'SELECT * FROM foo WHERE bar = ?';
        $params    = array(666);
        $types     = array(\PDO::PARAM_INT);
        $result    = array();

        $driverMock = $this->getMock('Doctrine\DBAL\Driver');

        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(new DriverConnectionMock()));

        $driverStatementMock = $this->getMock('Doctrine\Tests\Mocks\DriverStatementMock');

        $driverStatementMock->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->will($this->returnValue($result));

        /** @var \PHPUnit_Framework_MockObject_MockObject|\Doctrine\DBAL\Connection $conn */
        $conn = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->setMethods(array('executeQuery'))
            ->setConstructorArgs(array(array('platform' => new Mocks\MockPlatform()), $driverMock))
            ->getMock();

        $conn->expects($this->once())
            ->method('executeQuery')
            ->with($statement, $params, $types)
            ->will($this->returnValue($driverStatementMock));

        $this->assertSame($result, $conn->fetchAssoc($statement, $params, $types));
    }

    public function testFetchArray()
    {
        $statement = 'SELECT * FROM foo WHERE bar = ?';
        $params    = array(666);
        $types     = array(\PDO::PARAM_INT);
        $result    = array();

        $driverMock = $this->getMock('Doctrine\DBAL\Driver');

        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(new DriverConnectionMock()));

        $driverStatementMock = $this->getMock('Doctrine\Tests\Mocks\DriverStatementMock');

        $driverStatementMock->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_NUM)
            ->will($this->returnValue($result));

        /** @var \PHPUnit_Framework_MockObject_MockObject|\Doctrine\DBAL\Connection $conn */
        $conn = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->setMethods(array('executeQuery'))
            ->setConstructorArgs(array(array('platform' => new Mocks\MockPlatform()), $driverMock))
            ->getMock();

        $conn->expects($this->once())
            ->method('executeQuery')
            ->with($statement, $params, $types)
            ->will($this->returnValue($driverStatementMock));

        $this->assertSame($result, $conn->fetchArray($statement, $params, $types));
    }

    public function testFetchColumn()
    {
        $statement = 'SELECT * FROM foo WHERE bar = ?';
        $params    = array(666);
        $types     = array(\PDO::PARAM_INT);
        $column    = 0;
        $result    = array();

        $driverMock = $this->getMock('Doctrine\DBAL\Driver');

        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(new DriverConnectionMock()));

        $driverStatementMock = $this->getMock('Doctrine\Tests\Mocks\DriverStatementMock');

        $driverStatementMock->expects($this->once())
            ->method('fetchColumn')
            ->with($column)
            ->will($this->returnValue($result));

        /** @var \PHPUnit_Framework_MockObject_MockObject|\Doctrine\DBAL\Connection $conn */
        $conn = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->setMethods(array('executeQuery'))
            ->setConstructorArgs(array(array('platform' => new Mocks\MockPlatform()), $driverMock))
            ->getMock();

        $conn->expects($this->once())
            ->method('executeQuery')
            ->with($statement, $params, $types)
            ->will($this->returnValue($driverStatementMock));

        $this->assertSame($result, $conn->fetchColumn($statement, $params, $column, $types));
    }

    public function testFetchAll()
    {
        $statement = 'SELECT * FROM foo WHERE bar = ?';
        $params    = array(666);
        $types     = array(\PDO::PARAM_INT);
        $result    = array();

        $driverMock = $this->getMock('Doctrine\DBAL\Driver');

        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(new DriverConnectionMock()));

        $driverStatementMock = $this->getMock('Doctrine\Tests\Mocks\DriverStatementMock');

        $driverStatementMock->expects($this->once())
            ->method('fetchAll')
            ->will($this->returnValue($result));

        /** @var \PHPUnit_Framework_MockObject_MockObject|\Doctrine\DBAL\Connection $conn */
        $conn = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->setMethods(array('executeQuery'))
            ->setConstructorArgs(array(array('platform' => new Mocks\MockPlatform()), $driverMock))
            ->getMock();

        $conn->expects($this->once())
            ->method('executeQuery')
            ->with($statement, $params, $types)
            ->will($this->returnValue($driverStatementMock));

        $this->assertSame($result, $conn->fetchAll($statement, $params, $types));
    }
}
