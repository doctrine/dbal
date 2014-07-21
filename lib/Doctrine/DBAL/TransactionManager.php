<?php

namespace Doctrine\DBAL;

/**
 * Manages transactions.
 */
class TransactionManager
{
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
     * @param \Doctrine\DBAL\TransactionDefinition $definition
     *
     * @return \Doctrine\DBAL\Transaction
     */
    public function createTransaction(TransactionDefinition $definition)
    {
        if ($definition === null) {
            $definition = new TransactionDefinition($this);
        }

        $this->connection->connect();

        $isolationLevel = $definition->getIsolationLevel();
        if ($isolationLevel !== null) {
            $this->connection->setTransactionIsolation($isolationLevel);
        }

        $transaction = new Transaction($definition);
        $this->activeTransactions[] = $transaction;

        $logger = $this->connection->getConfiguration()->getSQLLogger();

        if (count($this->activeTransactions) === 1) {
            $logger && $logger->startQuery('"START TRANSACTION"');
            $this->connection->getWrappedConnection()->beginTransaction();
            $logger && $logger->stopQuery();
        } elseif ($this->nestTransactionsWithSavepoints) {
            $logger && $logger->startQuery('"SAVEPOINT"');
            $this->connection->createSavepoint($this->getNestedTransactionSavePointName());
            $logger && $logger->stopQuery();
        }

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
            $this->createTransaction($transaction->getTransactionDefinition());
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
                $this->createTransaction($transaction->getTransactionDefinition());
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
     * @return string
     */
    private function getNestedTransactionSavePointName()
    {
        return 'DOCTRINE2_SAVEPOINT_' . count($this->activeTransactions);
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
