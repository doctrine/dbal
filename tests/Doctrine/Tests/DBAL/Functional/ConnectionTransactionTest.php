<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\Tests\DbalFunctionalTestCase;

/**
 * Functional tests for the Connection transactions.
 */
class ConnectionTransactionTest extends DbalFunctionalTestCase
{
    public function testGetNestTransactionsWithSavepointsIsFalseByDefault()
    {
        $this->assertFalse($this->_conn->getNestTransactionsWithSavepoints());
    }

    public function testSetNestTransactionsWithSavepoints()
    {
        $this->_conn->setNestTransactionsWithSavepoints(true);
        $this->assertTrue($this->_conn->getNestTransactionsWithSavepoints());
    }

    /**
     * @expectedException \Doctrine\DBAL\ConnectionException
     */
    public function testCallingGetCurrentTransactionWhileNotInTransactionThrowsException()
    {
        $this->_conn->getCurrentTransaction();
    }

    /**
     * @expectedException \Doctrine\DBAL\ConnectionException
     */
    public function testCallingGetTopLevelTransactionWhileNotInTransactionThrowsException()
    {
        $this->_conn->getTopLevelTransaction();
    }

    public function testTransactionNesting()
    {
        $this->assertTransactionNestingLevel(0);

        $transaction1 = $this->_conn->beginTransaction();

        $this->assertSame($transaction1, $this->_conn->getTopLevelTransaction());
        $this->assertSame($transaction1, $this->_conn->getCurrentTransaction());

        $this->assertTransactionNestingLevel(1);

        $transaction2 = $this->_conn->beginTransaction();

        $this->assertSame($transaction1, $this->_conn->getTopLevelTransaction());
        $this->assertSame($transaction2, $this->_conn->getCurrentTransaction());

        $this->assertTransactionNestingLevel(2);

        $transaction2->rollback();

        $this->assertSame($transaction1, $this->_conn->getTopLevelTransaction());
        $this->assertSame($transaction1, $this->_conn->getCurrentTransaction());

        $this->assertTransactionNestingLevel(1);

        $transaction1->commit();

        $this->assertTransactionNestingLevel(0);
    }

    public function testCommittingTopLevelTransactionCommitsNestedTransactions()
    {
        $transaction1 = $this->_conn->beginTransaction();
        $transaction2 = $this->_conn->beginTransaction();
        $transaction3 = $this->_conn->beginTransaction();

        $transaction1->commit();

        $this->assertTrue($transaction2->wasCommitted());
        $this->assertTrue($transaction3->wasCommitted());
    }

    public function testRollingBackTopLevelTransactionRollsBackNestedTransactions()
    {
        $transaction1 = $this->_conn->beginTransaction();
        $transaction2 = $this->_conn->beginTransaction();
        $transaction3 = $this->_conn->beginTransaction();

        $transaction1->rollback();

        $this->assertTrue($transaction2->wasRolledBack());
        $this->assertTrue($transaction3->wasRolledBack());
    }

    /**
     * @param int $expected
     */
    private function assertTransactionNestingLevel($expected)
    {
        $this->assertSame($expected, $this->_conn->getTransactionNestingLevel());
        $this->assertSame($expected !== 0, $this->_conn->isTransactionActive());
    }
}
