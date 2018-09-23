<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalFunctionalTestCase;
use Error;
use Exception;
use RuntimeException;
use Throwable;
use function in_array;

class ConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        $this->resetSharedConn();
        parent::setUp();
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->resetSharedConn();
    }

    public function testGetWrappedConnection()
    {
        self::assertInstanceOf(DriverConnection::class, $this->connection->getWrappedConnection());
    }

    public function testCommitWithRollbackOnlyThrowsException()
    {
        $this->connection->beginTransaction();
        $this->connection->setRollbackOnly();

        $this->expectException(ConnectionException::class);
        $this->connection->commit();
    }

    public function testTransactionNestingBehavior()
    {
        try {
            $this->connection->beginTransaction();
            self::assertEquals(1, $this->connection->getTransactionNestingLevel());

            try {
                $this->connection->beginTransaction();
                self::assertEquals(2, $this->connection->getTransactionNestingLevel());
                throw new Exception();
                $this->connection->commit(); // never reached
            } catch (Throwable $e) {
                $this->connection->rollBack();
                self::assertEquals(1, $this->connection->getTransactionNestingLevel());
                //no rethrow
            }
            self::assertTrue($this->connection->isRollbackOnly());

            $this->connection->commit(); // should throw exception
            $this->fail('Transaction commit after failed nested transaction should fail.');
        } catch (ConnectionException $e) {
            self::assertEquals(1, $this->connection->getTransactionNestingLevel());
            $this->connection->rollBack();
            self::assertEquals(0, $this->connection->getTransactionNestingLevel());
        }
    }

    public function testTransactionNestingBehaviorWithSavepoints()
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform to support savepoints.');
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
                $this->connection->commit(); // never reached
            } catch (Throwable $e) {
                $this->connection->rollBack();
                self::assertEquals(1, $this->connection->getTransactionNestingLevel());
                //no rethrow
            }
            self::assertFalse($this->connection->isRollbackOnly());
            try {
                $this->connection->setNestTransactionsWithSavepoints(false);
                $this->fail('Should not be able to disable savepoints in usage for nested transactions inside an open transaction.');
            } catch (ConnectionException $e) {
                self::assertTrue($this->connection->getNestTransactionsWithSavepoints());
            }
            $this->connection->commit(); // should not throw exception
        } catch (ConnectionException $e) {
            $this->fail('Transaction commit after failed nested transaction should not fail when using savepoints.');
            $this->connection->rollBack();
        }
    }

    public function testTransactionNestingBehaviorCantBeChangedInActiveTransaction()
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->connection->beginTransaction();
        $this->expectException(ConnectionException::class);
        $this->connection->setNestTransactionsWithSavepoints(true);
    }

    public function testSetNestedTransactionsThroughSavepointsNotSupportedThrowsException()
    {
        if ($this->connection->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Savepoints are not supported by this driver.');

        $this->connection->setNestTransactionsWithSavepoints(true);
    }

    public function testCreateSavepointsNotSupportedThrowsException()
    {
        if ($this->connection->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Savepoints are not supported by this driver.');

        $this->connection->createSavepoint('foo');
    }

    public function testReleaseSavepointsNotSupportedThrowsException()
    {
        if ($this->connection->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Savepoints are not supported by this driver.');

        $this->connection->releaseSavepoint('foo');
    }

    public function testRollbackSavepointsNotSupportedThrowsException()
    {
        if ($this->connection->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Savepoints are not supported by this driver.');

        $this->connection->rollbackSavepoint('foo');
    }

    public function testTransactionBehaviorWithRollback()
    {
        try {
            $this->connection->beginTransaction();
            self::assertEquals(1, $this->connection->getTransactionNestingLevel());

            throw new Exception();

            $this->connection->commit(); // never reached
        } catch (Throwable $e) {
            self::assertEquals(1, $this->connection->getTransactionNestingLevel());
            $this->connection->rollBack();
            self::assertEquals(0, $this->connection->getTransactionNestingLevel());
        }
    }

    public function testTransactionBehaviour()
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

    public function testTransactionalWithException()
    {
        try {
            $this->connection->transactional(static function ($conn) {
                /** @var Connection $conn */
                $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());
                throw new RuntimeException('Ooops!');
            });
            $this->fail('Expected exception');
        } catch (RuntimeException $expected) {
            self::assertEquals(0, $this->connection->getTransactionNestingLevel());
        }
    }

    public function testTransactionalWithThrowable()
    {
        try {
            $this->connection->transactional(static function ($conn) {
                /** @var Connection $conn */
                $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());
                throw new Error('Ooops!');
            });
            $this->fail('Expected exception');
        } catch (Error $expected) {
            self::assertEquals(0, $this->connection->getTransactionNestingLevel());
        }
    }

    public function testTransactional()
    {
        $res = $this->connection->transactional(static function ($conn) {
            /** @var Connection $conn */
            $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());
        });

        self::assertNull($res);
    }

    public function testTransactionalReturnValue()
    {
        $res = $this->connection->transactional(static function () {
            return 42;
        });

        self::assertEquals(42, $res);
    }

    /**
     * Tests that the quote function accepts DBAL and PDO types.
     */
    public function testQuote()
    {
        self::assertEquals(
            $this->connection->quote('foo', Type::STRING),
            $this->connection->quote('foo', ParameterType::STRING)
        );
    }

    public function testPingDoesTriggersConnect()
    {
        self::assertTrue($this->connection->ping());
        self::assertTrue($this->connection->isConnected());
    }

    /**
     * @group DBAL-1025
     */
    public function testConnectWithoutExplicitDatabaseName()
    {
        if (in_array($this->connection->getDatabasePlatform()->getName(), ['oracle', 'db2'], true)) {
            $this->markTestSkipped('Platform does not support connecting without database name.');
        }

        $params = $this->connection->getParams();
        unset($params['dbname']);

        $connection = DriverManager::getConnection(
            $params,
            $this->connection->getConfiguration(),
            $this->connection->getEventManager()
        );

        self::assertTrue($connection->connect());

        $connection->close();
    }

    /**
     * @group DBAL-990
     */
    public function testDeterminesDatabasePlatformWhenConnectingToNonExistentDatabase()
    {
        if (in_array($this->connection->getDatabasePlatform()->getName(), ['oracle', 'db2'], true)) {
            $this->markTestSkipped('Platform does not support connecting without database name.');
        }

        $params = $this->connection->getParams();

        $params['dbname'] = 'foo_bar';

        $connection = DriverManager::getConnection(
            $params,
            $this->connection->getConfiguration(),
            $this->connection->getEventManager()
        );

        self::assertInstanceOf(AbstractPlatform::class, $connection->getDatabasePlatform());
        self::assertFalse($connection->isConnected());
        self::assertSame($params, $connection->getParams());

        $connection->close();
    }
}
