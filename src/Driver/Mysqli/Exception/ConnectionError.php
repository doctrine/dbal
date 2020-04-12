<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Mysqli\MysqliException;
use mysqli;

final class ConnectionError extends MysqliException
{
    public static function new(mysqli $connection) : self
    {
        $connectionSQLState = $connection->sqlstate;

        $sqlState = null;
        if ($connectionSQLState !== false) {
            $sqlState = $connectionSQLState;
        }

        return new self($connection->error, $sqlState, $connection->errno);
    }
}
