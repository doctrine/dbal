<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Mysqli\MysqliException;
use mysqli_stmt;

final class StatementError extends MysqliException
{
    public static function new(mysqli_stmt $statement) : self
    {
        return new self($statement->error, $statement->sqlstate ?: null, $statement->errno);
    }
}
