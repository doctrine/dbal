<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Abstraction\Result;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @requires extension pdo_mysql
 */
class ConnectionTest extends TestCase
{
    /** @var Connection */
    private $connection;

    /** @var array<string, mixed> */
    protected $params = [
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'user' => 'root',
        'password' => 'password',
        'port' => 1234,
    ];

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection($this->params);
    }

    /**
     * @return Connection|MockObject
     */
    private function getExecuteUpdateMockConnection()
    {
        $driverMock = $this->createMock(Driver::class);

        $driverMock->expects(self::any())
            ->method('connect')
            ->will(self::returnValue(
                $this->createMock(DriverConnection::class)
            ));

        $platform = $this->getMockForAbstractClass(AbstractPlatform::class);

        return $this->getMockBuilder(Connection::class)
            ->onlyMethods(['executeUpdate'])
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

    public function testGetDriver(): void
    {
        self::assertInstanceOf(\Doctrine\DBAL\Driver\PDOMySql\Driver::class, $this->connection->getDriver());
    }

    public function testConnectDispatchEvent(): void
    {
        $listenerMock = $this->createMock(ConnectDispatchEventListener::class);
        $listenerMock->expects(self::once())->method('postConnect');

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::postConnect], $listenerMock);

        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects(self::at(0))
                   ->method('connect');

        $conn = new Connection([], $driverMock, new Configuration(), $eventManager);
        $conn->connect();
    }

    public function testEventManagerPassedToPlatform(): void
    {
        $eventManager = new EventManager();

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects(self::once())
            ->method('setEventManager')
            ->with($eventManager);

        $driver = $this->createMock(Driver::class);
        $driver->expects(self::any())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection = new Connection($this->params, $driver, null, $eventManager);
        $connection->getDatabasePlatform();
    }

    /**
     * @requires extension pdo_sqlite
     * @dataProvider getQueryMethods
     */
    public function testDriverExceptionIsWrapped(callable $callback): void
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage("An exception occurred while executing \"MUUHAAAAHAAAA\":\n\nSQLSTATE[HY000]: General error: 1 near \"MUUHAAAAHAAAA\"");

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $callback($connection, 'MUUHAAAAHAAAA');
    }

    /**
     * @return iterable<string, array<int, callable>>
     */
    public static function getQueryMethods(): iterable
    {
        yield 'exec' => [
            static function (Connection $connection, string $statement): void {
                $connection->exec($statement);
            },
        ];

        yield 'query' => [
            static function (Connection $connection, string $statement): void {
                $connection->query($statement);
            },
        ];

        yield 'executeQuery' => [
            static function (Connection $connection, string $statement): void {
                $connection->executeQuery($statement);
            },
        ];

        yield 'executeUpdate' => [
            static function (Connection $connection, string $statement): void {
                $connection->executeUpdate($statement);
            },
        ];

        yield 'prepare' => [
            static function (Connection $connection, string $statement): void {
                $connection->prepare($statement);
            },
        ];
    }

    /**
     * Pretty dumb test, however we want to check that the DebugStack correctly implements the interface.
     *
     * @group DBAL-11
     */
    public function testDebugSQLStack(): void
    {
        $logger = new DebugStack();
        $this->connection->getConfiguration()->setSQLLogger($logger);
        self::assertSame($logger, $this->connection->getConfiguration()->getSQLLogger());
    }

    /**
     * @group DBAL-81
     */
    public function testIsAutoCommit(): void
    {
        self::assertTrue($this->connection->isAutoCommit());
    }

    /**
     * @group DBAL-81
     */
    public function testSetAutoCommit(): void
    {
        $this->connection->setAutoCommit(false);
        self::assertFalse($this->connection->isAutoCommit());
    }

    /**
     * @group DBAL-81
     */
    public function testConnectStartsTransactionInNoAutoCommitMode(): void
    {
        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects(self::any())
            ->method('connect')
            ->will(self::returnValue(
                $this->createMock(DriverConnection::class)
            ));
        $conn = new Connection([], $driverMock);

        $conn->setAutoCommit(false);

        self::assertFalse($conn->isTransactionActive());

        $conn->connect();

        self::assertTrue($conn->isTransactionActive());
    }

    /**
     * @group DBAL-81
     */
    public function testCommitStartsTransactionInNoAutoCommitMode(): void
    {
        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects(self::any())
            ->method('connect')
            ->will(self::returnValue(
                $this->createMock(DriverConnection::class)
            ));
        $conn = new Connection([], $driverMock);

        $conn->setAutoCommit(false);
        $conn->connect();
        $conn->commit();

        self::assertTrue($conn->isTransactionActive());
    }

    /**
     * @return bool[][]
     */
    public function resultProvider(): array
    {
        return [[true], [false]];
    }

    /**
     * @group DBAL-81
     */
    public function testRollBackStartsTransactionInNoAutoCommitMode(): void
    {
        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects(self::any())
            ->method('connect')
            ->will(self::returnValue(
                $this->createMock(DriverConnection::class)
            ));
        $conn = new Connection([], $driverMock);

        $conn->setAutoCommit(false);
        $conn->connect();
        $conn->rollBack();

        self::assertTrue($conn->isTransactionActive());
    }

    /**
     * @group DBAL-81
     */
    public function testSwitchingAutoCommitModeCommitsAllCurrentTransactions(): void
    {
        $driverMock = $this->createMock(Driver::class);
        $driverMock->expects(self::any())
            ->method('connect')
            ->will(self::returnValue(
                $this->createMock(DriverConnection::class)
            ));
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
        $conn = $this->getExecuteUpdateMockConnection();

        $conn->expects(self::once())
            ->method('executeUpdate')
            ->with('INSERT INTO footable () VALUES ()');

        $conn->insert('footable', []);
    }

    /**
     * @group DBAL-2511
     */
    public function testUpdateWithDifferentColumnsInDataAndIdentifiers(): void
    {
        $conn = $this->getExecuteUpdateMockConnection();

        $conn->expects(self::once())
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
    public function testUpdateWithSameColumnInDataAndIdentifiers(): void
    {
        $conn = $this->getExecuteUpdateMockConnection();

        $conn->expects(self::once())
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
    public function testUpdateWithIsNull(): void
    {
        $conn = $this->getExecuteUpdateMockConnection();

        $conn->expects(self::once())
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
    public function testDeleteWithIsNull(): void
    {
        $conn = $this->getExecuteUpdateMockConnection();

        $conn->expects(self::once())
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

    /**
     * @param mixed $expected
     *
     * @dataProvider fetchModeProvider
     */
    public function testFetch(string $method, callable $invoke, $expected): void
    {
        $query  = 'SELECT * FROM foo WHERE bar = ?';
        $params = [666];
        $types  = [ParameterType::INTEGER];

        $result = $this->createMock(Result::class);
        $result->expects(self::once())
            ->method($method)
            ->willReturn($expected);

        $driver = $this->createConfiguredMock(Driver::class, [
            'connect' => $this->createMock(DriverConnection::class),
        ]);

        $conn = $this->getMockBuilder(Connection::class)
            ->onlyMethods(['executeQuery'])
            ->setConstructorArgs([[], $driver])
            ->getMock();

        $conn->expects(self::once())
            ->method('executeQuery')
            ->with($query, $params, $types)
            ->willReturn($result);

        self::assertSame($expected, $invoke($conn, $query, $params, $types));
    }

    /**
     * @return iterable<string,array<int,mixed>>
     */
    public static function fetchModeProvider(): iterable
    {
        yield 'numeric' => [
            'fetchNumeric',
            static function (Connection $connection, string $query, array $params, array $types) {
                return $connection->fetchNumeric($query, $params, $types);
            },
            ['bar'],
        ];

        yield 'associative' => [
            'fetchAssociative',
            static function (Connection $connection, string $query, array $params, array $types) {
                return $connection->fetchAssociative($query, $params, $types);
            },
            ['foo' => 'bar'],
        ];

        yield 'one' => [
            'fetchOne',
            static function (Connection $connection, string $query, array $params, array $types) {
                return $connection->fetchOne($query, $params, $types);
            },
            'bar',
        ];

        yield 'all-numeric' => [
            'fetchAllNumeric',
            static function (Connection $connection, string $query, array $params, array $types): array {
                return $connection->fetchAllNumeric($query, $params, $types);
            },
            [
                ['bar'],
                ['baz'],
            ],
        ];

        yield 'all-associative' => [
            'fetchAllAssociative',
            static function (Connection $connection, string $query, array $params, array $types): array {
                return $connection->fetchAllAssociative($query, $params, $types);
            },
            [
                ['foo' => 'bar'],
                ['foo' => 'baz'],
            ],
        ];

        yield 'first-column' => [
            'fetchFirstColumn',
            static function (Connection $connection, string $query, array $params, array $types): array {
                return $connection->fetchFirstColumn($query, $params, $types);
            },
            [
                'bar',
                'baz',
            ],
        ];
    }

    public function testCallingDeleteWithNoDeletionCriteriaResultsInInvalidArgumentException(): void
    {
        $driver = $this->createMock(Driver::class);
        $conn   = new Connection([], $driver);

        $this->expectException(InvalidArgumentException::class);
        $conn->delete('kittens', []);
    }

    public function testCallConnectOnce(): void
    {
        $driver = $this->createMock(Driver::class);
        $driver->expects(self::once())
            ->method('connect');

        $platform = $this->createMock(AbstractPlatform::class);

        $conn = new Connection(['platform' => $platform], $driver);
        $conn->connect();
        $conn->connect();
    }

    /**
     * @group DBAL-1127
     */
    public function testPlatformDetectionIsTriggerOnlyOnceOnRetrievingPlatform(): void
    {
        $driverMock = $this->createMock(VersionAwarePlatformDriver::class);

        $driverConnectionMock = $this->createMock(ServerInfoAwareConnection::class);

        $platformMock = $this->getMockForAbstractClass(AbstractPlatform::class);

        $connection = new Connection([], $driverMock);

        $driverMock->expects(self::once())
            ->method('connect')
            ->will(self::returnValue($driverConnectionMock));

        $driverConnectionMock->expects(self::once())
            ->method('getServerVersion')
            ->will(self::returnValue('6.6.6'));

        $driverMock->expects(self::once())
            ->method('createDatabasePlatformForVersion')
            ->with('6.6.6')
            ->will(self::returnValue($platformMock));

        self::assertSame($platformMock, $connection->getDatabasePlatform());
    }

    public function testConnectionParamsArePassedToTheQueryCacheProfileInExecuteCacheQuery(): void
    {
        $resultCacheDriverMock = $this->createMock(Cache::class);

        $resultCacheDriverMock
            ->expects(self::atLeastOnce())
            ->method('fetch')
            ->with('cacheKey')
            ->will(self::returnValue(['realKey' => []]));

        $query  = 'SELECT * FROM foo WHERE bar = ?';
        $params = [666];
        $types  = [ParameterType::INTEGER];

        $queryCacheProfileMock = $this->createMock(QueryCacheProfile::class);

        $queryCacheProfileMock
            ->expects(self::any())
            ->method('getResultCacheDriver')
            ->will(self::returnValue($resultCacheDriverMock));

        // This is our main expectation
        $queryCacheProfileMock
            ->expects(self::once())
            ->method('generateCacheKeys')
            ->with($query, $params, $types, $this->params)
            ->will(self::returnValue(['cacheKey', 'realKey']));

        $driver = $this->createMock(Driver::class);

        (new Connection($this->params, $driver))->executeCacheQuery($query, $params, $types, $queryCacheProfileMock);
    }

    /**
     * @group #2821
     */
    public function testShouldNotPassPlatformInParamsToTheQueryCacheProfileInExecuteCacheQuery(): void
    {
        $resultCacheDriverMock = $this->createMock(Cache::class);

        $resultCacheDriverMock
            ->expects(self::atLeastOnce())
            ->method('fetch')
            ->with('cacheKey')
            ->will(self::returnValue(['realKey' => []]));

        $queryCacheProfileMock = $this->createMock(QueryCacheProfile::class);

        $queryCacheProfileMock
            ->expects(self::any())
            ->method('getResultCacheDriver')
            ->will(self::returnValue($resultCacheDriverMock));

        $query = 'SELECT 1';

        $connectionParams = $this->params;

        $queryCacheProfileMock
            ->expects(self::once())
            ->method('generateCacheKeys')
            ->with($query, [], [], $connectionParams)
            ->will(self::returnValue(['cacheKey', 'realKey']));

        $connectionParams['platform'] = $this->createMock(AbstractPlatform::class);

        $driver = $this->createMock(Driver::class);

        (new Connection($connectionParams, $driver))->executeCacheQuery($query, [], [], $queryCacheProfileMock);
    }

    /**
     * @group #2821
     */
    public function testThrowsExceptionWhenInValidPlatformSpecified(): void
    {
        $connectionParams             = $this->params;
        $connectionParams['platform'] = new stdClass();

        $driver = $this->createMock(Driver::class);

        $this->expectException(DBALException::class);

        new Connection($connectionParams, $driver);
    }

    /**
     * @group DBAL-990
     */
    public function testRethrowsOriginalExceptionOnDeterminingPlatformWhenConnectingToNonExistentDatabase(): void
    {
        $driverMock = $this->createMock(VersionAwarePlatformDriver::class);

        $connection        = new Connection(['dbname' => 'foo'], $driverMock);
        $originalException = new Exception('Original exception');
        $fallbackException = new Exception('Fallback exception');

        $driverMock->expects(self::at(0))
            ->method('connect')
            ->willThrowException($originalException);

        $driverMock->expects(self::at(1))
            ->method('connect')
            ->willThrowException($fallbackException);

        $this->expectExceptionMessage($originalException->getMessage());

        $connection->getDatabasePlatform();
    }

    /**
     * @group #3194
     */
    public function testExecuteCacheQueryStripsPlatformFromConnectionParamsBeforeGeneratingCacheKeys(): void
    {
        $driver = $this->createMock(Driver::class);

        $platform = $this->createMock(AbstractPlatform::class);

        $queryCacheProfile = $this->createMock(QueryCacheProfile::class);

        $resultCacheDriver = $this->createMock(Cache::class);

        $queryCacheProfile
            ->expects(self::any())
            ->method('getResultCacheDriver')
            ->will(self::returnValue($resultCacheDriver));

        $resultCacheDriver
            ->expects(self::atLeastOnce())
            ->method('fetch')
            ->with('cacheKey')
            ->will(self::returnValue(['realKey' => []]));

        $query = 'SELECT 1';

        $params = [
            'dbname' => 'foo',
            'platform' => $platform,
        ];

        $paramsWithoutPlatform = $params;
        unset($paramsWithoutPlatform['platform']);

        $queryCacheProfile
            ->expects(self::once())
            ->method('generateCacheKeys')
            ->with($query, [], [], $paramsWithoutPlatform)
            ->will(self::returnValue(['cacheKey', 'realKey']));

        $connection = new Connection($params, $driver);

        self::assertSame($params, $connection->getParams());

        $connection->executeCacheQuery($query, [], [], $queryCacheProfile);
    }
}

interface ConnectDispatchEventListener
{
    public function postConnect(): void;
}
