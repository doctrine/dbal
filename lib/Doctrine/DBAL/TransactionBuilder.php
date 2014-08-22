<?php

namespace Doctrine\DBAL;

/**
 * Builds a transaction with a fluent API.
 */
class TransactionBuilder
{
    /**
     * The transaction manager that created this definition.
     *
     * @var \Doctrine\DBAL\TransactionManager
     */
    private $transactionManager;

    /**
     * The isolation level for the transaction, or null if not set.
     *
     * @var integer|null
     */
    private $isolationLevel;

    /**
     * Class constructor.
     *
     * @param TransactionManager $manager
     */
    public function __construct(TransactionManager $manager)
    {
        $this->transactionManager = $manager;
    }

    /**
     * Sets the isolation level for this transaction.
     *
     * @param integer $isolationLevel One of the Connection::TRANSACTION_* constants.
     *
     * @return \Doctrine\DBAL\TransactionDefinition The current instance for chaining.
     */
    public function withIsolationLevel($isolationLevel)
    {
        $this->isolationLevel = $isolationLevel;

        return $this;
    }

    /**
     * Returns the isolation level set for this transaction.
     *
     * @return integer|null The isolation level if set, else null.
     */
    public function getIsolationLevel()
    {
        return $this->isolationLevel;
    }

    /**
     * Begins the transaction and returns the associated Transaction object.
     *
     * @return \Doctrine\DBAL\Transaction
     */
    public function begin()
    {
        $definition = new TransactionDefinition($this);

        return $this->transactionManager->beginTransaction($definition);
    }
}
