<?php

namespace Doctrine\DBAL\Cache;

/**
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @since 2.2
 */
class CacheException extends \Doctrine\DBAL\DBALException
{
    /**
     * @return \Doctrine\DBAL\Cache\CacheException
     */
    static public function noCacheKey()
    {
        return new self("No cache key was set.");
    }

    /**
     * @return \Doctrine\DBAL\Cache\CacheException
     */
    static public function noResultDriverConfigured()
    {
        return new self("Trying to cache a query but no result driver is configured.");
    }
}
