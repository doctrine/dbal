<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Sharding\Exception;

use Doctrine\DBAL\Sharding\ShardingException;

final class NoShardDistributionValue extends ShardingException
{
    public static function new() : self
    {
        return new self('You have to specify a string or integer as shard distribution value.', 1332142103);
    }
}
