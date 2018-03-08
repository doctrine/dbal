<?php

namespace Doctrine\DBAL;

/**
 * Builds a transaction with a fluent API.
 */
class TransactionBuilder
{
    const ISOLATION_LEVEL = 'dbal.isolation-level';

    /**
     * The connection.
     *
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     * The configuration for the transaction to be built.
     *
     * @var array
     */
    private $configuration = array();

    /**
     * Class constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
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
     * @param int $isolationLevel
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
        return $this->connection->beginTransaction($this->configuration);
    }
}
