<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Error;
use Exception;
use PDO;
use RuntimeException;
use Throwable;

use function file_exists;
use function in_array;
use function unlink;

class ConnectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $this->resetSharedConn();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (file_exists('/tmp/test_nesting.sqlite')) {
            unlink('/tmp/test_nesting.sqlite');
        }

        parent::tearDown();
        $this->resetSharedConn();
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
        try {
            $this->connection->beginTransaction();
            self::assertEquals(1, $this->connection->getTransactionNestingLevel());

            try {
                $this->connection->beginTransaction();
                self::assertEquals(2, $this->connection->getTransactionNestingLevel());

                throw new Exception();
            } catch (Throwable $e) {
                $this->connection->rollBack();
                self::assertEquals(1, $this->connection->getTransactionNestingLevel());
                //no rethrow
            }

            self::assertTrue($this->connection->isRollbackOnly());

            $this->connection->commit(); // should throw exception
            self::fail('Transaction commit after failed nested transaction should fail.');
        } catch (ConnectionException $e) {
            self::assertEquals(1, $this->connection->getTransactionNestingLevel());
            $this->connection->rollBack();
            self::assertEquals(0, $this->connection->getTransactionNestingLevel());
        }

        $this->connection->beginTransaction();
        $this->connection->close();
        $this->connection->beginTransaction();
        self::assertEquals(1, $this->connection->getTransactionNestingLevel());
    }

    public function testTransactionNestingLevelIsResetOnReconnect(): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $params           = $this->connection->getParams();
            $params['memory'] = false;
            $params['path']   = '/tmp/test_nesting.sqlite';

            $connection = DriverManager::getConnection(
                $params,
                $this->connection->getConfiguration(),
                $this->connection->getEventManager()
            );
        } else {
            $connection = $this->connection;
        }

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

        $this->connection->setNestTransactionsWithSavepoints(true);
        try {
            $this->connection->beginTransaction();
            self::assertEquals(1, $this->connection->getTransactionNestingLevel());

            try {
                $this->connection->beginTransaction();
                self::assertEquals(2, $this->connection->getTransactionNestingLevel());
                $this->connection->beginTransaction();
                self::assertEquals(3, $this->connection->getTransactionNestingLevel());
                $this->connection->commit();
                self::assertEquals(2, $this->connection->getTransactionNestingLevel());

                throw new Exception();
            } catch (Throwable $e) {
                $this->connection->rollBack();
                self::assertEquals(1, $this->connection->getTransactionNestingLevel());
                //no rethrow
            }

            self::assertFalse($this->connection->isRollbackOnly());
            try {
                $this->connection->setNestTransactionsWithSavepoints(false);
                self::fail('Should not be able to disable savepoints in usage for nested transactions inside an open transaction.');
            } catch (ConnectionException $e) {
                self::assertTrue($this->connection->getNestTransactionsWithSavepoints());
            }

            $this->connection->commit(); // should not throw exception
        } catch (ConnectionException $e) {
            self::fail('Transaction commit after failed nested transaction should not fail when using savepoints.');
        }
    }

    public function testTransactionNestingBehaviorCantBeChangedInActiveTransaction(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->connection->beginTransaction();
        $this->expectException(ConnectionException::class);
        $this->connection->setNestTransactionsWithSavepoints(true);
    }

    public function testSetNestedTransactionsThroughSavepointsNotSupportedThrowsException(): void
    {
        if ($this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Savepoints are not supported by this driver.');

        $this->connection->setNestTransactionsWithSavepoints(true);
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
        try {
            $this->connection->beginTransaction();
            self::assertEquals(1, $this->connection->getTransactionNestingLevel());

            throw new Exception();
        } catch (Throwable $e) {
            self::assertEquals(1, $this->connection->getTransactionNestingLevel());
            $this->connection->rollBack();
            self::assertEquals(0, $this->connection->getTransactionNestingLevel());
        }
    }

    public function testTransactionBehaviour(): void
    {
        try {
            $this->connection->beginTransaction();
            self::assertEquals(1, $this->connection->getTransactionNestingLevel());
            $this->connection->commit();
        } catch (Throwable $e) {
            $this->connection->rollBack();
            self::assertEquals(0, $this->connection->getTransactionNestingLevel());
        }

        self::assertEquals(0, $this->connection->getTransactionNestingLevel());
    }

    public function testTransactionalWithException(): void
    {
        try {
            $this->connection->transactional(static function ($conn): void {
                /** @var Connection $conn */
                $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());

                throw new RuntimeException('Ooops!');
            });
            self::fail('Expected exception');
        } catch (RuntimeException $expected) {
            self::assertEquals(0, $this->connection->getTransactionNestingLevel());
        }
    }

    public function testTransactionalWithThrowable(): void
    {
        try {
            $this->connection->transactional(static function ($conn): void {
                /** @var Connection $conn */
                $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());

                throw new Error('Ooops!');
            });
            self::fail('Expected exception');
        } catch (Error $expected) {
            self::assertEquals(0, $this->connection->getTransactionNestingLevel());
        }
    }

    public function testTransactional(): void
    {
        $res = $this->connection->transactional(static function ($conn): void {
            /** @var Connection $conn */
            $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());
        });

        self::assertNull($res);
    }

    public function testTransactionalReturnValue(): void
    {
        $res = $this->connection->transactional(static function (): int {
            return 42;
        });

        self::assertEquals(42, $res);
    }

    public function testPingDoesTriggersConnect(): void
    {
        $this->connection->close();
        self::assertFalse($this->connection->isConnected());

        $this->connection->ping();
        self::assertTrue($this->connection->isConnected());
    }

    /**
     * @group DBAL-1025
     */
    public function testConnectWithoutExplicitDatabaseName(): void
    {
        if (in_array($this->connection->getDatabasePlatform()->getName(), ['oracle', 'db2'], true)) {
            self::markTestSkipped('Platform does not support connecting without database name.');
        }

        $params = $this->connection->getParams();
        unset($params['dbname']);

        $connection = DriverManager::getConnection(
            $params,
            $this->connection->getConfiguration(),
            $this->connection->getEventManager()
        );

        $connection->connect();

        self::assertTrue($connection->isConnected());

        $connection->close();
    }

    /**
     * @group DBAL-990
     */
    public function testDeterminesDatabasePlatformWhenConnectingToNonExistentDatabase(): void
    {
        if (in_array($this->connection->getDatabasePlatform()->getName(), ['oracle', 'db2'], true)) {
            self::markTestSkipped('Platform does not support connecting without database name.');
        }

        $params = $this->connection->getParams();

        $params['dbname'] = 'foo_bar';

        $connection = DriverManager::getConnection(
            $params,
            $this->connection->getConfiguration(),
            $this->connection->getEventManager()
        );

        self::assertFalse($connection->isConnected());
        self::assertSame($params, $connection->getParams());

        $connection->close();
    }

    public function testPersistentConnection(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (
            $platform instanceof SqlitePlatform
            || $platform instanceof SQLServer2012Platform
        ) {
            self::markTestSkipped('The platform does not support persistent connections');
        }

        $params               = TestUtil::getConnectionParams();
        $params['persistent'] = true;

        $connection       = DriverManager::getConnection($params);
        $driverConnection = $connection->getWrappedConnection();

        if (! $driverConnection instanceof PDOConnection) {
            self::markTestSkipped('Unable to test if the connection is persistent');
        }

        $pdo = $driverConnection->getWrappedConnection();

        self::assertTrue($pdo->getAttribute(PDO::ATTR_PERSISTENT));
    }
}
