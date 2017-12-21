<?php

namespace Doctrine\DBAL\Cache;

use Doctrine\Common\Cache\Cache;

/**
 * Query Cache Profile handles the data relevant for query caching.
 *
 * It is a value object, setter methods return NEW instances.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class QueryCacheProfile
{
    /**
     * @var \Doctrine\Common\Cache\Cache|null
     */
    private $resultCacheDriver;

    /**
     * @var integer
     */
    private $lifetime = 0;

    /**
     * @var string|null
     */
    private $cacheKey;

    /**
     * @param integer                           $lifetime
     * @param string|null                       $cacheKey
     * @param \Doctrine\Common\Cache\Cache|null $resultCache
     */
    public function __construct($lifetime = 0, $cacheKey = null, Cache $resultCache = null)
    {
        $this->lifetime = $lifetime;
        $this->cacheKey = $cacheKey;
        $this->resultCacheDriver = $resultCache;
    }

    /**
     * @return \Doctrine\Common\Cache\Cache|null
     */
    public function getResultCacheDriver()
    {
        return $this->resultCacheDriver;
    }

    /**
     * @return integer
     */
    public function getLifetime()
    {
        return $this->lifetime;
    }

    /**
     * @return string
     *
     * @throws \Doctrine\DBAL\Cache\CacheException
     */
    public function getCacheKey()
    {
        if ($this->cacheKey === null) {
            throw CacheException::noCacheKey();
        }

        return $this->cacheKey;
    }

    /**
     * Generates the real cache key from query, params, types and connection parameters.
     *
     * @param string $query
     * @param array  $params
     * @param array  $types
     * @param array  $connectionParams
     *
     * @return array
     */
    public function generateCacheKeys($query, $params, $types, array $connectionParams = [])
    {
        $realCacheKey = 'query=' . $query .
            '&params=' . serialize($params) .
            '&types=' . serialize($types) .
            '&connectionParams=' . serialize($connectionParams);

        // should the key be automatically generated using the inputs or is the cache key set?
        if ($this->cacheKey === null) {
            $cacheKey = sha1($realCacheKey);
        } else {
            $cacheKey = $this->cacheKey;
        }

        return array($cacheKey, $realCacheKey);
    }

    /**
     * @param \Doctrine\Common\Cache\Cache $cache
     *
     * @return \Doctrine\DBAL\Cache\QueryCacheProfile
     */
    public function setResultCacheDriver(Cache $cache)
    {
        return new QueryCacheProfile($this->lifetime, $this->cacheKey, $cache);
    }

    /**
     * @param string|null $cacheKey
     *
     * @return \Doctrine\DBAL\Cache\QueryCacheProfile
     */
    public function setCacheKey($cacheKey)
    {
        return new QueryCacheProfile($this->lifetime, $cacheKey, $this->resultCacheDriver);
    }

    /**
     * @param integer $lifetime
     *
     * @return \Doctrine\DBAL\Cache\QueryCacheProfile
     */
    public function setLifetime($lifetime)
    {
        return new QueryCacheProfile($lifetime, $this->cacheKey, $this->resultCacheDriver);
    }
}
