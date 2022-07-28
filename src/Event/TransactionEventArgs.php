<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\Common\EventArgs;
use Doctrine\DBAL\Connection;

abstract class TransactionEventArgs extends EventArgs
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }
}
