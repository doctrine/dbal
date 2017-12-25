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
        self::assertInstanceOf('Doctrine\DBAL\Driver\Connection', $this->_conn->getWrappedConnection());
    }

    public function testCommitWithRollbackOnlyThrowsException()
    {
        $this->_conn->beginTransaction();
        $this->_conn->setRollbackOnly();

        $this->expectException(ConnectionException::class);
        $this->_conn->commit();
    }

    public function testTransactionNestingBehavior()
    {
        try {
            $this->_conn->beginTransaction();
            self::assertEquals(1, $this->_conn->getTransactionNestingLevel());

            try {
                $this->_conn->beginTransaction();
                self::assertEquals(2, $this->_conn->getTransactionNestingLevel());
                throw new \Exception;
                $this->_conn->commit(); // never reached
            } catch (\Exception $e) {
                $this->_conn->rollBack();
                self::assertEquals(1, $this->_conn->getTransactionNestingLevel());
                //no rethrow
            }
            self::assertTrue($this->_conn->isRollbackOnly());

            $this->_conn->commit(); // should throw exception
            $this->fail('Transaction commit after failed nested transaction should fail.');
        } catch (ConnectionException $e) {
            self::assertEquals(1, $this->_conn->getTransactionNestingLevel());
            $this->_conn->rollBack();
            self::assertEquals(0, $this->_conn->getTransactionNestingLevel());
        }
    }

    public function testTransactionNestingBehaviorWithSavepoints()
    {
        if (!$this->_conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->_conn->setNestTransactionsWithSavepoints(true);
        try {
            $this->_conn->beginTransaction();
            self::assertEquals(1, $this->_conn->getTransactionNestingLevel());

            try {
                $this->_conn->beginTransaction();
                self::assertEquals(2, $this->_conn->getTransactionNestingLevel());
                $this->_conn->beginTransaction();
                self::assertEquals(3, $this->_conn->getTransactionNestingLevel());
                $this->_conn->commit();
                self::assertEquals(2, $this->_conn->getTransactionNestingLevel());
                throw new \Exception;
                $this->_conn->commit(); // never reached
            } catch (\Exception $e) {
                $this->_conn->rollBack();
                self::assertEquals(1, $this->_conn->getTransactionNestingLevel());
                //no rethrow
            }
            self::assertFalse($this->_conn->isRollbackOnly());
            try {
                $this->_conn->setNestTransactionsWithSavepoints(false);
                $this->fail('Should not be able to disable savepoints in usage for nested transactions inside an open transaction.');
            } catch (ConnectionException $e) {
                self::assertTrue($this->_conn->getNestTransactionsWithSavepoints());
            }
            $this->_conn->commit(); // should not throw exception
        } catch (ConnectionException $e) {
            $this->fail('Transaction commit after failed nested transaction should not fail when using savepoints.');
            $this->_conn->rollBack();
        }
    }

    public function testTransactionNestingBehaviorCantBeChangedInActiveTransaction()
    {
        if (!$this->_conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->_conn->beginTransaction();
        $this->expectException(ConnectionException::class);
        $this->_conn->setNestTransactionsWithSavepoints(true);
    }

    public function testSetNestedTransactionsThroughSavepointsNotSupportedThrowsException()
    {
        if ($this->_conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage("Savepoints are not supported by this driver.");

        $this->_conn->setNestTransactionsWithSavepoints(true);
    }

    public function testCreateSavepointsNotSupportedThrowsException()
    {
        if ($this->_conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage("Savepoints are not supported by this driver.");

        $this->_conn->createSavepoint('foo');
    }

    public function testReleaseSavepointsNotSupportedThrowsException()
    {
        if ($this->_conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage("Savepoints are not supported by this driver.");

        $this->_conn->releaseSavepoint('foo');
    }

    public function testRollbackSavepointsNotSupportedThrowsException()
    {
        if ($this->_conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage("Savepoints are not supported by this driver.");

        $this->_conn->rollbackSavepoint('foo');
    }

    public function testTransactionBehaviorWithRollback()
    {
        try {
            $this->_conn->beginTransaction();
            self::assertEquals(1, $this->_conn->getTransactionNestingLevel());

            throw new \Exception;

            $this->_conn->commit(); // never reached
        } catch (\Exception $e) {
            self::assertEquals(1, $this->_conn->getTransactionNestingLevel());
            $this->_conn->rollBack();
            self::assertEquals(0, $this->_conn->getTransactionNestingLevel());
        }
    }

    public function testTransactionBehaviour()
    {
        try {
            $this->_conn->beginTransaction();
            self::assertEquals(1, $this->_conn->getTransactionNestingLevel());
            $this->_conn->commit();
        } catch (\Exception $e) {
            $this->_conn->rollBack();
            self::assertEquals(0, $this->_conn->getTransactionNestingLevel());
        }

        self::assertEquals(0, $this->_conn->getTransactionNestingLevel());
    }

    public function testTransactionalWithException()
    {
        try {
            $this->_conn->transactional(function($conn) {
                /* @var $conn \Doctrine\DBAL\Connection */
                $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());
                throw new \RuntimeException("Ooops!");
            });
            $this->fail('Expected exception');
        } catch (\RuntimeException $expected) {
            self::assertEquals(0, $this->_conn->getTransactionNestingLevel());
        }
    }

    public function testTransactionalWithThrowable()
    {
        try {
            $this->_conn->transactional(function($conn) {
                /* @var $conn \Doctrine\DBAL\Connection */
                $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());
                throw new \Error("Ooops!");
            });
            $this->fail('Expected exception');
        } catch (\Error $expected) {
            self::assertEquals(0, $this->_conn->getTransactionNestingLevel());
        }
    }

    public function testTransactional()
    {
        $res = $this->_conn->transactional(function($conn) {
            /* @var $conn \Doctrine\DBAL\Connection */
            $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());
        });

        self::assertNull($res);
    }

    public function testTransactionalReturnValue()
    {
        $res = $this->_conn->transactional(function() {
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
            $this->_conn->quote("foo", Type::STRING),
            $this->_conn->quote("foo", ParameterType::STRING)
        );
    }

    public function testPingDoesTriggersConnect()
    {
        self::assertTrue($this->_conn->ping());
        self::assertTrue($this->_conn->isConnected());
    }

    /**
     * @group DBAL-1025
     */
    public function testConnectWithoutExplicitDatabaseName()
    {
        if (in_array($this->_conn->getDatabasePlatform()->getName(), array('oracle', 'db2'), true)) {
            $this->markTestSkipped('Platform does not support connecting without database name.');
        }

        $params = $this->_conn->getParams();
        unset($params['dbname']);

        $connection = DriverManager::getConnection(
            $params,
            $this->_conn->getConfiguration(),
            $this->_conn->getEventManager()
        );

        self::assertTrue($connection->connect());

        $connection->close();
    }

    /**
     * @group DBAL-990
     */
    public function testDeterminesDatabasePlatformWhenConnectingToNonExistentDatabase()
    {
        if (in_array($this->_conn->getDatabasePlatform()->getName(), ['oracle', 'db2'], true)) {
            $this->markTestSkipped('Platform does not support connecting without database name.');
        }

        $params = $this->_conn->getParams();
        $params['dbname'] = 'foo_bar';

        $connection = DriverManager::getConnection(
            $params,
            $this->_conn->getConfiguration(),
            $this->_conn->getEventManager()
        );

        self::assertInstanceOf(AbstractPlatform::class, $connection->getDatabasePlatform());
        self::assertFalse($connection->isConnected());
        self::assertSame($params, $connection->getParams());

        $connection->close();
    }
}
