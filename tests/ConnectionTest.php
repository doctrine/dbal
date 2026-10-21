<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaManagerFactory;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;
use Doctrine\DBAL\ServerVersionProvider;
use Doctrine\DBAL\Tests\Types\CaseConvertingStringType;
use Doctrine\DBAL\Types\PlatformAwareBindingType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

/**
 * @phpstan-import-type Params from DriverManager
 * @phpstan-import-type WrapperParameterTypeArray from Connection
 */
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
        $driver = self::createStub(Driver::class);

        return $this->getMockBuilder(Connection::class)
            ->onlyMethods(['executeStatement'])
            ->setConstructorArgs([[], $driver])
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
        $driver = self::createStub(Driver::class);

        $conn = new Connection([], $driver);

        $conn->setAutoCommit(false);

        self::assertFalse($conn->isTransactionActive());

        $conn->executeQuery('SELECT 1');

        self::assertTrue($conn->isTransactionActive());
    }

    public function testTransactionIsNotActiveAfterTransactionalInAutoCommitMode(): void
    {
        $driverMock = self::createStub(Driver::class);
        $driverMock
            ->method('connect')
            ->willReturn(
                self::createStub(DriverConnection::class),
            );

        $conn = new Connection([], $driverMock);

        $conn->setAutoCommit(true);

        self::assertFalse($conn->isTransactionActive());

        $conn->transactional(static function (Connection $connection): void {
        });

        self::assertFalse($conn->isTransactionActive());
    }

    public function testTransactionIsActiveAfterTransactionalInNoAutoCommitMode(): void
    {
        $platformMock = self::createStub(AbstractPlatform::class);
        $platformMock
            ->method('supportsSavepoints')
            ->willReturn(true);

        $driverMock = self::createStub(Driver::class);
        $driverMock
            ->method('connect')
            ->willReturn(
                self::createStub(DriverConnection::class),
            );

        $driverMock
            ->method('getDatabasePlatform')
            ->willReturn(
                $platformMock,
            );

        $conn = new Connection([], $driverMock);

        $conn->setAutoCommit(false);

        $conn->transactional(static function (Connection $connection): void {
        });

        self::assertTrue($conn->isTransactionActive());
    }

    public function testCommitStartsTransactionInNoAutoCommitMode(): void
    {
        $driver = self::createStub(Driver::class);

        $conn = new Connection([], $driver);

        $conn->setAutoCommit(false);
        $conn->executeQuery('SELECT 1');
        $conn->commit();

        self::assertTrue($conn->isTransactionActive());
    }

    public function testBeginTransactionFailureAfterCommitInNoAutoCommitMode(): void
    {
        $driverConnectionMock = $this->createMock(DriverConnection::class);
        $driverConnectionMock->expects(self::exactly(2))
            ->method('beginTransaction')
            ->willReturnOnConsecutiveCalls(
                true,
                self::throwException(new RuntimeException()),
            );

        $driver = self::createStub(Driver::class);
        $driver
            ->method('connect')
            ->willReturn(
                $driverConnectionMock,
            );
        $conn = new Connection([], $driver);

        $conn->setAutoCommit(false);

        /** Invoke protected {@see Connection::connect()} */
        $conn->getServerVersion();
        try {
            $conn->commit();
        } catch (RuntimeException) {
        }

        self::assertTrue($conn->isTransactionActive());
    }

    /** @return bool[][] */
    public static function resultProvider(): array
    {
        return [[true], [false]];
    }

    public function testRollBackStartsTransactionInNoAutoCommitMode(): void
    {
        $driver = self::createStub(Driver::class);

        $conn = new Connection([], $driver);

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

        $driver = self::createStub(Driver::class);
        $driver
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $conn = new Connection([], $driver);

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

    /** @param array<int<0, max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types */
    #[DataProvider('insertTypesProvider')]
    public function testInsertAppliesSQLConversionOfTypes(array $types, string $expectedSQL): void
    {
        $conn = $this->getExecuteStatementMockConnectionWithConvertingType();

        $conn->expects(self::once())
            ->method('executeStatement')
            ->with($expectedSQL, [1, 'Foo']);

        $conn->insert('TestTable', ['id' => 1, 'name' => 'Foo'], $types);
    }

    /** @return iterable<string, array{array<int|string, string|ParameterType|Type>, string}> */
    public static function insertTypesProvider(): iterable
    {
        yield 'named type names' => [
            ['id' => Types::INTEGER, 'name' => CaseConvertingStringType::NAME],
            'INSERT INTO TestTable (id, name) VALUES (?, LOWER(?))',
        ];

        yield 'positional type names' => [
            [Types::INTEGER, CaseConvertingStringType::NAME],
            'INSERT INTO TestTable (id, name) VALUES (?, LOWER(?))',
        ];

        yield 'sparse positional type' => [
            [1 => CaseConvertingStringType::NAME],
            'INSERT INTO TestTable (id, name) VALUES (?, LOWER(?))',
        ];

        yield 'type instance' => [
            ['name' => new CaseConvertingStringType()],
            'INSERT INTO TestTable (id, name) VALUES (?, LOWER(?))',
        ];

        yield 'binding types' => [
            ['id' => ParameterType::INTEGER, 'name' => ParameterType::STRING],
            'INSERT INTO TestTable (id, name) VALUES (?, ?)',
        ];

        yield 'no types' => [
            [],
            'INSERT INTO TestTable (id, name) VALUES (?, ?)',
        ];
    }

    public function testUpdateAppliesSQLConversionOfTypes(): void
    {
        $conn = $this->getExecuteStatementMockConnectionWithConvertingType();

        $conn->expects(self::once())
            ->method('executeStatement')
            ->with(
                'UPDATE TestTable SET name = LOWER(?), id = ? WHERE deleted IS NULL AND code = LOWER(?)',
                ['Foo', 1, 'Bar'],
                [CaseConvertingStringType::NAME, Types::INTEGER, CaseConvertingStringType::NAME],
            );

        $conn->update(
            'TestTable',
            ['name' => 'Foo', 'id' => 1],
            ['deleted' => null, 'code' => 'Bar'],
            [
                'name' => CaseConvertingStringType::NAME,
                'id' => Types::INTEGER,
                'code' => CaseConvertingStringType::NAME,
            ],
        );
    }

    public function testUpdateAppliesSQLConversionOfPositionalTypes(): void
    {
        $conn = $this->getExecuteStatementMockConnectionWithConvertingType();

        $conn->expects(self::once())
            ->method('executeStatement')
            ->with(
                'UPDATE TestTable SET name = ? WHERE code = LOWER(?)',
                ['Foo', 'Bar'],
                [1 => CaseConvertingStringType::NAME],
            );

        $conn->update('TestTable', ['name' => 'Foo'], ['code' => 'Bar'], [1 => CaseConvertingStringType::NAME]);
    }

    public function testDeleteAppliesSQLConversionOfTypes(): void
    {
        $conn = $this->getExecuteStatementMockConnectionWithConvertingType();

        $conn->expects(self::once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM TestTable WHERE deleted IS NULL AND code = LOWER(?)',
                ['Bar'],
                [CaseConvertingStringType::NAME],
            );

        $conn->delete(
            'TestTable',
            ['deleted' => null, 'code' => 'Bar'],
            ['deleted' => Types::BOOLEAN, 'code' => CaseConvertingStringType::NAME],
        );
    }

    public function testConvertToDatabaseValueSQL(): void
    {
        $conn = $this->getExecuteStatementMockConnectionWithConvertingType();

        self::assertSame('LOWER(:name)', $conn->convertToDatabaseValueSQL(':name', CaseConvertingStringType::NAME));
        self::assertSame('LOWER(?)', $conn->convertToDatabaseValueSQL('?', new CaseConvertingStringType()));
        self::assertSame('?', $conn->convertToDatabaseValueSQL('?', Types::STRING));
    }

    public function testConvertToPHPValueSQL(): void
    {
        $conn = $this->getExecuteStatementMockConnectionWithConvertingType();

        self::assertSame('UPPER(t.name)', $conn->convertToPHPValueSQL('t.name', CaseConvertingStringType::NAME));
        self::assertSame('UPPER(t.name)', $conn->convertToPHPValueSQL('t.name', new CaseConvertingStringType()));
        self::assertSame('t.name', $conn->convertToPHPValueSQL('t.name', Types::STRING));
    }

    public function testPlatformAwareBindingType(): void
    {
        $platform = self::createStub(AbstractPlatform::class);

        $type = new class ($platform) extends Type implements PlatformAwareBindingType {
            public function __construct(private readonly AbstractPlatform $expectedPlatform)
            {
            }

            /** {@inheritDoc} */
            public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
            {
                return 'BLOB';
            }

            public function getBindingTypeForPlatform(AbstractPlatform $platform): ParameterType
            {
                return $platform === $this->expectedPlatform ? ParameterType::BINARY : ParameterType::INTEGER;
            }
        };

        $driverStatement = $this->createMock(DriverStatement::class);
        $driverStatement->expects(self::exactly(2))
            ->method('bindValue')
            ->with(1, 'value', ParameterType::BINARY);
        $driverStatement->method('execute')
            ->willReturn(self::createStub(DriverResult::class));

        $driverConnection = self::createStub(DriverConnection::class);
        $driverConnection->method('prepare')
            ->willReturn($driverStatement);

        $driver = self::createStub(Driver::class);
        $driver->method('connect')
            ->willReturn($driverConnection);
        $driver->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection = new Connection([], $driver);
        $connection->executeStatement('INSERT INTO t (c) VALUES (?)', ['value'], [$type]);
        $connection->prepare('INSERT INTO t (c) VALUES (?)')->bindValue(1, 'value', $type);
    }

    private function getExecuteStatementMockConnectionWithConvertingType(): Connection&MockObject
    {
        $configuration = (new Configuration())->setTypeProvider(new TypeRegistry([
            CaseConvertingStringType::NAME => new CaseConvertingStringType(),
        ]));

        return $this->getMockBuilder(Connection::class)
            ->onlyMethods(['executeStatement'])
            ->setConstructorArgs([[], self::createStub(Driver::class), $configuration])
            ->getMock();
    }

    /** @param non-empty-string $method */
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

        $driver = self::createConfiguredStub(Driver::class, [
            'connect' => self::createStub(DriverConnection::class),
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

    /** @return iterable<string, array{
     *     non-empty-string,
     *     callable(Connection, string, list<mixed>|array<string, mixed>, WrapperParameterTypeArray): mixed,
     *     mixed,
     * }> */
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

        $platform = self::createStub(AbstractPlatform::class);

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

        $platform = self::createStub(AbstractPlatform::class);

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

        $driverConnectionMock = self::createStub(Driver\Connection::class);

        $platformMock = self::createStub(AbstractPlatform::class);

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

        $driverConnectionMock = self::createStub(Driver\Connection::class);

        $platformMock = self::createStub(AbstractPlatform::class);

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
        $cacheItemMock = self::createStub(CacheItemInterface::class);
        $cacheItemMock->method('isHit')->willReturn(true);
        $cacheItemMock->method('get')->willReturn(['realKey' => [[], []]]);

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

        $driver = self::createStub(Driver::class);

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

    public function testItPreservesTheOriginalExceptionOnRollbackFailure(): void
    {
        $connection = new class (['memory' => true], new Driver\SQLite3\Driver()) extends Connection {
            public function rollBack(): void
            {
                throw new ConnectionException('Rollback exception');
            }
        };

        try {
            $connection->transactional(static function (): void {
                throw new ConnectionException('Original exception');
            });
            self::fail('Exception expected'); // @phpstan-ignore deadCode.unreachable
        } catch (ConnectionException $e) {
            self::assertSame('Rollback exception', $e->getMessage());
            self::assertNotNull($e->getPrevious());
            self::assertSame('Original exception', $e->getPrevious()->getMessage());
        }
    }

    /**
     * We are not sure if this can happen in real life scenario
     */
    public function testItFailsDuringCommitBeforeTouchingDb(): void
    {
        $connection = new class (['memory' => true], new Driver\SQLite3\Driver()) extends Connection {
            public function commit(): void
            {
                throw new \Exception('Fail before touching the db');
            }

            public function rollBack(): void
            {
                throw new \Exception('Rollback got triggered');
            }
        };

        $this->expectExceptionMessage('Rollback got triggered');
        $connection->transactional(static function (): void {
        });
    }
}
