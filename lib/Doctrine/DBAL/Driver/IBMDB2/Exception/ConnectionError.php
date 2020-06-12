<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2\Exception;

use Doctrine\DBAL\Driver\IBMDB2\DB2Exception;

use function db2_conn_error;
use function db2_conn_errormsg;

/**
 * @psalm-immutable
 */
final class ConnectionError extends DB2Exception
{
    /**
     * @param resource $connection
     */
    public static function new($connection): self
    {
        return new self(db2_conn_errormsg($connection), db2_conn_error($connection));
    }
}
