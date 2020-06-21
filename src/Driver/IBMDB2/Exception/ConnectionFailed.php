<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2\Exception;

use Doctrine\DBAL\Driver\IBMDB2\DB2Exception;

use function db2_conn_error;
use function db2_conn_errormsg;

/**
 * @psalm-immutable
 */
final class ConnectionFailed extends DB2Exception
{
    public static function new(): self
    {
        return new self(db2_conn_errormsg(), db2_conn_error());
    }
}
