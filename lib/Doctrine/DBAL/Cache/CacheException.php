<?php

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\DBALException;

class CacheException extends DBALException
{
    // Exception codes. Dedicated 300-399 numbers
    public const NO_CACHE_KEY                = 300;
    public const NO_RESULT_DRIVER_CONFIGURED = 310;

    /**
     * @return \Doctrine\DBAL\Cache\CacheException
     */
    public static function noCacheKey()
    {
        return new self('No cache key was set.', self::NO_CACHE_KEY);
    }

    /**
     * @return \Doctrine\DBAL\Cache\CacheException
     */
    public static function noResultDriverConfigured()
    {
        return new self(
            'Trying to cache a query but no result driver is configured.',
            self::NO_RESULT_DRIVER_CONFIGURED
        );
    }
}
