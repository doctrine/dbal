<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use Error;
use PDO;
use Throwable;

use function file_exists;
use function unlink;

class ConnectionTest extends FunctionalTestCase
{
    private const TABLE = 'connection_test';

    protected function tearDown(): void
    {
        if (file_exists('/tmp/test_nesting.sqlite')) {
            unlink('/tmp/test_nesting.sqlite');
        }

        $this->markConnectionNotReusable();
    }

    public function testCommitWithRollbackOnlyThrowsException(): void
    {
        $this->connection->beginTransaction();
        $this->connection->setRollbackOnly();

        $this->expectException(ConnectionException::class);
        $this->connection->commit();
    }

    public function testTransactionNestingBehavior(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->createTestTable();

        $this->connection->beginTransaction();
        self::assertSame(1, $this->connection->getTransactionNestingLevel());

        try {
            $this->connection->beginTransaction();
            self::assertSame(2, $this->connection->getTransactionNestingLevel());

            $this->connection->insert(self::TABLE, ['id' => 1]);
            self::fail('Expected exception to be thrown because of the unique constraint.');
        } catch (Throwable $e) {
            self::assertInstanceOf(UniqueConstraintViolationException::class, $e);
            $this->connection->rollBack();
            self::assertSame(1, $this->connection->getTransactionNestingLevel());
        }

        $this->connection->commit();

        $this->connection->beginTransaction();
        $this->connection->close();
        $this->connection->beginTransaction();
        self::assertEquals(1, $this->connection->getTransactionNestingLevel());
    }

    public function testTransactionNestingLevelIsResetOnReconnect(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform to support savepoints.');
        }

        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $params           = $this->connection->getParams();
            $params['memory'] = false;
            $params['path']   = '/tmp/test_nesting.sqlite';

