<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Cache\Exception\NoCacheKey;
use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;

use function hash;
use function serialize;
use function sha1;

/**
 * Query Cache Profile handles the data relevant for query caching.
 *
 * It is a value object, setter methods return NEW instances.
 *
 * @phpstan-import-type WrapperParameterType from Connection
 */
class QueryCacheProfile
{
    public function __construct(
        private readonly int $lifetime = 0,
        private readonly ?string $cacheKey = null,
        private readonly ?CacheItemPoolInterface $resultCache = null,
    ) {
    }

    public function getResultCache(): ?CacheItemPoolInterface
    {
        return $this->resultCache;
    }

    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    /** @throws CacheException */
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
     * @param list<mixed>|array<string, mixed> $params
     * @param array<string, mixed>             $connectionParams
     * @phpstan-param array<int, WrapperParameterType>|array<string, WrapperParameterType> $types
     *
     * @return array{string, string}
     */
    public function generateCacheKeys(string $sql, array $params, array $types, array $connectionParams = []): array
    {
        if (isset($connectionParams['password'])) {
            unset($connectionParams['password']);
        }

        $realCacheKey = 'query=' . $sql .
            '&params=' . serialize($params) .
            '&types=' . serialize($types) .
            '&connectionParams=' . hash('sha256', serialize($connectionParams));

        // should the key be automatically generated using the inputs or is the cache key set?
        $cacheKey = $this->cacheKey ?? sha1($realCacheKey);

        return [$cacheKey, $realCacheKey];
    }

    public function setResultCache(CacheItemPoolInterface $cache): QueryCacheProfile
    {
        return new QueryCacheProfile($this->lifetime, $this->cacheKey, $cache);
    }

    public function setCacheKey(?string $cacheKey): self
    {
        return new QueryCacheProfile($this->lifetime, $cacheKey, $this->resultCache);
    }

    public function setLifetime(int $lifetime): self
    {
        return new QueryCacheProfile($lifetime, $this->cacheKey, $this->resultCache);
    }
}
