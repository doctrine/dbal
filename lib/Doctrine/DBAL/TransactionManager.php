<?php

namespace Doctrine\DBAL;

/**
 * Manages transactions.
 */
class TransactionManager
{
    const SAVEPOINT_PREFIX = 'DOCTRINE2_SAVEPOINT_';

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     * @var \Doctrine\DBAL\Transaction[]
     */
    private $activeTransactions = array();

    /**
     * Whether nested transactions should use savepoints.
     *
     * @var boolean
     */
    private $nestTransactionsWithSavepoints = false;

    /**
     * @param \Doctrine\DBAL\Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return TransactionBuilder
     */
    public function createTransaction()
    {
        return new TransactionBuilder($this);
    }

    /**
     * @param array $configuration
     *
     * @return \Doctrine\DBAL\Transaction
     */
    public function beginTransaction(array $configuration = array())
    {
        $this->connection->connect();

        if (isset($configuration[TransactionBuilder::ISOLATION_LEVEL])) {
            $this->connection->setTransactionIsolation($configuration[TransactionBuilder::ISOLATION_LEVEL]);
        }

        $logger = $this->connection->getConfiguration()->getSQLLogger();

        if (count($this->activeTransactions) === 0) {
            $logger && $logger->startQuery('"START TRANSACTION"');
            $this->connection->getWrappedConnection()->beginTransaction();
            $logger && $logger->stopQuery();
        } elseif ($this->nestTransactionsWithSavepoints) {
            $logger && $logger->startQuery('"SAVEPOINT"');
            $this->connection->createSavepoint($this->getNestedTransactionSavePointName(true));
            $logger && $logger->stopQuery();
        }

        $transaction = new Transaction($this, $configuration);
        $this->activeTransactions[] = $transaction;

        return $transaction;
    }

    /**
     * @param \Doctrine\DBAL\Transaction $transaction
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\ConnectionException If the transaction is stale, or belongs to another manager.
     */
    public function commitTransaction(Transaction $transaction)
    {
        if ($transaction->isActive()) {
            // Transaction::commit() has to be called first, and in turn call this method.
            $transaction->commit();

            return;
        }

        $nestedTransaction = $this->getNestedTransaction($transaction);

        if ($nestedTransaction) {
            $nestedTransaction->commit();
        }

        $logger = $this->connection->getConfiguration()->getSQLLogger();

        if (count($this->activeTransactions) === 1) {
            $logger && $logger->startQuery('"COMMIT"');
            $this->connection->getWrappedConnection()->commit();
            $logger && $logger->stopQuery();
        } elseif ($this->nestTransactionsWithSavepoints) {
            $logger && $logger->startQuery('"RELEASE SAVEPOINT"');
            $this->connection->releaseSavepoint($this->getNestedTransactionSavePointName());
            $logger && $logger->stopQuery();
        }

        array_pop($this->activeTransactions);

        if (! $this->connection->isAutoCommit() && ! $this->activeTransactions) {
            $this->beginTransaction($transaction->getConfiguration());
        }
    }

    /**
     * @param \Doctrine\DBAL\Transaction $transaction
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function rollbackTransaction(Transaction $transaction)
    {
        if ($transaction->isActive()) {
            // Transaction::rollback() has to be called first, and in turn call this method.
            $transaction->rollback();

            return;
        }

        $nestedTransaction = $this->getNestedTransaction($transaction);

        if ($nestedTransaction) {
            $nestedTransaction->rollback();
        }

        $logger = $this->connection->getConfiguration()->getSQLLogger();

        if (count($this->activeTransactions) === 1) {
            $logger && $logger->startQuery('"ROLLBACK"');
            $this->connection->getWrappedConnection()->rollBack();
            $logger && $logger->stopQuery();

            if (! $this->connection->isAutoCommit()) {
                $this->beginTransaction($transaction->getConfiguration());
            }
        } elseif ($this->nestTransactionsWithSavepoints) {
            $logger && $logger->startQuery('"ROLLBACK TO SAVEPOINT"');
            $this->connection->rollbackSavepoint($this->getNestedTransactionSavePointName());
            $logger && $logger->stopQuery();
        } else {
            $this->getTopLevelTransaction()->setRollbackOnly();
        }

        array_pop($this->activeTransactions);
    }

    /**
     * Sets whether nested transactions should use savepoints.
     *
     * @param boolean $nestTransactionsWithSavepoints
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\ConnectionException If a transaction is active, or savepoints are not supported.
     */
    public function setNestTransactionsWithSavepoints($nestTransactionsWithSavepoints)
    {
        if ($this->activeTransactions) {
            throw ConnectionException::mayNotAlterNestedTransactionWithSavepointsInTransaction();
        }

        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            throw ConnectionException::savepointsNotSupported();
        }

        $this->nestTransactionsWithSavepoints = (bool) $nestTransactionsWithSavepoints;
    }

    /**
     * Returns whether nested transactions should use savepoints.
     *
     * @return boolean
     */
    public function getNestTransactionsWithSavepoints()
    {
        return $this->nestTransactionsWithSavepoints;
    }

    /**
     * Returns the current transaction nesting level.
     *
     * @return integer The nesting level. A value of 0 means there's no active transaction.
     */
    public function getTransactionNestingLevel()
    {
        return count($this->activeTransactions);
    }

    /**
     * @return boolean
     */
    public function isTransactionActive()
    {
        return ! empty($this->activeTransactions);
    }

    /**
     * Returns the top-level active transaction, i.e. the first transaction started.
     *
     * @return \Doctrine\DBAL\Transaction
     *
     * @throws \Doctrine\DBAL\ConnectionException If there is no active transaction.
     */
    public function getTopLevelTransaction()
    {
        if (! $this->activeTransactions) {
            throw ConnectionException::noActiveTransaction();
        }

        return reset($this->activeTransactions);
    }

    /**
     * Returns the current transaction, i.e. the last transaction started.
     *
     * @return \Doctrine\DBAL\Transaction
     *
     * @throws \Doctrine\DBAL\ConnectionException If there is no active transaction.
     */
    public function getCurrentTransaction()
    {
        if (! $this->activeTransactions) {
            throw ConnectionException::noActiveTransaction();
        }

        return end($this->activeTransactions);
    }

    /**
     * Returns the savepoint name to use for nested transactions.
     *
     * @param boolean $begin Whether this method is called from beginTransaction().
     *                       In this case, the transaction has not been added to the list of active transactions yet.
     *
     * @return string
     */
    private function getNestedTransactionSavePointName($begin = false)
    {
        $index = count($this->activeTransactions);

        if ($begin) {
            $index++;
        }

        return self::SAVEPOINT_PREFIX . $index;
    }

    /**
     * Returns the nested transaction one level below the given one, if any.
     *
     * @param \Doctrine\DBAL\Transaction $transaction
     *
     * @return \Doctrine\DBAL\Transaction|null The nested transaction, or null if none.
     *
     * @throws \Doctrine\DBAL\ConnectionException If the given transaction is stale, or belongs to another manager.
     */
    private function getNestedTransaction(Transaction $transaction)
    {
        $transactionIndex = array_search($transaction, $this->activeTransactions, true);

        if ($transactionIndex === false) {
            throw ConnectionException::staleTransaction();
        }

        if (++$transactionIndex === count($this->activeTransactions)) {
            return null;
        }

        return $this->activeTransactions[$transactionIndex];
    }
}