            $connection = DriverManager::getConnection(
                $params,
                $this->connection->getConfiguration(),
            );
        } else {
            $connection = $this->connection;
        }

        $this->dropTableIfExists('test_nesting');
        $connection->executeQuery('CREATE TABLE test_nesting(test int not null)');

        $this->connection->beginTransaction();
        $this->connection->beginTransaction();
        $connection->close(); // connection closed in runtime (for example if lost or another application logic)

        $connection->beginTransaction();
        $connection->executeQuery('insert into test_nesting values (33)');
        $connection->rollBack();

        self::assertEquals(0, $connection->fetchOne('select count(*) from test_nesting'));
    }

    public function testTransactionNestingBehaviorWithSavepoints(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->createTestTable();

        try {
            $this->connection->beginTransaction();
            self::assertSame(1, $this->connection->getTransactionNestingLevel());

            try {
                $this->connection->beginTransaction();
                self::assertSame(2, $this->connection->getTransactionNestingLevel());
                $this->connection->beginTransaction();
                self::assertSame(3, $this->connection->getTransactionNestingLevel());
                $this->connection->commit();
                self::assertSame(2, $this->connection->getTransactionNestingLevel());

                $this->connection->insert(self::TABLE, ['id' => 1]);
                self::fail('Expected exception to be thrown because of the unique constraint.');
            } catch (Throwable $e) {
                self::assertInstanceOf(UniqueConstraintViolationException::class, $e);
                $this->connection->rollBack();
                self::assertSame(1, $this->connection->getTransactionNestingLevel());
            }

            self::assertFalse($this->connection->isRollbackOnly());

            $this->connection->commit(); // should not throw exception
        } catch (ConnectionException) {
            self::fail('Transaction commit after failed nested transaction should not fail when using savepoints.');
        }
    }

    public function testTransactionIsInactiveAfterConnectionClose(): void
    {
        $this->connection->beginTransaction();
        $this->connection->close();

        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testCreateSavepointsNotSupportedThrowsException(): void
    {
        if ($this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Savepoints are not supported by this driver.');

        $this->connection->createSavepoint('foo');
    }

    public function testReleaseSavepointsNotSupportedThrowsException(): void
    {
        if ($this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Savepoints are not supported by this driver.');

        $this->connection->releaseSavepoint('foo');
    }

    public function testRollbackSavepointsNotSupportedThrowsException(): void
    {
        if ($this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Savepoints are not supported by this driver.');

        $this->connection->rollbackSavepoint('foo');
    }

    public function testTransactionBehaviorWithRollback(): void
    {
        $this->createTestTable();

        try {
            $this->connection->beginTransaction();
            self::assertSame(1, $this->connection->getTransactionNestingLevel());

            $this->connection->insert(self::TABLE, ['id' => 1]);
            self::fail('Expected exception to be thrown because of the unique constraint.');
        } catch (Throwable $e) {
            self::assertInstanceOf(UniqueConstraintViolationException::class, $e);
            self::assertSame(1, $this->connection->getTransactionNestingLevel());
            $this->connection->rollBack();
            self::assertSame(0, $this->connection->getTransactionNestingLevel());
        }
    }

    public function testTransactionBehaviour(): void
    {
        $this->createTestTable();

        $this->connection->beginTransaction();
        self::assertSame(1, $this->connection->getTransactionNestingLevel());
        $this->connection->insert(self::TABLE, ['id' => 2]);
        $this->connection->commit();
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
    }

    public function testTransactionalWithException(): void
    {
        $this->createTestTable();

        try {
            $this->connection->transactional(static function (Connection $connection): void {
                $connection->insert(self::TABLE, ['id' => 1]);
            });
            self::fail('Expected exception to be thrown because of the unique constraint.');
        } catch (Throwable $e) {
            self::assertInstanceOf(UniqueConstraintViolationException::class, $e);
            self::assertSame(0, $this->connection->getTransactionNestingLevel());
        }
    }

    public function testTransactionalWithThrowable(): void
    {
        try {
            $this->connection->transactional(static function (Connection $conn): void {
                $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());

                throw new Error('Ooops!');
            });
        } catch (Error) {
        }

        self::assertEquals(0, $this->connection->getTransactionNestingLevel());
    }

    public function testTransactional(): void
    {
        $this->createTestTable();

        $res = $this->connection->transactional(static function (Connection $connection) {
            return $connection->insert(self::TABLE, ['id' => 2]);
        });

        self::assertSame(1, $res);
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
    }

    public function testTransactionalReturnValue(): void
    {
        $res = $this->connection->transactional(static function (): int {
            return 42;
        });

        self::assertEquals(42, $res);
    }

    public function testConnectWithoutExplicitDatabaseName(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof OraclePlatform || $platform instanceof DB2Platform) {
            self::markTestSkipped('Platform does not support connecting without database name.');
        }

        $params = $this->connection->getParams();
        unset($params['dbname']);

        $connection = DriverManager::getConnection(
            $params,
            $this->connection->getConfiguration(),
        );

        self::assertEquals(1, $connection->fetchOne(
            $platform->getDummySelectSQL(),
        ));
    }

    public function testDeterminesDatabasePlatformWhenConnectingToNonExistentDatabase(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof OraclePlatform || $platform instanceof DB2Platform) {
            self::markTestSkipped('Platform does not support connecting without database name.');
        }

        $params = $this->connection->getParams();

        $params['dbname'] = 'foo_bar';

        $connection = DriverManager::getConnection(
            $params,
            $this->connection->getConfiguration(),
        );

        self::assertFalse($connection->isConnected());
        self::assertSame($params, $connection->getParams());

        $connection->close();
    }

    public function testPersistentConnection(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (
            $platform instanceof SQLitePlatform
            || $platform instanceof SQLServerPlatform
        ) {
            self::markTestSkipped('The platform does not support persistent connections');
        }

        $params               = TestUtil::getConnectionParams();
        $params['persistent'] = true;

        $connection       = DriverManager::getConnection($params);
        $nativeConnection = $connection->getNativeConnection();

        if (! $nativeConnection instanceof PDO) {
            self::markTestSkipped('Unable to test if the connection is persistent');
        }

        self::assertTrue($nativeConnection->getAttribute(PDO::ATTR_PERSISTENT));
    }

    public function testExceptionOnExecuteStatement(): void
    {
        $this->expectException(DriverException::class);

        $this->connection->executeStatement('foo');
    }

    public function testExceptionOnExecuteQuery(): void
    {
        $this->expectException(DriverException::class);

        $this->connection->executeQuery('foo');
    }

    /**
     * Some drivers do not check the query server-side even though emulated prepared statements are disabled,
     * so an exception is thrown only eventually.
     */
    public function testExceptionOnPrepareAndExecute(): void
    {
        $this->expectException(DriverException::class);

        $this->connection->prepare('foo')->executeStatement();
    }

    private function createTestTable(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $this->connection->insert(self::TABLE, ['id' => 1]);
    }
}
