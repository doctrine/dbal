<?php

namespace Doctrine\DBAL;

/**
 * The transaction object.
 */
class Transaction
{
    /**
     * The connection that created this transaction object.
     *
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     * The configuration for this transaction.
     *
     * @var array
     */
    private $configuration;

    /**
     * Indicates whether this transaction is marked for rollback only.
     *
     * @var bool
     */
    private $isRollbackOnly = false;

    /**
     * Indicates whether this transaction was committed.
     *
     * @var bool
     */
    private $wasCommitted = false;

    /**
     * Indicates whether this transaction was rolled back.
     *
     * @var bool
     */
    private $wasRolledBack = false;

    /**
     * Class constructor.
     *
     * @param \Doctrine\DBAL\Connection $connection
     * @param array                     $configuration
     */
    public function __construct(Connection $connection, array $configuration)
    {
        $this->connection    = $connection;
        $this->configuration = $configuration;
    }

    /**
     * Commits this transaction.
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\ConnectionException If this transaction is not active or marked as rollback only.
     */
    public function commit()
    {
        if (! $this->isActive()) {
            throw ConnectionException::transactionNotActive();
        }

        if ($this->isRollbackOnly) {
            throw ConnectionException::commitFailedRollbackOnly();
        }

        $this->wasCommitted = true;

        $this->connection->commitTransaction($this);
    }

    /**
     * Rolls back this transaction.
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\ConnectionException If this transaction is not active.
     */
    public function rollback()
    {
        if (! $this->isActive()) {
            throw ConnectionException::transactionNotActive();
        }

        $this->wasRolledBack = true;

        $this->connection->rollbackTransaction($this);
    }

    /**
     * Returns whether this transaction is still active.
     *
     * @return bool
     */
    public function isActive()
    {
        return ! ($this->wasCommitted || $this->wasRolledBack);
    }

    /**
     * Marks the current transaction so that the only possible outcome for the transaction to be rolled back.
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\ConnectionException If this transaction is not active.
     */
    public function setRollbackOnly()
    {
        if (! $this->isActive()) {
            throw ConnectionException::transactionNotActive();
        }

        $this->isRollbackOnly = true;
    }

    /**
     * Returns whether this transaction is marked for rollback only.
     *
     * @return bool
     */
    public function isRollbackOnly()
    {
        return $this->isRollbackOnly;
    }

    /**
     * Returns whether this transaction was committed.
     *
     * @return bool
     */
    public function wasCommitted()
    {
        return $this->wasCommitted;
    }

    /**
     * Returns whether this transaction was rolled back.
     *
     * @return bool
     */
    public function wasRolledBack()
    {
        return $this->wasRolledBack;
    }

    /**
     * Returns the configuration for this transaction.
     *
     * @return array
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }
}
