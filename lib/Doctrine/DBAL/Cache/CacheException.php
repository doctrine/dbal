<?php

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\DBALException;

/**
 * @psalm-immutable
 */
class CacheException extends DBALException
{
    /**
     * @return CacheException
     */
    public static function noCacheKey()
    {
        return new self('No cache key was set.');
    }

    /**
     * @return CacheException
     */
    public static function noResultDriverConfigured()
    {
        return new self('Trying to cache a query but no result driver is configured.');
    }
}
