<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Sharding\Exception;

use Doctrine\DBAL\Sharding\ShardingException;

final class MissingDefaultDistributionKey extends ShardingException
{
    public static function new() : self
    {
        return new self('SQLAzure requires a distribution key to be set during sharding configuration.', 1332141329);
    }
}
