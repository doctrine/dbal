<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\ArrayStatement;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Logging\EchoSQLLogger;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Tests\DbalTestCase;
use Doctrine\Tests\Mocks\DriverMock;
use Doctrine\Tests\Mocks\DriverStatementMock;
use Doctrine\Tests\Mocks\ServerInfoAwareConnectionMock;
use Doctrine\Tests\Mocks\VersionAwarePlatformDriverMock;
use Exception;
use PHPUnit_Framework_MockObject_MockObject;
use ReflectionObject;
use stdClass;
use function call_user_func_array;

/**
 * @requires extension pdo_mysql
 */
class ConnectionTest extends DbalTestCase
{
    /** @var Connection */
    private $connection;

    /** @var string[] */
    protected $params = [
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'user' => 'root',
        'password' => 'password',
        'port' => '1234',
    ];

    protected function setUp()
    {
        $this->connection = DriverManager::getConnection($this->params);
    }

    public function getExecuteUpdateMockConnection()
    {
        $driverMock = $this->createMock(Driver::class);

        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(
                $this->createMock(DriverConnection::class)
            ));

        return $this->getMockBuilder(Connection::class)
            ->setMethods(['executeUpdate'])
            ->setConstructorArgs([['platform' => new Mocks\MockPlatform()], $driverMock])
            ->getMock();
    }

    public function testIsConnected()
    {
        self::assertFalse($this->connection->isConnected());
    }

    public function testNoTransactionActiveByDefault()
    {
        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testCommitWithNoActiveTransactionThrowsException()
    {
        $this->expectException(ConnectionException::class);
        $this->connection->commit();
    }

    public function testRollbackWithNoActiveTransactionThrowsException()
    {
        $this->expectException(ConnectionException::class);
        $this->connection->rollBack();
    }

    public function testSetRollbackOnlyNoActiveTransactionThrowsException()
    {
        $this->expectException(ConnectionException::class);
        $this->connection->setRollbackOnly();
    }

    public function testIsRollbackOnlyNoActiveTransactionThrowsException()
    {
        $this->expectException(ConnectionException::class);
        $this->connection->isRollbackOnly();
    }

    public function testGetConfiguration()
    {
        $config = $this->connection->getConfiguration();

        self::assertInstanceOf(Configuration::class, $config);
    }

    public function testGetHost()
    {
        self::assertEquals('localhost', $this->connection->getHost());
    }

    public function testGetPort()
    {
        self::assertEquals('1234', $this->connection->getPort());
    }

    public function testGetUsername()
    {
        self::assertEquals('root', $this->connection->getUsername());
    }

    public function testGetPassword()
    {
        self::assertEquals('password', $this->connection->getPassword());
    }

    public function testGetDriver()
    {
        self::assertInstanceOf(\Doctrine\DBAL\Driver\PDOMySql\Driver::class, $this->connection->getDriver());
    }

    public function testGetEventManager()
    {
        self::assertInstanceOf(EventManager::class, $this->connection->getEventManager());
    }

    public function testConnectDispatchEvent()
    {
        $listenerMock = $this->getMockBuilder('ConnectDispatchEventListener')
            ->setMethods(['postConnect'])
            ->getMock();
        $listenerMock->expects($this->once())->method('postConnect');

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::postConnect], $listenerMock);

        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects($this->at(0))
                   ->method('connect');
        $platform = new Mocks\MockPlatform();

        $conn = new Connection(['platform' => $platform], $driverMock, new Configuration(), $eventManager);
        $conn->connect();
    }

    public function testEventManagerPassedToPlatform()
    {
        $driverMock = new DriverMock();
        $connection = new Connection($this->params, $driverMock);
        self::assertInstanceOf(EventManager::class, $connection->getDatabasePlatform()->getEventManager());
        self::assertSame($connection->getEventManager(), $connection->getDatabasePlatform()->getEventManager());
    }

    /**
     * @requires extension pdo_sqlite
     * @expectedException \Doctrine\DBAL\DBALException
     * @dataProvider getQueryMethods
     */
    public function testDriverExceptionIsWrapped($method)
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage("An exception occurred while executing 'MUUHAAAAHAAAA':\n\nSQLSTATE[HY000]: General error: 1 near \"MUUHAAAAHAAAA\"");

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $connection->$method('MUUHAAAAHAAAA');
    }

    public function getQueryMethods()
    {
        return [
            ['exec'],
            ['query'],
            ['executeQuery'],
            ['executeUpdate'],
            ['prepare'],
        ];
    }

    /**
     * Pretty dumb test, however we want to check that the EchoSQLLogger correctly implements the interface.
     *
     * @group DBAL-11
     */
    public function testEchoSQLLogger()
    {
        $logger = new EchoSQLLogger();
        $this->connection->getConfiguration()->setSQLLogger($logger);
        self::assertSame($logger, $this->connection->getConfiguration()->getSQLLogger());
    }

    /**
     * Pretty dumb test, however we want to check that the DebugStack correctly implements the interface.
     *
     * @group DBAL-11
     */
    public function testDebugSQLStack()
    {
        $logger = new DebugStack();
        $this->connection->getConfiguration()->setSQLLogger($logger);
        self::assertSame($logger, $this->connection->getConfiguration()->getSQLLogger());
    }

    /**
     * @group DBAL-81
     */
    public function testIsAutoCommit()
    {
        self::assertTrue($this->connection->isAutoCommit());
    }

    /**
     * @group DBAL-81
     */
    public function testSetAutoCommit()
    {
        $this->connection->setAutoCommit(false);
        self::assertFalse($this->connection->isAutoCommit());
        $this->connection->setAutoCommit(0);
        self::assertFalse($this->connection->isAutoCommit());
    }

    /**
     * @group DBAL-81
     */
    public function testConnectStartsTransactionInNoAutoCommitMode()
    {
        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(
                $this->createMock(DriverConnection::class)
            ));
        $conn = new Connection(['platform' => new Mocks\MockPlatform()], $driverMock);

        $conn->setAutoCommit(false);

        self::assertFalse($conn->isTransactionActive());

        $conn->connect();

        self::assertTrue($conn->isTransactionActive());
    }

    /**
     * @group DBAL-81
     */
    public function testCommitStartsTransactionInNoAutoCommitMode()
    {
        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(
                $this->createMock(DriverConnection::class)
            ));
        $conn = new Connection(['platform' => new Mocks\MockPlatform()], $driverMock);

        $conn->setAutoCommit(false);
        $conn->connect();
        $conn->commit();

        self::assertTrue($conn->isTransactionActive());
    }

    /**
     * @group DBAL-81
     */
    public function testRollBackStartsTransactionInNoAutoCommitMode()
    {
        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(
                $this->createMock(DriverConnection::class)
            ));
        $conn = new Connection(['platform' => new Mocks\MockPlatform()], $driverMock);

        $conn->setAutoCommit(false);
        $conn->connect();
        $conn->rollBack();

        self::assertTrue($conn->isTransactionActive());
    }

    /**
     * @group DBAL-81
     */
    public function testSwitchingAutoCommitModeCommitsAllCurrentTransactions()
    {
        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(
                $this->createMock(DriverConnection::class)
            ));
        $conn = new Connection(['platform' => new Mocks\MockPlatform()], $driverMock);

        $conn->connect();
        $conn->beginTransaction();
        $conn->beginTransaction();
        $conn->setAutoCommit(false);

        self::assertSame(1, $conn->getTransactionNestingLevel());

        $conn->beginTransaction();
        $conn->beginTransaction();
        $conn->setAutoCommit(true);

        self::assertFalse($conn->isTransactionActive());
    }

    public function testEmptyInsert()
    {
        $conn = $this->getExecuteUpdateMockConnection();

        $conn->expects($this->once())
            ->method('executeUpdate')
            ->with('INSERT INTO footable () VALUES ()');

        $conn->insert('footable', []);
    }

    /**
     * @group DBAL-2511
     */
    public function testUpdateWithDifferentColumnsInDataAndIdentifiers()
    {
        $conn = $this->getExecuteUpdateMockConnection();

        $conn->expects($this->once())
            ->method('executeUpdate')
            ->with(
                'UPDATE TestTable SET text = ?, is_edited = ? WHERE id = ? AND name = ?',
                [
                    'some text',
                    true,
                    1,
                    'foo',
                ],
                [
                    'string',
                    'boolean',
                    'integer',
                    'string',
                ]
            );

        $conn->update(
            'TestTable',
            [
                'text' => 'some text',
                'is_edited' => true,
            ],
            [
                'id' => 1,
                'name' => 'foo',
            ],
            [
                'text' => 'string',
                'is_edited' => 'boolean',
                'id' => 'integer',
                'name' => 'string',
            ]
        );
    }

    /**
     * @group DBAL-2511
     */
    public function testUpdateWithSameColumnInDataAndIdentifiers()
    {
        $conn = $this->getExecuteUpdateMockConnection();

        $conn->expects($this->once())
            ->method('executeUpdate')
            ->with(
                'UPDATE TestTable SET text = ?, is_edited = ? WHERE id = ? AND is_edited = ?',
                [
                    'some text',
                    true,
                    1,
                    false,
                ],
                [
                    'string',
                    'boolean',
                    'integer',
                    'boolean',
                ]
            );

        $conn->update(
            'TestTable',
            [
                'text' => 'some text',
                'is_edited' => true,
            ],
            [
                'id' => 1,
                'is_edited' => false,
            ],
            [
                'text' => 'string',
                'is_edited' => 'boolean',
                'id' => 'integer',
            ]
        );
    }

    /**
     * @group DBAL-2688
     */
    public function testUpdateWithIsNull()
    {
        $conn = $this->getExecuteUpdateMockConnection();

        $conn->expects($this->once())
            ->method('executeUpdate')
            ->with(
                'UPDATE TestTable SET text = ?, is_edited = ? WHERE id IS NULL AND name = ?',
                [
                    'some text',
                    null,
                    'foo',
                ],
                [
                    'string',
                    'boolean',
                    'string',
                ]
            );

        $conn->update(
            'TestTable',
            [
                'text' => 'some text',
                'is_edited' => null,
            ],
            [
                'id' => null,
                'name' => 'foo',
            ],
            [
                'text' => 'string',
                'is_edited' => 'boolean',
                'id' => 'integer',
                'name' => 'string',
            ]
        );
    }

    /**
     * @group DBAL-2688
     */
    public function testDeleteWithIsNull()
    {
        $conn = $this->getExecuteUpdateMockConnection();

        $conn->expects($this->once())
            ->method('executeUpdate')
            ->with(
                'DELETE FROM TestTable WHERE id IS NULL AND name = ?',
                ['foo'],
                ['string']
            );

        $conn->delete(
            'TestTable',
            [
                'id' => null,
                'name' => 'foo',
            ],
            [
                'id' => 'integer',
                'name' => 'string',
            ]
        );
    }

    public function testFetchAssoc()
    {
        $statement = 'SELECT * FROM foo WHERE bar = ?';
        $params    = [666];
        $types     = [ParameterType::INTEGER];
        $result    = [];

        $driverMock = $this->createMock(Driver::class);

        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(
                $this->createMock(DriverConnection::class)
            ));

        $driverStatementMock = $this->createMock(DriverStatementMock::class);

        $driverStatementMock->expects($this->once())
            ->method('fetch')
            ->with(FetchMode::ASSOCIATIVE)
            ->will($this->returnValue($result));

        /** @var PHPUnit_Framework_MockObject_MockObject|Connection $conn */
        $conn = $this->getMockBuilder(Connection::class)
            ->setMethods(['executeQuery'])
            ->setConstructorArgs([['platform' => new Mocks\MockPlatform()], $driverMock])
            ->getMock();

        $conn->expects($this->once())
            ->method('executeQuery')
            ->with($statement, $params, $types)
            ->will($this->returnValue($driverStatementMock));

        self::assertSame($result, $conn->fetchAssoc($statement, $params, $types));
    }

    public function testFetchArray()
    {
        $statement = 'SELECT * FROM foo WHERE bar = ?';
        $params    = [666];
        $types     = [ParameterType::INTEGER];
        $result    = [];

        $driverMock = $this->createMock(Driver::class);

        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(
                $this->createMock(DriverConnection::class)
            ));

        $driverStatementMock = $this->createMock(DriverStatementMock::class);

        $driverStatementMock->expects($this->once())
            ->method('fetch')
            ->with(FetchMode::NUMERIC)
            ->will($this->returnValue($result));

        /** @var PHPUnit_Framework_MockObject_MockObject|Connection $conn */
        $conn = $this->getMockBuilder(Connection::class)
            ->setMethods(['executeQuery'])
            ->setConstructorArgs([['platform' => new Mocks\MockPlatform()], $driverMock])
            ->getMock();

        $conn->expects($this->once())
            ->method('executeQuery')
            ->with($statement, $params, $types)
            ->will($this->returnValue($driverStatementMock));

        self::assertSame($result, $conn->fetchArray($statement, $params, $types));
    }

    public function testFetchColumn()
    {
        $statement = 'SELECT * FROM foo WHERE bar = ?';
        $params    = [666];
        $types     = [ParameterType::INTEGER];
        $column    = 0;
        $result    = [];

        $driverMock = $this->createMock(Driver::class);

        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(
                $this->createMock(DriverConnection::class)
            ));

        $driverStatementMock = $this->createMock(DriverStatementMock::class);

        $driverStatementMock->expects($this->once())
            ->method('fetchColumn')
            ->with($column)
            ->will($this->returnValue($result));

        /** @var PHPUnit_Framework_MockObject_MockObject|Connection $conn */
        $conn = $this->getMockBuilder(Connection::class)
            ->setMethods(['executeQuery'])
            ->setConstructorArgs([['platform' => new Mocks\MockPlatform()], $driverMock])
            ->getMock();

        $conn->expects($this->once())
            ->method('executeQuery')
            ->with($statement, $params, $types)
            ->will($this->returnValue($driverStatementMock));

        self::assertSame($result, $conn->fetchColumn($statement, $params, $column, $types));
    }

    public function testConnectionIsClosedButNotUnset()
    {
        // mock Connection, and make connect() purposefully do nothing
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->setMethods(['connect'])
            ->getMock();

        // artificially set the wrapped connection to non-null
        $reflection   = new ReflectionObject($connection);
        $connProperty = $reflection->getProperty('_conn');
        $connProperty->setAccessible(true);
        $connProperty->setValue($connection, new stdClass());

        // close the connection (should nullify the wrapped connection)
        $connection->close();

        // the wrapped connection should be null
        // (and since connect() does nothing, this will not reconnect)
        // this will also fail if this _conn property was unset instead of set to null
        self::assertNull($connection->getWrappedConnection());
    }

    public function testFetchAll()
    {
        $statement = 'SELECT * FROM foo WHERE bar = ?';
        $params    = [666];
        $types     = [ParameterType::INTEGER];
        $result    = [];

        $driverMock = $this->createMock(Driver::class);

        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(
                $this->createMock(DriverConnection::class)
            ));

        $driverStatementMock = $this->createMock(DriverStatementMock::class);

        $driverStatementMock->expects($this->once())
            ->method('fetchAll')
            ->will($this->returnValue($result));

        /** @var PHPUnit_Framework_MockObject_MockObject|Connection $conn */
        $conn = $this->getMockBuilder(Connection::class)
            ->setMethods(['executeQuery'])
            ->setConstructorArgs([['platform' => new Mocks\MockPlatform()], $driverMock])
            ->getMock();

        $conn->expects($this->once())
            ->method('executeQuery')
            ->with($statement, $params, $types)
            ->will($this->returnValue($driverStatementMock));

        self::assertSame($result, $conn->fetchAll($statement, $params, $types));
    }

    public function testConnectionDoesNotMaintainTwoReferencesToExternalPDO()
    {
        $params['pdo'] = new stdClass();

        $driverMock = $this->createMock(Driver::class);

        $conn = new Connection($params, $driverMock);

        self::assertArrayNotHasKey('pdo', $conn->getParams(), 'Connection is maintaining additional reference to the PDO connection');
    }

    public function testPassingExternalPDOMeansConnectionIsConnected()
    {
        $params['pdo'] = new stdClass();

        $driverMock = $this->createMock(Driver::class);

        $conn = new Connection($params, $driverMock);

        self::assertTrue($conn->isConnected(), 'Connection is not connected after passing external PDO');
    }

    public function testCallingDeleteWithNoDeletionCriteriaResultsInInvalidArgumentException()
    {
        /** @var Driver $driver */
        $driver  = $this->createMock(Driver::class);
        $pdoMock = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);

        // should never execute queries with invalid arguments
        $pdoMock->expects($this->never())->method('exec');
        $pdoMock->expects($this->never())->method('prepare');

        $conn = new Connection(['pdo' => $pdoMock], $driver);

        $this->expectException(InvalidArgumentException::class);
        $conn->delete('kittens', []);
    }

    public function dataCallConnectOnce()
    {
        return [
            ['delete', ['tbl', ['id' => 12345]]],
            ['insert', ['tbl', ['data' => 'foo']]],
            ['update', ['tbl', ['data' => 'bar'], ['id' => 12345]]],
            ['prepare', ['select * from dual']],
            ['executeUpdate', ['insert into tbl (id) values (?)'], [123]],
        ];
    }

    /**
     * @dataProvider dataCallConnectOnce
     */
    public function testCallConnectOnce($method, $params)
    {
        $driverMock   = $this->createMock(Driver::class);
        $pdoMock      = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $platformMock = new Mocks\MockPlatform();
        $stmtMock     = $this->createMock(Statement::class);

        $pdoMock->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($stmtMock));

        $conn = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([['pdo' => $pdoMock, 'platform' => $platformMock], $driverMock])
            ->setMethods(['connect'])
            ->getMock();

        $conn->expects($this->once())->method('connect');

        call_user_func_array([$conn, $method], $params);
    }

    /**
     * @group DBAL-1127
     */
    public function testPlatformDetectionIsTriggerOnlyOnceOnRetrievingPlatform()
    {
        /** @var VersionAwarePlatformDriverMock|PHPUnit_Framework_MockObject_MockObject $driverMock */
        $driverMock = $this->createMock(VersionAwarePlatformDriverMock::class);

        /** @var ServerInfoAwareConnectionMock|PHPUnit_Framework_MockObject_MockObject $driverConnectionMock */
        $driverConnectionMock = $this->createMock(ServerInfoAwareConnectionMock::class);

        /** @var AbstractPlatform|PHPUnit_Framework_MockObject_MockObject $platformMock */
        $platformMock = $this->getMockForAbstractClass(AbstractPlatform::class);

        $connection = new Connection([], $driverMock);

        $driverMock->expects($this->once())
            ->method('connect')
            ->will($this->returnValue($driverConnectionMock));

        $driverConnectionMock->expects($this->once())
            ->method('requiresQueryForServerVersion')
            ->will($this->returnValue(false));

        $driverConnectionMock->expects($this->once())
            ->method('getServerVersion')
            ->will($this->returnValue('6.6.6'));

        $driverMock->expects($this->once())
            ->method('createDatabasePlatformForVersion')
            ->with('6.6.6')
            ->will($this->returnValue($platformMock));

        self::assertSame($platformMock, $connection->getDatabasePlatform());
    }

    public function testConnectionParamsArePassedToTheQueryCacheProfileInExecuteCacheQuery()
    {
        $resultCacheDriverMock = $this->createMock(Cache::class);

        $resultCacheDriverMock
            ->expects($this->atLeastOnce())
            ->method('fetch')
            ->with('cacheKey')
            ->will($this->returnValue(['realKey' => []]));

        $query  = 'SELECT * FROM foo WHERE bar = ?';
        $params = [666];
        $types  = [ParameterType::INTEGER];

        /** @var QueryCacheProfile|PHPUnit_Framework_MockObject_MockObject $queryCacheProfileMock */
        $queryCacheProfileMock = $this->createMock(QueryCacheProfile::class);

        $queryCacheProfileMock
            ->expects($this->any())
            ->method('getResultCacheDriver')
            ->will($this->returnValue($resultCacheDriverMock));

        // This is our main expectation
        $queryCacheProfileMock
            ->expects($this->once())
            ->method('generateCacheKeys')
            ->with($query, $params, $types, $this->params)
            ->will($this->returnValue(['cacheKey', 'realKey']));

        /** @var Driver $driver */
        $driver = $this->createMock(Driver::class);

        self::assertInstanceOf(
            ArrayStatement::class,
            (new Connection($this->params, $driver))->executeCacheQuery($query, $params, $types, $queryCacheProfileMock)
        );
    }

    /**
     * @group #2821
     */
    public function testShouldNotPassPlatformInParamsToTheQueryCacheProfileInExecuteCacheQuery() : void
    {
        $resultCacheDriverMock = $this->createMock(Cache::class);

        $resultCacheDriverMock
            ->expects($this->atLeastOnce())
            ->method('fetch')
            ->with('cacheKey')
            ->will($this->returnValue(['realKey' => []]));

        /** @var QueryCacheProfile|PHPUnit_Framework_MockObject_MockObject $queryCacheProfileMock */
        $queryCacheProfileMock = $this->createMock(QueryCacheProfile::class);

        $queryCacheProfileMock
            ->expects($this->any())
            ->method('getResultCacheDriver')
            ->will($this->returnValue($resultCacheDriverMock));

        $query = 'SELECT 1';

        $connectionParams = $this->params;

        $queryCacheProfileMock
            ->expects($this->once())
            ->method('generateCacheKeys')
            ->with($query, [], [], $connectionParams)
            ->will($this->returnValue(['cacheKey', 'realKey']));

        $connectionParams['platform'] = $this->createMock(AbstractPlatform::class);

        /** @var Driver $driver */
        $driver = $this->createMock(Driver::class);

        (new Connection($connectionParams, $driver))->executeCacheQuery($query, [], [], $queryCacheProfileMock);
    }

    /**
     * @group #2821
     */
    public function testThrowsExceptionWhenInValidPlatformSpecified() : void
    {
        $connectionParams             = $this->params;
        $connectionParams['platform'] = new stdClass();

        /** @var Driver $driver */
        $driver = $this->createMock(Driver::class);

        $this->expectException(DBALException::class);

        new Connection($connectionParams, $driver);
    }

    /**
     * @group DBAL-990
     */
    public function testRethrowsOriginalExceptionOnDeterminingPlatformWhenConnectingToNonExistentDatabase()
    {
        /** @var VersionAwarePlatformDriverMock|PHPUnit_Framework_MockObject_MockObject $driverMock */
        $driverMock = $this->createMock(VersionAwarePlatformDriverMock::class);

        $connection        = new Connection(['dbname' => 'foo'], $driverMock);
        $originalException = new Exception('Original exception');
        $fallbackException = new Exception('Fallback exception');

        $driverMock->expects($this->at(0))
            ->method('connect')
            ->willThrowException($originalException);

        $driverMock->expects($this->at(1))
            ->method('connect')
            ->willThrowException($fallbackException);

        $this->expectExceptionMessage($originalException->getMessage());

        $connection->getDatabasePlatform();
    }
}
