<?php

namespace Doctrine\DBAL;

/**
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Jonathan H. Wage <jonwage@gmail.com
 */
class ConnectionException extends DBALException
{
    /**
     * @return \Doctrine\DBAL\ConnectionException
     */
    public static function commitFailedRollbackOnly()
    {
        return new self("Transaction commit failed because the transaction has been marked for rollback only.");
    }

    /**
     * @return \Doctrine\DBAL\ConnectionException
     */
    public static function noActiveTransaction()
    {
        return new self("There is no active transaction.");
    }

    /**
     * @return \Doctrine\DBAL\ConnectionException
     */
    public static function transactionNotActive()
    {
        return new self("This transaction is not active, and cannot be committed or rolled back.");
    }

    /**
     * @return \Doctrine\DBAL\ConnectionException
     */
    public static function staleTransaction()
    {
        return new self(
            "This transaction is not managed by this connection. " .
            "It has probably already been committed or rolled back, or belongs to another connection."
        );
    }

    /**
     * @return \Doctrine\DBAL\ConnectionException
     */
    public static function savepointsNotSupported()
    {
        return new self("Savepoints are not supported by this driver.");
    }

    /**
     * @return \Doctrine\DBAL\ConnectionException
     */
    public static function mayNotAlterNestedTransactionWithSavepointsInTransaction()
    {
        return new self("May not alter the nested transaction with savepoints behavior while a transaction is open.");
    }
}
