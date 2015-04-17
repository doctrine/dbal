<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Events;
use Doctrine\Tests\Mocks\DriverConnectionMock;
use Doctrine\Tests\Mocks\DriverMock;

class ConnectionTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $_conn = null;

    protected $params = array(
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'user' => 'root',
        'password' => 'password',
        'port' => '1234'
    );

    public function setUp()
    {
        $this->_conn = \Doctrine\DBAL\DriverManager::getConnection($this->params);
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
        $driverMock = new DriverMock();
        $connection = new Connection($this->params, $driverMock);
        $this->assertInstanceOf('Doctrine\Common\EventManager', $connection->getDatabasePlatform()->getEventManager());
        $this->assertSame($connection->getEventManager(), $connection->getDatabasePlatform()->getEventManager());
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     * @dataProvider getQueryMethods
     */
    public function testDriverExceptionIsWrapped($method)
    {
        $this->setExpectedException('Doctrine\DBAL\DBALException', "An exception occurred while executing 'MUUHAAAAHAAAA':\n\nSQLSTATE[HY000]: General error: 1 near \"MUUHAAAAHAAAA\"");

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

    public function testConnectionIsClosed()
    {
        $this->_conn->close();

        $this->setExpectedException('Doctrine\\DBAL\\Exception\\DriverException');

        $this->_conn->quoteIdentifier('Bug');
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

    public function testConnectionDoesNotMaintainTwoReferencesToExternalPDO()
    {
        $params['pdo'] = new \stdClass();

        $driverMock = $this->getMock('Doctrine\DBAL\Driver');

        $conn = new Connection($params, $driverMock);

        $this->assertArrayNotHasKey('pdo', $conn->getParams(), "Connection is maintaining additional reference to the PDO connection");
    }

    public function testPassingExternalPDOMeansConnectionIsConnected()
    {
        $params['pdo'] = new \stdClass();

        $driverMock = $this->getMock('Doctrine\DBAL\Driver');

        $conn = new Connection($params, $driverMock);

        $this->assertTrue($conn->isConnected(), "Connection is not connected after passing external PDO");
    }

    public function testCallingDeleteWithNoDeletionCriteriaResultsInInvalidArgumentException()
    {
        /* @var $driver \Doctrine\DBAL\Driver */
        $driver  = $this->getMock('Doctrine\DBAL\Driver');
        $pdoMock = $this->getMock('Doctrine\DBAL\Driver\Connection');

        // should never execute queries with invalid arguments
        $pdoMock->expects($this->never())->method('exec');
        $pdoMock->expects($this->never())->method('prepare');

        $conn = new Connection(array('pdo' => $pdoMock), $driver);

        $this->setExpectedException('Doctrine\DBAL\Exception\InvalidArgumentException');
        $conn->delete('kittens', array());
    }

    public function dataCallConnectOnce()
    {
        return array(
            array('delete', array('tbl', array('id' => 12345))),
            array('insert', array('tbl', array('data' => 'foo'))),
            array('update', array('tbl', array('data' => 'bar'), array('id' => 12345))),
            array('prepare', array('select * from dual')),
            array('executeUpdate', array('insert into tbl (id) values (?)'), array(123)),
        );
    }

    /**
     * @dataProvider dataCallConnectOnce
     */
    public function testCallConnectOnce($method, $params)
    {
        $driverMock   = $this->getMock('Doctrine\DBAL\Driver');
        $pdoMock      = $this->getMock('Doctrine\DBAL\Driver\Connection');
        $platformMock = new Mocks\MockPlatform();
        $stmtMock     = $this->getMock('Doctrine\DBAL\Driver\Statement');

        $pdoMock->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($stmtMock));

        $conn = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->setConstructorArgs(array(array('pdo' => $pdoMock, 'platform' => $platformMock), $driverMock))
            ->setMethods(array('connect'))
            ->getMock();

        $conn->expects($this->once())->method('connect');

        call_user_func_array(array($conn, $method), $params);
    }
}
