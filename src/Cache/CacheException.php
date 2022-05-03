<?php

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Exception;

/**
 * @psalm-immutable
 */
class CacheException extends Exception
{
    public static function noCacheKey(): CacheException
    {
        return new self('No cache key was set.');
    }

    public static function noResultDriverConfigured(): CacheException
    {
        return new self('Trying to cache a query but no result driver is configured.');
    }
}
