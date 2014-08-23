<?php

namespace Doctrine\DBAL;

/**
 * Builds a transaction with a fluent API.
 */
class TransactionBuilder
{
    const ISOLATION_LEVEL = 'dbal.isolation-level';

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
     * Sets a configuration variable.
     *
     * @param string $configurationName  The configuration variable name.
     * @param string $configurationValue The configuration value.
     *
     * @return \Doctrine\DBAL\TransactionBuilder The current instance for chaining.
     */
    protected function with($configurationName, $configurationValue)
    {
        $this->configuration[$configurationName] = $configurationValue;

        return $this;
    }

    /**
     * @param integer $isolationLevel
     *
     * @return \Doctrine\DBAL\TransactionBuilder
     */
    public function withIsolationLevel($isolationLevel)
    {
        return $this->with(self::ISOLATION_LEVEL, $isolationLevel);
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
