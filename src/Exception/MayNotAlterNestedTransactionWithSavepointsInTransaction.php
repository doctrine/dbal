<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\ConnectionException;

/**
 * @psalm-immutable
 */
final class MayNotAlterNestedTransactionWithSavepointsInTransaction extends ConnectionException
{
    public static function new(): self
    {
        return new self('May not alter the nested transaction with savepoints behavior while a transaction is open.');
    }
}
