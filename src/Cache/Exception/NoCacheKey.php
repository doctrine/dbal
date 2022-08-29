<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Cache\Exception;

use Doctrine\DBAL\Cache\CacheException;

/** @psalm-immutable */
final class NoCacheKey extends CacheException
{
    public static function new(): self
    {
        return new self('No cache key was set.');
    }
}
