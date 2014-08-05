<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Transaction;
use Doctrine\DBAL\TransactionDefinition;
use Doctrine\Tests\DbalTestCase;

/**
 * Unit tests for the transaction object.
 */
class TransactionTest extends DbalTestCase
{
    /**
     * The transaction manager mock.
     *
     * @var \Doctrine\Tests\DBAL\Mocks\TransactionManagerMock
     */
    private $transactionManager;

    /**
     * A Transaction instance built on the transaction manager mock.
     *
     * @var \Doctrine\DBAL\Transaction
     */
    private $transaction;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->transactionManager = $this->getMock('Doctrine\DBAL\TransactionManager', array(), array(), '', false);
        $transactionDefinition = new TransactionDefinition($this->transactionManager);
        $this->transaction = new Transaction($this->transactionManager, $transactionDefinition);
    }

    public function testTransactionDefaults()
    {
        $this->assertTrue($this->transaction->isActive());
        $this->assertFalse($this->transaction->wasCommitted());
        $this->assertFalse($this->transaction->wasRolledBack());
        $this->assertFalse($this->transaction->isRollbackOnly());
    }

    public function testCommit()
    {
        $this->transactionManager->expects($this->once())->method('commitTransaction');

        $this->transaction->commit();

        $this->assertFalse($this->transaction->isActive());
        $this->assertTrue($this->transaction->wasCommitted());
        $this->assertFalse($this->transaction->wasRolledBack());
    }

    public function testRollback()
    {
        $this->transactionManager->expects($this->once())->method('rollbackTransaction');

        $this->transaction->rollback();

        $this->assertFalse($this->transaction->isActive());
        $this->assertTrue($this->transaction->wasRolledBack());
        $this->assertFalse($this->transaction->wasCommitted());
    }

    public function testSetRollbackOnly()
    {
        $this->transaction->setRollbackOnly();
        $this->assertTrue($this->transaction->isRollbackOnly());
    }

    /**
     * @expectedException \Doctrine\DBAL\ConnectionException
     */
    public function testCommittingInactiveTransactionThrowsException()
    {
        $this->transaction->commit();
        $this->transaction->commit();
    }

    /**
     * @expectedException \Doctrine\DBAL\ConnectionException
     */
    public function testCommittingRollbackOnlyTransactionThrowsException()
    {
        $this->transaction->setRollbackOnly();
        $this->transaction->commit();
    }

    /**
     * @expectedException \Doctrine\DBAL\ConnectionException
     */
    public function testRollingBackInactiveTransactionThrowsException()
    {
        $this->transaction->rollback();
        $this->transaction->rollback();
    }

    /**
     * @expectedException \Doctrine\DBAL\ConnectionException
     */
    public function testMarkingInactiveTransactionAsRollbackOnlyThrowsException()
    {
        $this->transaction->commit();
        $this->transaction->setRollbackOnly();
    }
}
