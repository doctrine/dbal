<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Logging\EchoSQLLogger;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Doctrine\Tests\DbalTestCase;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;

/**
 * @requires extension pdo_mysql
 */
class ConnectionTest extends DbalTestCase
{
    /** @var Connection */
    private $connection;

    /** @var array{wrapperClass?: class-string<Connection>} */
    protected $params = [
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'user' => 'root',
        'password' => 'password',
        'port' => '1234',
    ];

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection($this->params);
    }

    /**
     * @return Connection&MockObject
     */
    private function getExecuteStatementMockConnection()
    {
        $driverMock = $this->createMock(Driver::class);

        $driverMock->expects($this->any())
            ->method('connect')
            ->willReturn(
                $this->createMock(DriverConnection::class)
            );

        $platform = $this->getMockForAbstractClass(AbstractPlatform::class);

        return (new MockBuilderProxy($this->getMockBuilder(Connection::class)))
            ->onlyMethods(['executeStatement'])
            ->setConstructorArgs([['platform' => $platform], $driverMock])
            ->getMock();
    }

    public function testIsConnected(): void
    {
        self::assertFalse($this->connection->isConnected());
    }

    public function testNoTransactionActiveByDefault(): void
    {
        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testCommitWithNoActiveTransactionThrowsException(): void
    {
        $this->expectException(ConnectionException::class);
        $this->connection->commit();
    }

    public function testRollbackWithNoActiveTransactionThrowsException(): void
    {
        $this->expectException(ConnectionException::class);
        $this->connection->rollBack();
    }

    public function testSetRollbackOnlyNoActiveTransactionThrowsException(): void
    {
        $this->expectException(ConnectionException::class);
        $this->connection->setRollbackOnly();
    }

    public function testIsRollbackOnlyNoActiveTransactionThrowsException(): void
    {
        $this->expectException(ConnectionException::class);
        $this->connection->isRollbackOnly();
    }

    public function testGetConfiguration(): void
    {
        $config = $this->connection->getConfiguration();

        self::assertInstanceOf(Configuration::class, $config);
    }

    public function testGetHost(): void
    {
        self::assertEquals('localhost', $this->connection->getHost());
    }

    public function testGetPort(): void
    {
        self::assertEquals('1234', $this->connection->getPort());
    }

    public function testGetUsername(): void
    {
        self::assertEquals('root', $this->connection->getUsername());
    }

    public function testGetPassword(): void
    {
        self::assertEquals('password', $this->connection->getPassword());
    }

    public function testGetDriver(): void
    {
        self::assertInstanceOf(Driver\PDO\MySQL\Driver::class, $this->connection->getDriver());
    }

    public function testGetEventManager(): void
    {
        self::assertInstanceOf(EventManager::class, $this->connection->getEventManager());
    }

    public function testConnectDispatchEvent(): void
    {
        $listenerMock = $this->createMock(ConnectDispatchEventListener::class);
        $listenerMock->expects($this->once())->method('postConnect');

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::postConnect], $listenerMock);

        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects($this->once())
                   ->method('connect');

        $conn = new Connection([], $driverMock, new Configuration(), $eventManager);
        $conn->connect();
    }

    public function testEventManagerPassedToPlatform(): void
    {
        $eventManager = new EventManager();

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects($this->once())
            ->method('setEventManager')
            ->with($eventManager);

        $driver = $this->createMock(Driver::class);
        $driver->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection = new Connection($this->params, $driver, null, $eventManager);
        $connection->getDatabasePlatform();
    }

    /**
     * @requires extension pdo_sqlite
     * @dataProvider getQueryMethods
     */
    public function testDriverExceptionIsWrapped(string $method): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(<<<EOF
An exception occurred while executing 'MUUHAAAAHAAAA':

SQLSTATE[HY000]: General error: 1 near "MUUHAAAAHAAAA"
EOF
        );

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $connection->$method('MUUHAAAAHAAAA');
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function getQueryMethods(): iterable
    {
        return [
            ['exec'],
            ['query'],
            ['executeQuery'],
            ['executeStatement'],
            ['prepare'],
        ];
    }

    /**
     * Pretty dumb test, however we want to check that the EchoSQLLogger correctly implements the interface.
     */
    public function testEchoSQLLogger(): void
    {
        $logger = new EchoSQLLogger();
        $this->connection->getConfiguration()->setSQLLogger($logger);
        self::assertSame($logger, $this->connection->getConfiguration()->getSQLLogger());
    }

    /**
     * Pretty dumb test, however we want to check that the DebugStack correctly implements the interface.
     */
    public function testDebugSQLStack(): void
    {
        $logger = new DebugStack();
        $this->connection->getConfiguration()->setSQLLogger($logger);
        self::assertSame($logger, $this->connection->getConfiguration()->getSQLLogger());
    }

    public function testIsAutoCommit(): void
    {
        self::assertTrue($this->connection->isAutoCommit());
    }

    public function testSetAutoCommit(): void
    {
        $this->connection->setAutoCommit(false);
        self::assertFalse($this->connection->isAutoCommit());
    }

    public function testConnectStartsTransactionInNoAutoCommitMode(): void
    {
        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects($this->any())
            ->method('connect')
            ->willReturn(
                $this->createMock(DriverConnection::class)
            );
        $conn = new Connection([], $driverMock);

        $conn->setAutoCommit(false);

        self::assertFalse($conn->isTransactionActive());

        $conn->connect();

        self::assertTrue($conn->isTransactionActive());
    }

    public function testCommitStartsTransactionInNoAutoCommitMode(): void
    {
        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects($this->any())
            ->method('connect')
            ->willReturn(
                $this->createMock(DriverConnection::class)
            );
        $conn = new Connection([], $driverMock);

        $conn->setAutoCommit(false);
        $conn->connect();
        $conn->commit();

        self::assertTrue($conn->isTransactionActive());
    }

    /**
     * @dataProvider resultProvider
     */
    public function testCommitReturn(bool $expectedResult): void
    {
        $driverConnection = $this->createMock(DriverConnection::class);
        $driverConnection->expects($this->once())
            ->method('commit')->willReturn($expectedResult);

        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects($this->any())
            ->method('connect')
            ->willReturn($driverConnection);

        $conn = new Connection([], $driverMock);

        $conn->connect();
        $conn->beginTransaction();

        self::assertSame($expectedResult, $conn->commit());
    }

    /**
     * @return bool[][]
     */
    public function resultProvider(): array
    {
        return [[true], [false]];
    }

    public function testRollBackStartsTransactionInNoAutoCommitMode(): void
    {
        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects($this->any())
            ->method('connect')
            ->willReturn(
                $this->createMock(DriverConnection::class)
            );
        $conn = new Connection([], $driverMock);

        $conn->setAutoCommit(false);
        $conn->connect();
        $conn->rollBack();

        self::assertTrue($conn->isTransactionActive());
    }

    public function testSwitchingAutoCommitModeCommitsAllCurrentTransactions(): void
    {
        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects($this->any())
            ->method('connect')
            ->willReturn(
                $this->createMock(DriverConnection::class)
            );
        $conn = new Connection([], $driverMock);

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

    public function testEmptyInsert(): void
    {
        $conn = $this->getExecuteStatementMockConnection();

        $conn->expects($this->once())
            ->method('executeStatement')
            ->with('INSERT INTO footable () VALUES ()');

        $conn->insert('footable', []);
    }

    public function testUpdateWithDifferentColumnsInDataAndIdentifiers(): void
    {
        $conn = $this->getExecuteStatementMockConnection();

        $conn->expects($this->once())
            ->method('executeStatement')
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

    public function testUpdateWithSameColumnInDataAndIdentifiers(): void
    {
        $conn = $this->getExecuteStatementMockConnection();

        $conn->expects($this->once())
            ->method('executeStatement')
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

    public function testUpdateWithIsNull(): void
    {
        $conn = $this->getExecuteStatementMockConnection();

        $conn->expects($this->once())
            ->method('executeStatement')
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

    public function testDeleteWithIsNull(): void
    {
        $conn = $this->getExecuteStatementMockConnection();

        $conn->expects($this->once())
            ->method('executeStatement')
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

    public function testFetchAssoc(): void
    {
        $statement = 'SELECT * FROM foo WHERE bar = ?';
        $params    = [666];
        $types     = [ParameterType::INTEGER];
        $result    = [];

        $driverMock = $this->createMock(Driver::class);

        $driverMock->expects($this->any())
            ->method('connect')
            ->willReturn(
                $this->createMock(DriverConnection::class)
            );

        $driverStatementMock = $this->createMock(Statement::class);

        $driverStatementMock->expects($this->once())
            ->method('fetch')
            ->with(FetchMode::ASSOCIATIVE)
            ->willReturn($result);

        $conn = (new MockBuilderProxy($this->getMockBuilder(Connection::class)))
            ->onlyMethods(['executeQuery'])
            ->setConstructorArgs([[], $driverMock])
            ->getMock();

        $conn->expects($this->once())
            ->method('executeQuery')
            ->with($statement, $params, $types)
            ->willReturn($driverStatementMock);

        self::assertSame($result, $conn->fetchAssoc($statement, $params, $types));
    }

    public function testFetchArray(): void
    {
        $statement = 'SELECT * FROM foo WHERE bar = ?';
        $params    = [666];
        $types     = [ParameterType::INTEGER];
        $result    = [];

        $driverMock = $this->createMock(Driver::class);

        $driverMock->expects($this->any())
            ->method('connect')
            ->willReturn(
                $this->createMock(DriverConnection::class)
            );

        $driverStatementMock = $this->createMock(Statement::class);

        $driverStatementMock->expects($this->once())
            ->method('fetch')
            ->with(FetchMode::NUMERIC)
            ->willReturn($result);

        $conn = (new MockBuilderProxy($this->getMockBuilder(Connection::class)))
            ->onlyMethods(['executeQuery'])
            ->setConstructorArgs([[], $driverMock])
            ->getMock();

        $conn->expects($this->once())
            ->method('executeQuery')
            ->with($statement, $params, $types)
            ->willReturn($driverStatementMock);

        self::assertSame($result, $conn->fetchArray($statement, $params, $types));
    }

    public function testFetchColumn(): void
    {
        $statement = 'SELECT * FROM foo WHERE bar = ?';
        $params    = [666];
        $types     = [ParameterType::INTEGER];
        $column    = 0;
        $result    = [];

        $driverMock = $this->createMock(Driver::class);

        $driverMock->expects($this->any())
            ->method('connect')
            ->willReturn(
                $this->createMock(DriverConnection::class)
            );

        $driverStatementMock = $this->createMock(Statement::class);

        $driverStatementMock->expects($this->once())
            ->method('fetchColumn')
            ->with($column)
            ->willReturn($result);

        $conn = (new MockBuilderProxy($this->getMockBuilder(Connection::class)))
            ->onlyMethods(['executeQuery'])
            ->setConstructorArgs([[], $driverMock])
            ->getMock();

        $conn->expects($this->once())
            ->method('executeQuery')
            ->with($statement, $params, $types)
            ->willReturn($driverStatementMock);

        self::assertSame($result, $conn->fetchColumn($statement, $params, $column, $types));
    }

    public function testFetchAll(): void
    {
        $statement = 'SELECT * FROM foo WHERE bar = ?';
        $params    = [666];
        $types     = [ParameterType::INTEGER];
        $result    = [];

        $driverMock = $this->createMock(Driver::class);

        $driverMock->expects($this->any())
            ->method('connect')
            ->willReturn(
                $this->createMock(DriverConnection::class)
            );

        $driverStatementMock = $this->createMock(Statement::class);

        $driverStatementMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($result);

        $conn = (new MockBuilderProxy($this->getMockBuilder(Connection::class)))
            ->onlyMethods(['executeQuery'])
            ->setConstructorArgs([[], $driverMock])
            ->getMock();

        $conn->expects($this->once())
            ->method('executeQuery')
            ->with($statement, $params, $types)
            ->willReturn($driverStatementMock);

        self::assertSame($result, $conn->fetchAll($statement, $params, $types));
    }

    public function testConnectionThrowsExceptionWhenInvalidPDOProvided(): void
    {
        $driverMock = $this->createMock(Driver::class);
        $pdo        = $this->createMock(stdClass::class);

        $this->expectException(Exception::class);

        new Connection(['pdo' => $pdo], $driverMock);
    }

    public function testConnectionDoesNotMaintainTwoReferencesToExternalPDO(): void
    {
        $driverMock = $this->createMock(Driver::class);
        $pdo        = $this->createMock(PDO::class);

        $conn = new Connection(['pdo' => $pdo], $driverMock);

        self::assertArrayNotHasKey('pdo', $conn->getParams());
    }

    public function testPassingExternalPDOMeansConnectionIsConnected(): void
    {
        $driverMock = $this->createMock(Driver::class);
        $pdo        = $this->createMock(PDO::class);

        $conn = new Connection(['pdo' => $pdo], $driverMock);

        self::assertTrue($conn->isConnected(), 'Connection is not connected after passing external PDO');
    }

    public function testCallingDeleteWithNoDeletionCriteriaResultsInInvalidArgumentException(): void
    {
        $driver = $this->createMock(Driver::class);
        $conn   = new Connection([], $driver);

        $this->expectException(InvalidArgumentException::class);
        $conn->delete('kittens', []);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function dataCallConnectOnce(): iterable
    {
        return [
            ['delete', ['tbl', ['id' => 12345]]],
            ['insert', ['tbl', ['data' => 'foo']]],
            ['update', ['tbl', ['data' => 'bar'], ['id' => 12345]]],
            ['prepare', ['select * from dual']],
            ['executeStatement', ['insert into tbl (id) values (?)'], [123]],
        ];
    }

    /**
     * @param array<int, mixed> $params
     *
     * @dataProvider dataCallConnectOnce
     */
    public function testCallConnectOnce(string $method, array $params): void
    {
        $wrappedConnection = $this->createMock(DriverConnection::class);
        $driver            = $this->createMock(Driver::class);
        $stmt              = $this->createMock(Statement::class);
        $driver->expects(self::once())
            ->method('connect')
            ->willReturn($wrappedConnection);

        $wrappedConnection
            ->method('prepare')
            ->willReturn($stmt);

        $platform = $this->createMock(AbstractPlatform::class);

        $conn = new Connection(['platform' => $platform], $driver);

        $conn->$method(...$params);
    }

    public function testPlatformDetectionIsTriggerOnlyOnceOnRetrievingPlatform(): void
    {
        $driverMock = $this->createMock(FutureVersionAwarePlatformDriver::class);

        $driverConnectionMock = $this->createMock(ServerInfoAwareConnection::class);

        $platformMock = $this->getMockForAbstractClass(AbstractPlatform::class);

        $connection = new Connection([], $driverMock);

        $driverMock->expects($this->once())
            ->method('connect')
            ->willReturn($driverConnectionMock);

        $driverConnectionMock->expects($this->once())
            ->method('requiresQueryForServerVersion')
            ->willReturn(false);

        $driverConnectionMock->expects($this->once())
            ->method('getServerVersion')
            ->willReturn('6.6.6');

        $driverMock->expects($this->once())
            ->method('createDatabasePlatformForVersion')
            ->with('6.6.6')
            ->willReturn($platformMock);

        self::assertSame($platformMock, $connection->getDatabasePlatform());
    }

    public function testConnectionParamsArePassedToTheQueryCacheProfileInExecuteCacheQuery(): void
    {
        $resultCacheDriverMock = $this->createMock(Cache::class);

        $resultCacheDriverMock
            ->expects($this->atLeastOnce())
            ->method('fetch')
            ->with('cacheKey')
            ->willReturn(['realKey' => []]);

        $query  = 'SELECT * FROM foo WHERE bar = ?';
        $params = [666];
        $types  = [ParameterType::INTEGER];

        $queryCacheProfileMock = $this->createMock(QueryCacheProfile::class);

        $queryCacheProfileMock
            ->expects($this->any())
            ->method('getResultCacheDriver')
            ->willReturn($resultCacheDriverMock);

        // This is our main expectation
        $queryCacheProfileMock
            ->expects($this->once())
            ->method('generateCacheKeys')
            ->with($query, $params, $types, $this->params)
            ->willReturn(['cacheKey', 'realKey']);

        $driver = $this->createMock(Driver::class);
        $result = (new Connection($this->params, $driver))
            ->executeCacheQuery($query, $params, $types, $queryCacheProfileMock);

        self::assertInstanceOf(Driver\ResultStatement::class, $result);
        self::assertInstanceOf(Result::class, $result);
    }

    public function testShouldNotPassPlatformInParamsToTheQueryCacheProfileInExecuteCacheQuery(): void
    {
        $resultCacheDriverMock = $this->createMock(Cache::class);

        $resultCacheDriverMock
            ->expects($this->atLeastOnce())
            ->method('fetch')
            ->with('cacheKey')
            ->willReturn(['realKey' => []]);

        $queryCacheProfileMock = $this->createMock(QueryCacheProfile::class);

        $queryCacheProfileMock
            ->expects($this->any())
            ->method('getResultCacheDriver')
            ->willReturn($resultCacheDriverMock);

        $query = 'SELECT 1';

        $connectionParams = $this->params;

        $queryCacheProfileMock
            ->expects($this->once())
            ->method('generateCacheKeys')
            ->with($query, [], [], $connectionParams)
            ->willReturn(['cacheKey', 'realKey']);

        $connectionParams['platform'] = $this->createMock(AbstractPlatform::class);

        $driver = $this->createMock(Driver::class);

        (new Connection($connectionParams, $driver))->executeCacheQuery($query, [], [], $queryCacheProfileMock);
    }

    public function testThrowsExceptionWhenInValidPlatformSpecified(): void
    {
        $connectionParams             = $this->params;
        $connectionParams['platform'] = new stdClass();

        $driver = $this->createMock(Driver::class);

        $this->expectException(Exception::class);

        new Connection($connectionParams, $driver);
    }

    public function testRethrowsOriginalExceptionOnDeterminingPlatformWhenConnectingToNonExistentDatabase(): void
    {
        $driverMock = $this->createMock(FutureVersionAwarePlatformDriver::class);

        $connection        = new Connection(['dbname' => 'foo'], $driverMock);
        $originalException = new \Exception('Original exception');
        $fallbackException = new \Exception('Fallback exception');

        $driverMock->method('connect')
            ->will(self::onConsecutiveCalls(
                self::throwException($originalException),
                self::throwException($fallbackException)
            ));

        $this->expectExceptionMessage($originalException->getMessage());

        $connection->getDatabasePlatform();
    }

    public function testExecuteCacheQueryStripsPlatformFromConnectionParamsBeforeGeneratingCacheKeys(): void
    {
        $driver = $this->createMock(Driver::class);

        $platform = $this->createMock(AbstractPlatform::class);

        $queryCacheProfile = $this->createMock(QueryCacheProfile::class);

        $resultCacheDriver = $this->createMock(Cache::class);

        $queryCacheProfile
            ->expects($this->any())
            ->method('getResultCacheDriver')
            ->willReturn($resultCacheDriver);

        $resultCacheDriver
            ->expects($this->atLeastOnce())
            ->method('fetch')
            ->with('cacheKey')
            ->willReturn(['realKey' => []]);

        $query = 'SELECT 1';

        $params = [
            'dbname' => 'foo',
            'platform' => $platform,
        ];

        $paramsWithoutPlatform = $params;
        unset($paramsWithoutPlatform['platform']);

        $queryCacheProfile
            ->expects($this->once())
            ->method('generateCacheKeys')
            ->with($query, [], [], $paramsWithoutPlatform)
            ->willReturn(['cacheKey', 'realKey']);

        $connection = new Connection($params, $driver);

        self::assertSame($params, $connection->getParams());

        $connection->executeCacheQuery($query, [], [], $queryCacheProfile);
    }
}

interface ConnectDispatchEventListener
{
    public function postConnect(): void;
}
