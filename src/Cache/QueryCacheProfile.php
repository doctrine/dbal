<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Cache;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Cache\Exception\NoCacheKey;

use function hash;
use function serialize;
use function sha1;

/**
 * Query Cache Profile handles the data relevant for query caching.
 *
 * It is a value object, setter methods return NEW instances.
 */
class QueryCacheProfile
{
    /** @var Cache|null */
    private $resultCacheDriver;

    /** @var int */
    private $lifetime = 0;

    /** @var string|null */
    private $cacheKey;

    public function __construct(int $lifetime = 0, ?string $cacheKey = null, ?Cache $resultCache = null)
    {
        $this->lifetime          = $lifetime;
        $this->cacheKey          = $cacheKey;
        $this->resultCacheDriver = $resultCache;
    }

    public function getResultCacheDriver(): ?Cache
    {
        return $this->resultCacheDriver;
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
            throw NoCacheKey::new();
        }

        return $this->cacheKey;
    }

    /**
     * Generates the real cache key from query, params, types and connection parameters.
     *
     * @param mixed[]        $params
     * @param int[]|string[] $types
     * @param mixed[]        $connectionParams
     *
     * @return string[]
     */
    public function generateCacheKeys(string $query, array $params, array $types, array $connectionParams = []): array
    {
        $realCacheKey = 'query=' . $query .
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

    public function setResultCacheDriver(Cache $cache): self
    {
        return new QueryCacheProfile($this->lifetime, $this->cacheKey, $cache);
    }

    public function setCacheKey(?string $cacheKey): self
    {
        return new QueryCacheProfile($this->lifetime, $cacheKey, $this->resultCacheDriver);
    }

    public function setLifetime(int $lifetime): self
    {
        return new QueryCacheProfile($lifetime, $this->cacheKey, $this->resultCacheDriver);
    }
}
