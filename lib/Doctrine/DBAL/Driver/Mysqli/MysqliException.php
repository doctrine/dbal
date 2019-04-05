<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\AbstractDriverException;
use mysqli;
use mysqli_stmt;

/**
 * Exception thrown in case the mysqli driver errors.
 */
class MysqliException extends AbstractDriverException
{
    public static function fromConnectionError(mysqli $connection) : self
    {
        return new self($connection->error, $connection->sqlstate ?: null, $connection->errno);
    }

    public static function fromStatementError(mysqli_stmt $statement) : self
    {
        return new self($statement->error, $statement->sqlstate ?: null, $statement->errno);
    }
}
