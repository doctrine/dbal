<?php

namespace Doctrine\DBAL;

/**
 * Defines the parameters of a transaction.
 *
 * This object is immutable.
 */
class TransactionDefinition
{
    /**
     * The isolation level for the transaction, or null if not set.
     *
     * @var integer|null
     */
    private $isolationLevel;

    /**
     * Class constructor.
     *
     * @param TransactionBuilder $builder A builder to get the parameters from, or null to use the defaults.
     */
    public function __construct(TransactionBuilder $builder = null)
    {
        if ($builder) {
            $this->isolationLevel = $builder->getIsolationLevel();
        }
    }

    /**
     * Returns the isolation level set for this transaction.
     *
     * @return integer|null The isolation level, or null if not set.
     */
    public function getIsolationLevel()
    {
        return $this->isolationLevel;
    }
}
