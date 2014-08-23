<?php

namespace Doctrine\DBAL;

/**
 * Builds a transaction with a fluent API.
 */
class TransactionBuilder
{
    /**
     * The transaction manager.
     *
     * @var \Doctrine\DBAL\TransactionManager
     */
    private $transactionManager;

    /**
     * The configuration for the transaction to be built.
     *
     * @var array
     */
    private $configuration = array();

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
     * @param string $configurationName  The configuration variable name.
     * @param string $configurationValue The configuration value.
     *
     * @return \Doctrine\DBAL\TransactionBuilder The current instance for chaining.
     */
    public function with($configurationName, $configurationValue)
    {
        $this->configuration[$configurationName] = $configurationValue;

        return $this;
    }

    /**
     * Begins the transaction and returns the associated Transaction object.
     *
     * @return \Doctrine\DBAL\Transaction
     */
    public function begin()
    {
        return $this->transactionManager->beginTransaction($this->configuration);
    }
}
