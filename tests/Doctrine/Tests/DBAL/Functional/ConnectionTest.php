<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class ConnectionTest extends \Doctrine\Tests\DbalFunctionalTestCase
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
        self::assertInstanceOf('Doctrine\DBAL\Driver\Connection', $this->conn->getWrappedConnection());
    }

    public function testCommitWithRollbackOnlyThrowsException()
    {
        $this->conn->beginTransaction();
        $this->conn->setRollbackOnly();

        $this->expectException(ConnectionException::class);
        $this->conn->commit();
    }

    public function testTransactionNestingBehavior()
    {
        try {
            $this->conn->beginTransaction();
            self::assertEquals(1, $this->conn->getTransactionNestingLevel());

            try {
                $this->conn->beginTransaction();
                self::assertEquals(2, $this->conn->getTransactionNestingLevel());
                throw new \Exception;
                $this->conn->commit(); // never reached
            } catch (\Exception $e) {
                $this->conn->rollBack();
                self::assertEquals(1, $this->conn->getTransactionNestingLevel());
                //no rethrow
            }
            self::assertTrue($this->conn->isRollbackOnly());

            $this->conn->commit(); // should throw exception
            $this->fail('Transaction commit after failed nested transaction should fail.');
        } catch (ConnectionException $e) {
            self::assertEquals(1, $this->conn->getTransactionNestingLevel());
            $this->conn->rollBack();
            self::assertEquals(0, $this->conn->getTransactionNestingLevel());
        }
    }

    public function testTransactionNestingBehaviorWithSavepoints()
    {
        if (!$this->conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->conn->setNestTransactionsWithSavepoints(true);
        try {
            $this->conn->beginTransaction();
            self::assertEquals(1, $this->conn->getTransactionNestingLevel());

            try {
                $this->conn->beginTransaction();
                self::assertEquals(2, $this->conn->getTransactionNestingLevel());
                $this->conn->beginTransaction();
                self::assertEquals(3, $this->conn->getTransactionNestingLevel());
                $this->conn->commit();
                self::assertEquals(2, $this->conn->getTransactionNestingLevel());
                throw new \Exception;
                $this->conn->commit(); // never reached
            } catch (\Exception $e) {
                $this->conn->rollBack();
                self::assertEquals(1, $this->conn->getTransactionNestingLevel());
                //no rethrow
            }
            self::assertFalse($this->conn->isRollbackOnly());
            try {
                $this->conn->setNestTransactionsWithSavepoints(false);
                $this->fail('Should not be able to disable savepoints in usage for nested transactions inside an open transaction.');
            } catch (ConnectionException $e) {
                self::assertTrue($this->conn->getNestTransactionsWithSavepoints());
            }
            $this->conn->commit(); // should not throw exception
        } catch (ConnectionException $e) {
            $this->fail('Transaction commit after failed nested transaction should not fail when using savepoints.');
            $this->conn->rollBack();
        }
    }

    public function testTransactionNestingBehaviorCantBeChangedInActiveTransaction()
    {
        if (!$this->conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->conn->beginTransaction();
        $this->expectException(ConnectionException::class);
        $this->conn->setNestTransactionsWithSavepoints(true);
    }

    public function testSetNestedTransactionsThroughSavepointsNotSupportedThrowsException()
    {
        if ($this->conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage("Savepoints are not supported by this driver.");

        $this->conn->setNestTransactionsWithSavepoints(true);
    }

    public function testCreateSavepointsNotSupportedThrowsException()
    {
        if ($this->conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage("Savepoints are not supported by this driver.");

        $this->conn->createSavepoint('foo');
    }

    public function testReleaseSavepointsNotSupportedThrowsException()
    {
        if ($this->conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage("Savepoints are not supported by this driver.");

        $this->conn->releaseSavepoint('foo');
    }

    public function testRollbackSavepointsNotSupportedThrowsException()
    {
        if ($this->conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage("Savepoints are not supported by this driver.");

        $this->conn->rollbackSavepoint('foo');
    }

    public function testTransactionBehaviorWithRollback()
    {
        try {
            $this->conn->beginTransaction();
            self::assertEquals(1, $this->conn->getTransactionNestingLevel());

            throw new \Exception;

            $this->conn->commit(); // never reached
        } catch (\Exception $e) {
            self::assertEquals(1, $this->conn->getTransactionNestingLevel());
            $this->conn->rollBack();
            self::assertEquals(0, $this->conn->getTransactionNestingLevel());
        }
    }

    public function testTransactionBehaviour()
    {
        try {
            $this->conn->beginTransaction();
            self::assertEquals(1, $this->conn->getTransactionNestingLevel());
            $this->conn->commit();
        } catch (\Exception $e) {
            $this->conn->rollBack();
            self::assertEquals(0, $this->conn->getTransactionNestingLevel());
        }

        self::assertEquals(0, $this->conn->getTransactionNestingLevel());
    }

    public function testTransactionalWithException()
    {
        try {
            $this->conn->transactional(function($conn) {
                /* @var $conn \Doctrine\DBAL\Connection */
                $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());
                throw new \RuntimeException("Ooops!");
            });
            $this->fail('Expected exception');
        } catch (\RuntimeException $expected) {
            self::assertEquals(0, $this->conn->getTransactionNestingLevel());
        }
    }

    public function testTransactionalWithThrowable()
    {
        try {
            $this->conn->transactional(function($conn) {
                /* @var $conn \Doctrine\DBAL\Connection */
                $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());
                throw new \Error("Ooops!");
            });
            $this->fail('Expected exception');
        } catch (\Error $expected) {
            self::assertEquals(0, $this->conn->getTransactionNestingLevel());
        }
    }

    public function testTransactional()
    {
        $res = $this->conn->transactional(function($conn) {
            /* @var $conn \Doctrine\DBAL\Connection */
            $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());
        });

        self::assertNull($res);
    }

    public function testTransactionalReturnValue()
    {
        $res = $this->conn->transactional(function() {
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
            $this->conn->quote("foo", Type::STRING),
            $this->conn->quote("foo", ParameterType::STRING)
        );
    }

    public function testPingDoesTriggersConnect()
    {
        self::assertTrue($this->conn->ping());
        self::assertTrue($this->conn->isConnected());
    }

    /**
     * @group DBAL-1025
     */
    public function testConnectWithoutExplicitDatabaseName()
    {
        if (in_array($this->conn->getDatabasePlatform()->getName(), array('oracle', 'db2'), true)) {
            $this->markTestSkipped('Platform does not support connecting without database name.');
        }

        $params = $this->conn->getParams();
        unset($params['dbname']);

        $connection = DriverManager::getConnection(
            $params,
            $this->conn->getConfiguration(),
            $this->conn->getEventManager()
        );

        self::assertTrue($connection->connect());

        $connection->close();
    }

    /**
     * @group DBAL-990
     */
    public function testDeterminesDatabasePlatformWhenConnectingToNonExistentDatabase()
    {
        if (in_array($this->conn->getDatabasePlatform()->getName(), ['oracle', 'db2'], true)) {
            $this->markTestSkipped('Platform does not support connecting without database name.');
        }

        $params = $this->conn->getParams();
        $params['dbname'] = 'foo_bar';

        $connection = DriverManager::getConnection(
            $params,
            $this->conn->getConfiguration(),
            $this->conn->getEventManager()
        );

        self::assertInstanceOf(AbstractPlatform::class, $connection->getDatabasePlatform());
        self::assertFalse($connection->isConnected());
        self::assertSame($params, $connection->getParams());

        $connection->close();
    }
}
