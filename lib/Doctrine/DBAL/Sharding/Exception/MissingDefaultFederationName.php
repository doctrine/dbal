<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Sharding\Exception;

use Doctrine\DBAL\Sharding\ShardingException;

final class MissingDefaultFederationName extends ShardingException
{
    public static function new() : self
    {
        return new self('SQLAzure requires a federation name to be set during sharding configuration.', 1332141280);
    }
}
