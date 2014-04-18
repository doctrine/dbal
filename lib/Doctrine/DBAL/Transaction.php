<?php

namespace Doctrine\DBAL;

/**
 * The transaction object.
 */
class Transaction
{
    /**
     * @var \Doctrine\DBAL\TransactionManager
     */
    private $transactionManager;

    /**
     * Indicates whether this transaction is active, and can be committed or rolled back.
     *
     * @var boolean
     */
    private $isActive = true;

    /**
     * Indicates whether this transaction is marked for rollback only.
     *
     * @var boolean
     */
    private $isRollbackOnly = false;

    /**
     * @param TransactionManager $transactionManager
     */
    public function __construct(TransactionManager $transactionManager)
    {
        $this->transactionManager = $transactionManager;
    }

    /**
     * @return void
     *
     * @throws \Doctrine\DBAL\ConnectionException If this transaction is not active or marked as rollback only.
     */
    public function commit()
    {
        if (! $this->isActive) {
            throw ConnectionException::transactionNotActive();
        }

        if ($this->isRollbackOnly) {
            throw ConnectionException::commitFailedRollbackOnly();
        }

        $this->isActive = false;

        $this->transactionManager->commitTransaction($this);
    }

    /**
     * @return void
     *
     * @throws \Doctrine\DBAL\ConnectionException If this transaction is not active.
     */
    public function rollback()
    {
        if (! $this->isActive) {
            throw ConnectionException::transactionNotActive();
        }

        $this->isActive = false;

        $this->transactionManager->rollbackTransaction($this);
    }

    /**
     * Returns whether this transaction is still active.
     *
     * @return boolean
     */
    public function isActive()
    {
        return $this->isActive;
    }

    /**
     * Marks the current transaction so that the only possible outcome for the transaction to be rolled back.
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\ConnectionException If no transaction is active.
     */
    public function setRollbackOnly()
    {
        $this->isRollbackOnly = true;
    }

    /**
     * Returns whether this transaction is marked for rollback only.
     *
     * @return boolean
     */
    public function isRollbackOnly()
    {
        return $this->isRollbackOnly;
    }
}
