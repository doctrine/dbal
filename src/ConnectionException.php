<?php

namespace Doctrine\DBAL;

/**
 * @psalm-immutable
 */
class ConnectionException extends Exception
{
    public static function commitFailedRollbackOnly(): ConnectionException
    {
        return new self('Transaction commit failed because the transaction has been marked for rollback only.');
    }

    public static function noActiveTransaction(): ConnectionException
    {
        return new self('There is no active transaction.');
    }

    public static function savepointsNotSupported(): ConnectionException
    {
        return new self('Savepoints are not supported by this driver.');
    }

    public static function mayNotAlterNestedTransactionWithSavepointsInTransaction(): ConnectionException
    {
        return new self('May not alter the nested transaction with savepoints behavior while a transaction is open.');
    }
}
