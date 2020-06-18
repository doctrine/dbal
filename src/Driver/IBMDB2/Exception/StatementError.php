<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2\Exception;

use Doctrine\DBAL\Driver\IBMDB2\DB2Exception;

use function db2_stmt_error;
use function db2_stmt_errormsg;

/**
 * @psalm-immutable
 */
final class StatementError extends DB2Exception
{
    /**
     * @param resource $statement
     */
    public static function new($statement): self
    {
        return new self(db2_stmt_errormsg($statement), db2_stmt_error($statement));
    }
}
