<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Sharding\Exception;

use Doctrine\DBAL\Sharding\ShardingException;

final class ActiveTransaction extends ShardingException
{
    public static function new() : self
    {
        return new self('Cannot switch shard during an active transaction.', 1332141766);
    }
}
