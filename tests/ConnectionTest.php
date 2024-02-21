<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaManagerFactory;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;
use Doctrine\DBAL\ServerVersionProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/** @psalm-import-type Params from DriverManager */
#[RequiresPhpExtension('pdo_mysql')]
class ConnectionTest extends TestCase
{
    private Connection $connection;

    private const CONNECTION_PARAMS = [
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'user' => 'root',
        'password' => 'password',
        'port' => 1234,
    ];

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(self::CONNECTION_PARAMS);
    }

    private function getExecuteStatementMockConnection(): Connection&MockObject
    {
        $driverMock = $this->createMock(Driver::class);

        return $this->getMockBuilder(Connection::class)
            ->onlyMethods(['executeStatement'])
            ->setConstructorArgs([[], $driverMock])
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
        self::assertInstanceOf(Driver\PDO\MySQL\Driver::class, $this->connection->getDriver());
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    #[DataProvider('getQueryMethods')]
    public function testDriverExceptionIsWrapped(callable $callback): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'An exception occurred while executing a query: SQLSTATE[HY000]: General error: 1 near "MUUHAAAAHAAAA"',
        );

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $callback($connection, 'MUUHAAAAHAAAA');
    }

    /** @return iterable<string, array<int, callable>> */
    public static function getQueryMethods(): iterable
    {
        yield 'executeQuery' => [
            static function (Connection $connection, string $statement): void {
                $connection->executeQuery($statement);
            },
        ];

        yield 'executeStatement' => [
            static function (Connection $connection, string $statement): void {
                $connection->executeStatement($statement);
            },
        ];

        yield 'prepare' => [
            static function (Connection $connection, string $statement): void {
                $connection->prepare($statement);
            },
        ];
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

        $conn = new Connection([], $driverMock);

        $conn->setAutoCommit(false);

        self::assertFalse($conn->isTransactionActive());

        $conn->executeQuery('SELECT 1');

        self::assertTrue($conn->isTransactionActive());
    }

    public function testCommitStartsTransactionInNoAutoCommitMode(): void
    {
        $driverMock = $this->createMock(Driver::class);

        $conn = new Connection([], $driverMock);

        $conn->setAutoCommit(false);
        $conn->executeQuery('SELECT 1');
        $conn->commit();

        self::assertTrue($conn->isTransactionActive());
    }

    /** @return bool[][] */
    public static function resultProvider(): array
    {
        return [[true], [false]];
    }

    public function testRollBackStartsTransactionInNoAutoCommitMode(): void
    {
        $driverMock = $this->createMock(Driver::class);

        $conn = new Connection([], $driverMock);

        $conn->setAutoCommit(false);
        $conn->executeQuery('SELECT 1');
        $conn->rollBack();

        self::assertTrue($conn->isTransactionActive());
    }

    public function testSwitchingAutoCommitModeCommitsAllCurrentTransactions(): void
    {
        $platform = self::createStub(AbstractPlatform::class);
        $platform
            ->method('supportsSavepoints')
            ->willReturn(true);

        $driverMock = $this->createMock(Driver::class);
        $driverMock
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $conn = new Connection([], $driverMock);

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

        $conn->expects(self::once())
            ->method('executeStatement')
            ->with('INSERT INTO footable () VALUES ()');

        $conn->insert('footable', []);
    }

    public function testUpdateWithDifferentColumnsInDataAndIdentifiers(): void
    {
        $conn = $this->getExecuteStatementMockConnection();

        $conn->expects(self::once())
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
                ],
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
            ],
        );
    }

    public function testUpdateWithSameColumnInDataAndIdentifiers(): void
    {
        $conn = $this->getExecuteStatementMockConnection();

        $conn->expects(self::once())
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
                ],
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
            ],
        );
    }

    public function testUpdateWithIsNull(): void
    {
        $conn = $this->getExecuteStatementMockConnection();

        $conn->expects(self::once())
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
                ],
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
            ],
        );
    }

    public function testDeleteWithIsNull(): void
    {
        $conn = $this->getExecuteStatementMockConnection();

        $conn->expects(self::once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM TestTable WHERE id IS NULL AND name = ?',
                ['foo'],
                ['string'],
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
            ],
        );
    }

    #[DataProvider('fetchModeProvider')]
    public function testFetch(string $method, callable $invoke, mixed $expected): void
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

    /** @return iterable<string,array<int,mixed>> */
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

    public function testCallConnectOnce(): void
    {
        $driver = $this->createMock(Driver::class);
        $driver->expects(self::once())
            ->method('connect');

        $conn = new Connection([], $driver);
        $conn->executeQuery('SELECT 1');
        $conn->executeQuery('SELECT 2');
    }

    public function testPlatformDetectionTriggersConnectionIfRequiredByTheDriver(): void
    {
        $driverConnection = $this->createMock(DriverConnection::class);
        $driverConnection->expects(self::once())
            ->method('getServerVersion')
            ->willReturn('6.6.6');

        $platform = $this->createMock(AbstractPlatform::class);

        $driver = $this->createMock(Driver::class);
        $driver->expects(self::once())
            ->method('connect')
            ->willReturn($driverConnection);
        $driver->expects(self::once())
            ->method('getDatabasePlatform')
            ->willReturnCallback(static function (ServerVersionProvider $versionProvider) use ($platform) {
                self::assertSame('6.6.6', $versionProvider->getServerVersion());

                return $platform;
            });

        $connection = new Connection([], $driver);

        self::assertSame($platform, $connection->getDatabasePlatform());
    }

    public function testPlatformDetectionDoesNotTriggerConnectionIfNotRequiredByTheDriver(): void
    {
        $driverConnection = $this->createMock(DriverConnection::class);
        $driverConnection->expects(self::never())
            ->method('getServerVersion');

        $platform = $this->createMock(AbstractPlatform::class);

        $driver = $this->createMock(Driver::class);
        $driver->expects(self::never())
            ->method('connect');
        $driver->expects(self::once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection = new Connection([], $driver);

        self::assertSame($platform, $connection->getDatabasePlatform());
    }

    public function testPlatformDetectionFetchedFromParameters(): void
    {
        $driverMock = $this->createMock(Driver::class);

        $driverConnectionMock = $this->createMock(Driver\Connection::class);

        $platformMock = $this->createMock(AbstractPlatform::class);

        $connection = new Connection(['serverVersion' => '8.0'], $driverMock);

        $driverMock->expects(self::never())
            ->method('connect')
            ->willReturn($driverConnectionMock);

        $driverMock->expects(self::once())
            ->method('getDatabasePlatform')
            ->with(new Connection\StaticServerVersionProvider('8.0'))
            ->willReturn($platformMock);

        self::assertSame($platformMock, $connection->getDatabasePlatform());
    }

    public function testPlatformDetectionFetchedFromPrimaryReplicaParameters(): void
    {
        $driverMock = $this->createMock(Driver::class);

        $driverConnectionMock = $this->createMock(Driver\Connection::class);

        $platformMock = $this->createMock(AbstractPlatform::class);

        $connection = new Connection(['primary' => ['serverVersion' => '8.0']], $driverMock);

        $driverMock->expects(self::never())
            ->method('connect')
            ->willReturn($driverConnectionMock);

        $driverMock->expects(self::once())
            ->method('getDatabasePlatform')
            ->with(new Connection\StaticServerVersionProvider('8.0'))
            ->willReturn($platformMock);

        self::assertSame($platformMock, $connection->getDatabasePlatform());
    }

    public function testConnectionParamsArePassedToTheQueryCacheProfileInExecuteCacheQuery(): void
    {
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock->method('isHit')->willReturn(true);
        $cacheItemMock->method('get')->willReturn(['realKey' => []]);

        $resultCacheMock = $this->createMock(CacheItemPoolInterface::class);

        $resultCacheMock
            ->expects(self::atLeastOnce())
            ->method('getItem')
            ->with('cacheKey')
            ->willReturn($cacheItemMock);

        $query  = 'SELECT * FROM foo WHERE bar = ?';
        $params = [666];
        $types  = [ParameterType::INTEGER];

        $queryCacheProfileMock = $this->createMock(QueryCacheProfile::class);

        $queryCacheProfileMock
            ->method('getResultCache')
            ->willReturn($resultCacheMock);

        $expectedConnectionParams = self::CONNECTION_PARAMS;
        unset($expectedConnectionParams['password']);

        // This is our main expectation
        $queryCacheProfileMock
            ->expects(self::once())
            ->method('generateCacheKeys')
            ->with($query, $params, $types, $expectedConnectionParams)
            ->willReturn(['cacheKey', 'realKey']);

        $driver = $this->createMock(Driver::class);

        (new Connection(self::CONNECTION_PARAMS, $driver))
            ->executeCacheQuery($query, $params, $types, $queryCacheProfileMock);
    }

    public function testCustomSchemaManagerFactory(): void
    {
        $schemaManager = self::createStub(AbstractSchemaManager::class);
        $factory       = $this->createMock(SchemaManagerFactory::class);
        $factory->expects(self::once())->method('createSchemaManager')->willReturn($schemaManager);

        $configuration = new Configuration();
        $configuration->setSchemaManagerFactory($factory);

        $connection = DriverManager::getConnection(['driver' => 'sqlite3', 'memory' => true], $configuration);
        self::assertSame($schemaManager, $connection->createSchemaManager());
    }

    public function testDefaultSchemaManagerFactory(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'sqlite3', 'memory' => true]);
        self::assertInstanceOf(SQLiteSchemaManager::class, $connection->createSchemaManager());
    }
}
