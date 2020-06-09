<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\AbstractDriverException;

use function db2_conn_error;
use function db2_conn_errormsg;
use function db2_stmt_error;
use function db2_stmt_errormsg;

/**
 * @psalm-immutable
 */
class DB2Exception extends AbstractDriverException
{
    /**
     * @param resource|null $connection
     */
    public static function fromConnectionError($connection = null): self
    {
        if ($connection !== null) {
            return new self(db2_conn_errormsg($connection), db2_conn_error($connection));
        }

        return new self(db2_conn_errormsg(), db2_conn_error());
    }

    /**
     * @param resource|null $statement
     */
    public static function fromStatementError($statement = null): self
    {
        if ($statement !== null) {
            return new self(db2_stmt_errormsg($statement), db2_stmt_error($statement));
        }

        return new self(db2_stmt_errormsg(), db2_stmt_error());
    }
}
