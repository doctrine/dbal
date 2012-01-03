<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Types\Type;

require_once __DIR__ . '/../../TestInit.php';

class ConnectionTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function setUp()
    {
        $this->resetSharedConn();
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->resetSharedConn();
    }

    public function testGetWrappedConnection()
    {
        $this->assertInstanceOf('Doctrine\DBAL\Driver\Connection', $this->_conn->getWrappedConnection());
    }

    public function testCommitWithRollbackOnlyThrowsException()
    {
        $this->_conn->beginTransaction();
        $this->_conn->setRollbackOnly();
        $this->setExpectedException('Doctrine\DBAL\ConnectionException');
        $this->_conn->commit();
    }

    public function testTransactionNestingBehavior()
    {
        try {
            $this->_conn->beginTransaction();
            $this->assertEquals(1, $this->_conn->getTransactionNestingLevel());

            try {
                $this->_conn->beginTransaction();
                $this->assertEquals(2, $this->_conn->getTransactionNestingLevel());
                throw new \Exception;
                $this->_conn->commit(); // never reached
            } catch (\Exception $e) {
                $this->_conn->rollback();
                $this->assertEquals(1, $this->_conn->getTransactionNestingLevel());
                //no rethrow
            }
            $this->assertTrue($this->_conn->isRollbackOnly());

            $this->_conn->commit(); // should throw exception
            $this->fail('Transaction commit after failed nested transaction should fail.');
        } catch (ConnectionException $e) {
            $this->assertEquals(1, $this->_conn->getTransactionNestingLevel());
            $this->_conn->rollback();
            $this->assertEquals(0, $this->_conn->getTransactionNestingLevel());
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
            $this->assertEquals(1, $this->_conn->getTransactionNestingLevel());

            try {
                $this->_conn->beginTransaction();
                $this->assertEquals(2, $this->_conn->getTransactionNestingLevel());
                $this->_conn->beginTransaction();
                $this->assertEquals(3, $this->_conn->getTransactionNestingLevel());
                $this->_conn->commit();
                $this->assertEquals(2, $this->_conn->getTransactionNestingLevel());
                throw new \Exception;
                $this->_conn->commit(); // never reached
            } catch (\Exception $e) {
                $this->_conn->rollback();
                $this->assertEquals(1, $this->_conn->getTransactionNestingLevel());
                //no rethrow
            }
            $this->assertFalse($this->_conn->isRollbackOnly());
            try {
                $this->_conn->setNestTransactionsWithSavepoints(false);
                $this->fail('Should not be able to disable savepoints in usage for nested transactions inside an open transaction.');
            } catch (ConnectionException $e) {
                $this->assertTrue($this->_conn->getNestTransactionsWithSavepoints());
            }
            $this->_conn->commit(); // should not throw exception
        } catch (ConnectionException $e) {
            $this->fail('Transaction commit after failed nested transaction should not fail when using savepoints.');
            $this->_conn->rollback();
        }
    }

    public function testTransactionNestingBehaviorCantBeChangedInActiveTransaction()
    {
        if (!$this->_conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->_conn->beginTransaction();
        try {
            $this->_conn->setNestTransactionsWithSavepoints(true);
            $this->fail('An exception should have been thrown by chaning the nesting transaction behavior within an transaction.');
        } catch(ConnectionException $e) {
            $this->_conn->rollBack();
        }
    }

    public function testSetNestedTransactionsThroughSavepointsNotSupportedThrowsException()
    {
        if ($this->_conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->setExpectedException('Doctrine\DBAL\ConnectionException', "Savepoints are not supported by this driver.");

        $this->_conn->setNestTransactionsWithSavepoints(true);
    }

    public function testCreateSavepointsNotSupportedThrowsException()
    {
        if ($this->_conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->setExpectedException('Doctrine\DBAL\ConnectionException', "Savepoints are not supported by this driver.");

        $this->_conn->createSavepoint('foo');
    }

    public function testReleaseSavepointsNotSupportedThrowsException()
    {
        if ($this->_conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->setExpectedException('Doctrine\DBAL\ConnectionException', "Savepoints are not supported by this driver.");

        $this->_conn->releaseSavepoint('foo');
    }

    public function testRollbackSavepointsNotSupportedThrowsException()
    {
        if ($this->_conn->getDatabasePlatform()->supportsSavepoints()) {
            $this->markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->setExpectedException('Doctrine\DBAL\ConnectionException', "Savepoints are not supported by this driver.");

        $this->_conn->rollbackSavepoint('foo');
    }

    public function testTransactionBehaviorWithRollback()
    {
        try {
            $this->_conn->beginTransaction();
            $this->assertEquals(1, $this->_conn->getTransactionNestingLevel());

            throw new \Exception;

            $this->_conn->commit(); // never reached
        } catch (\Exception $e) {
            $this->assertEquals(1, $this->_conn->getTransactionNestingLevel());
            $this->_conn->rollback();
            $this->assertEquals(0, $this->_conn->getTransactionNestingLevel());
        }
    }

    public function testTransactionBehaviour()
    {
        try {
            $this->_conn->beginTransaction();
            $this->assertEquals(1, $this->_conn->getTransactionNestingLevel());
            $this->_conn->commit();
        } catch (\Exception $e) {
            $this->_conn->rollback();
            $this->assertEquals(0, $this->_conn->getTransactionNestingLevel());
        }

        $this->assertEquals(0, $this->_conn->getTransactionNestingLevel());
    }

    public function testTransactionalWithException()
    {
        try {
            $this->_conn->transactional(function($conn) {
                $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());
                throw new \RuntimeException("Ooops!");
            });
        } catch (\RuntimeException $expected) {
            $this->assertEquals(0, $this->_conn->getTransactionNestingLevel());
        }
    }

    public function testTransactional()
    {
        $this->_conn->transactional(function($conn) {
            /* @var $conn Connection */
            $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());
        });
    }

    /**
     * Tests that the quote function accepts DBAL and PDO types.
     */
    public function testQuote()
    {
        $this->assertEquals($this->_conn->quote("foo", Type::STRING), $this->_conn->quote("foo", \PDO::PARAM_STR));
    }
}
