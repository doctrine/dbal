<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\Tests\DbalFunctionalTestCase;

/**
 * Functional tests for the transaction manager.
 */
class TransactionManagerTest extends DbalFunctionalTestCase
{
    /**
     * @var \Doctrine\DBAL\TransactionManager
     */
    private $transactionManager;

    protected function setUp()
    {
        parent::setUp();

        $this->transactionManager = $this->_conn->getTransactionManager();
    }

    public function testGetNestTransactionsWithSavepointsIsFalseByDefault()
    {
        $this->assertFalse($this->transactionManager->getNestTransactionsWithSavepoints());
    }

    public function testSetNestTransactionsWithSavepoints()
    {
        $this->transactionManager->setNestTransactionsWithSavepoints(true);
        $this->assertTrue($this->transactionManager->getNestTransactionsWithSavepoints());
    }

    /**
     * @expectedException \Doctrine\DBAL\ConnectionException
     */
    public function testCallingGetCurrentTransactionWhileNotInTransactionThrowsException()
    {
        $this->transactionManager->getCurrentTransaction();
    }

    /**
     * @expectedException \Doctrine\DBAL\ConnectionException
     */
    public function testCallingGetTopLevelTransactionWhileNotInTransactionThrowsException()
    {
        $this->transactionManager->getTopLevelTransaction();
    }

    public function testTransactionNesting()
    {
        $this->assertTransactionNestingLevel(0);

        $transaction1 = $this->transactionManager->createTransaction();

        $this->assertSame($transaction1, $this->transactionManager->getTopLevelTransaction());
        $this->assertSame($transaction1, $this->transactionManager->getCurrentTransaction());

        $this->assertTransactionNestingLevel(1);

        $transaction2 = $this->transactionManager->createTransaction();

        $this->assertSame($transaction1, $this->transactionManager->getTopLevelTransaction());
        $this->assertSame($transaction2, $this->transactionManager->getCurrentTransaction());

        $this->assertTransactionNestingLevel(2);

        $transaction2->rollback();

        $this->assertSame($transaction1, $this->transactionManager->getTopLevelTransaction());
        $this->assertSame($transaction1, $this->transactionManager->getCurrentTransaction());

        $this->assertTransactionNestingLevel(1);

        $transaction1->commit();

        $this->assertTransactionNestingLevel(0);
    }

    public function testCommittingTopLevelTransactionCommitsNestedTransactions()
    {
        $transaction1 = $this->transactionManager->createTransaction();
        $transaction2 = $this->transactionManager->createTransaction();
        $transaction3 = $this->transactionManager->createTransaction();

        $transaction1->commit();

        $this->assertTrue($transaction2->wasCommitted());
        $this->assertTrue($transaction3->wasCommitted());
    }

    public function testRollingBackTopLevelTransactionRollsBackNestedTransactions()
    {
        $transaction1 = $this->transactionManager->createTransaction();
        $transaction2 = $this->transactionManager->createTransaction();
        $transaction3 = $this->transactionManager->createTransaction();

        $transaction1->rollback();

        $this->assertTrue($transaction2->wasRolledBack());
        $this->assertTrue($transaction3->wasRolledBack());
    }

    /**
     * @param integer $expected
     */
    private function assertTransactionNestingLevel($expected)
    {
        $this->assertSame($expected, $this->transactionManager->getTransactionNestingLevel());
        $this->assertSame($expected !== 0, $this->transactionManager->isTransactionActive());
    }
}
