<?php

namespace Doctrine\DBAL;

class ConnectionException extends DBALException
{
    // Exception codes. Dedicated 100-199 numbers
    public const ROLLBACK_ONLY                 = 100;
    public const NO_ACTIVE_TRANSACTION         = 110;
    public const SAVE_POINTS_NOT_SUPPORTED     = 120;
    public const SAVEPOINT_IN_OPEN_TRANSACTION = 130;

    /**
     * @return \Doctrine\DBAL\ConnectionException
     */
    public static function commitFailedRollbackOnly()
    {
        return new self(
            'Transaction commit failed because the transaction has been marked for rollback only.',
            self::ROLLBACK_ONLY
        );
    }

    /**
     * @return \Doctrine\DBAL\ConnectionException
     */
    public static function noActiveTransaction()
    {
        return new self('There is no active transaction.', self::NO_ACTIVE_TRANSACTION);
    }

    /**
     * @return \Doctrine\DBAL\ConnectionException
     */
    public static function savepointsNotSupported()
    {
        return new self('Savepoints are not supported by this driver.', self::SAVE_POINTS_NOT_SUPPORTED);
    }

    /**
     * @return \Doctrine\DBAL\ConnectionException
     */
    public static function mayNotAlterNestedTransactionWithSavepointsInTransaction()
    {
        return new self(
            'May not alter the nested transaction with savepoints behavior while a transaction is open.',
            self::SAVEPOINT_IN_OPEN_TRANSACTION
        );
    }
}
