<?php

namespace Doctrine\DBAL\Cache;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\Psr6\CacheAdapter;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
use Psr\Cache\CacheItemPoolInterface;
use TypeError;

use function get_class;
use function hash;
use function serialize;
use function sha1;
use function sprintf;

/**
 * Query Cache Profile handles the data relevant for query caching.
 *
 * It is a value object, setter methods return NEW instances.
 */
class QueryCacheProfile
{
    /** @var CacheItemPoolInterface|null */
    private $resultCache;

    /** @var int */
    private $lifetime;

    /** @var string|null */
    private $cacheKey;

    /**
     * @param int                               $lifetime
     * @param string|null                       $cacheKey
     * @param CacheItemPoolInterface|Cache|null $resultCache
     */
    public function __construct($lifetime = 0, $cacheKey = null, ?object $resultCache = null)
    {
        $this->lifetime = $lifetime;
        $this->cacheKey = $cacheKey;
        if ($resultCache instanceof CacheItemPoolInterface) {
            $this->resultCache = $resultCache;
        } elseif ($resultCache instanceof Cache) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/4620',
                'Passing an instance of %s to %s as $resultCache is deprecated. Pass an instance of %s instead.',
                Cache::class,
                __METHOD__,
                CacheItemPoolInterface::class
            );

            $this->resultCache = CacheAdapter::wrap($resultCache);
        } elseif ($resultCache !== null) {
            throw new TypeError(sprintf(
                '$resultCache: Expected either null or an instance of %s or %s, got %s.',
                CacheItemPoolInterface::class,
                Cache::class,
                get_class($resultCache)
            ));
        }
    }

    public function getResultCache(): ?CacheItemPoolInterface
    {
        return $this->resultCache;
    }

    /**
     * @deprecated Use {@see getResultCache()} instead.
     */
    public function getResultCacheDriver(): ?Cache
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4620',
            '%s is deprecated, call getResultCache() instead.',
            __METHOD__
        );

        return $this->resultCache !== null ? DoctrineProvider::wrap($this->resultCache) : null;
    }

    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    /**
     * @throws CacheException
     */
    public function getCacheKey(): string
    {
        if ($this->cacheKey === null) {
            throw CacheException::noCacheKey();
        }

        return $this->cacheKey;
    }

    /**
     * Generates the real cache key from query, params, types and connection parameters.
     *
     * @param string                                                               $sql
     * @param list<mixed>|array<string, mixed>                                     $params
     * @param array<int, Type|int|string|null>|array<string, Type|int|string|null> $types
     * @param array<string, mixed>                                                 $connectionParams
     *
     * @return string[]
     */
    public function generateCacheKeys($sql, $params, $types, array $connectionParams = []): array
    {
        if (isset($connectionParams['password'])) {
            unset($connectionParams['password']);
        }

        $realCacheKey = 'query=' . $sql .
            '&params=' . serialize($params) .
            '&types=' . serialize($types) .
            '&connectionParams=' . hash('sha256', serialize($connectionParams));

        // should the key be automatically generated using the inputs or is the cache key set?
        if ($this->cacheKey === null) {
            $cacheKey = sha1($realCacheKey);
        } else {
            $cacheKey = $this->cacheKey;
        }

        return [$cacheKey, $realCacheKey];
    }

    public function setResultCache(CacheItemPoolInterface $cache): QueryCacheProfile
    {
        return new QueryCacheProfile($this->lifetime, $this->cacheKey, $cache);
    }

    /**
     * @deprecated Use {@see setResultCache()} instead.
     */
    public function setResultCacheDriver(Cache $cache): QueryCacheProfile
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4620',
            '%s is deprecated, call setResultCache() instead.',
            __METHOD__
        );

        return new QueryCacheProfile($this->lifetime, $this->cacheKey, CacheAdapter::wrap($cache));
    }

    /**
     * @param string|null $cacheKey
     */
    public function setCacheKey($cacheKey): QueryCacheProfile
    {
        return new QueryCacheProfile($this->lifetime, $cacheKey, $this->resultCache);
    }

    /**
     * @param int $lifetime
     */
    public function setLifetime($lifetime): QueryCacheProfile
    {
        return new QueryCacheProfile($lifetime, $this->cacheKey, $this->resultCache);
    }
}
