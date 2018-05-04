<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Sharding\Exception;

use Doctrine\DBAL\Sharding\ShardingException;

final class NotImplemented extends ShardingException
{
    public static function new() : self
    {
        return new self('This functionality is not implemented with this sharding provider.', 1331557937);
    }
}
