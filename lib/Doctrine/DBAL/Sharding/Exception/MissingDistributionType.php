<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Sharding\Exception;

use Doctrine\DBAL\Sharding\ShardingException;

final class MissingDistributionType extends ShardingException
{
    public static function new() : self
    {
        return new self("You have to specify a sharding distribution type such as 'integer', 'string', 'guid'.");
    }
}
