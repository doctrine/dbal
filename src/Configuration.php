<?php

namespace Doctrine\DBAL;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\Psr6\CacheAdapter;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\Deprecations\Deprecation;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Configuration container for the Doctrine DBAL.
 */
class Configuration
{
    /** @var Middleware[] */
    private $middlewares = [];

    /**
     * The SQL logger in use. If null, SQL logging is disabled.
     *
     * @var SQLLogger|null
     */
    protected $sqlLogger;

    /**
     * The cache driver implementation that is used for query result caching.
     *
     * @var CacheItemPoolInterface|null
     */
    private $resultCache;

    /**
     * The cache driver implementation that is used for query result caching.
     *
     * @deprecated Use {@see $resultCache} instead.
     *
     * @var Cache|null
     */
    protected $resultCacheImpl;

    /**
     * The callable to use to filter schema assets.
     *
     * @var callable|null
     */
    protected $schemaAssetsFilter;

    /**
     * The default auto-commit mode for connections.
     *
     * @var bool
     */
    protected $autoCommit = true;

    /**
     * Sets the SQL logger to use. Defaults to NULL which means SQL logging is disabled.
     */
    public function setSQLLogger(?SQLLogger $logger = null): void
    {
        $this->sqlLogger = $logger;
    }

    /**
     * Gets the SQL logger that is used.
     */
    public function getSQLLogger(): ?SQLLogger
    {
        return $this->sqlLogger;
    }

    /**
     * Gets the cache driver implementation that is used for query result caching.
     */
    public function getResultCache(): ?CacheItemPoolInterface
    {
        return $this->resultCache;
    }

    /**
     * Gets the cache driver implementation that is used for query result caching.
     *
     * @deprecated Use {@see getResultCache()} instead.
     */
    public function getResultCacheImpl(): ?Cache
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4620',
            '%s is deprecated, call getResultCache() instead.',
            __METHOD__
        );

        return $this->resultCacheImpl;
    }

    /**
     * Sets the cache driver implementation that is used for query result caching.
     */
    public function setResultCache(CacheItemPoolInterface $cache): void
    {
        $this->resultCacheImpl = DoctrineProvider::wrap($cache);
        $this->resultCache     = $cache;
    }

    /**
     * Sets the cache driver implementation that is used for query result caching.
     *
     * @deprecated Use {@see setResultCache()} instead.
     */
    public function setResultCacheImpl(Cache $cacheImpl): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4620',
            '%s is deprecated, call setResultCache() instead.',
            __METHOD__
        );

        $this->resultCacheImpl = $cacheImpl;
        $this->resultCache     = CacheAdapter::wrap($cacheImpl);
    }

    /**
     * Sets the callable to use to filter schema assets.
     */
    public function setSchemaAssetsFilter(?callable $callable = null): void
    {
        $this->schemaAssetsFilter = $callable;
    }

    /**
     * Returns the callable to use to filter schema assets.
     */
    public function getSchemaAssetsFilter(): ?callable
    {
        return $this->schemaAssetsFilter;
    }

    /**
     * Sets the default auto-commit mode for connections.
     *
     * If a connection is in auto-commit mode, then all its SQL statements will be executed and committed as individual
     * transactions. Otherwise, its SQL statements are grouped into transactions that are terminated by a call to either
     * the method commit or the method rollback. By default, new connections are in auto-commit mode.
     *
     * @see   getAutoCommit
     *
     * @param bool $autoCommit True to enable auto-commit mode; false to disable it
     */
    public function setAutoCommit(bool $autoCommit): void
    {
        $this->autoCommit = $autoCommit;
    }

    /**
     * Returns the default auto-commit mode for connections.
     *
     * @see    setAutoCommit
     *
     * @return bool True if auto-commit mode is enabled by default for connections, false otherwise.
     */
    public function getAutoCommit(): bool
    {
        return $this->autoCommit;
    }

    /**
     * @param Middleware[] $middlewares
     *
     * @return $this
     */
    public function setMiddlewares(array $middlewares): self
    {
        $this->middlewares = $middlewares;

        return $this;
    }

    /**
     * @return Middleware[]
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}
