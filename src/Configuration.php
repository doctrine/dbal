<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Schema\SchemaManagerFactory;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Configuration container for the Doctrine DBAL.
 */
class Configuration
{
    /** @var Middleware[] */
    private array $middlewares = [];

    /**
     * The cache driver implementation that is used for query result caching.
     */
    private ?CacheItemPoolInterface $resultCache = null;

    /**
     * The callable to use to filter schema assets.
     *
     * @var callable
     */
    protected $schemaAssetsFilter;

    /**
     * The default auto-commit mode for connections.
     */
    protected bool $autoCommit = true;

    private ?SchemaManagerFactory $schemaManagerFactory = null;

    public function __construct()
    {
        $this->schemaAssetsFilter = static function (): bool {
            return true;
        };
    }

    /**
     * Gets the cache driver implementation that is used for query result caching.
     */
    public function getResultCache(): ?CacheItemPoolInterface
    {
        return $this->resultCache;
    }

    /**
     * Sets the cache driver implementation that is used for query result caching.
     */
    public function setResultCache(CacheItemPoolInterface $cache): void
    {
        $this->resultCache = $cache;
    }

    /**
     * Sets the callable to use to filter schema assets.
     */
    public function setSchemaAssetsFilter(callable $schemaAssetsFilter): void
    {
        $this->schemaAssetsFilter = $schemaAssetsFilter;
    }

    /**
     * Returns the callable to use to filter schema assets.
     */
    public function getSchemaAssetsFilter(): callable
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
     * @see getAutoCommit
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

    /** @return Middleware[] */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function getSchemaManagerFactory(): ?SchemaManagerFactory
    {
        return $this->schemaManagerFactory;
    }

    /** @return $this */
    public function setSchemaManagerFactory(SchemaManagerFactory $schemaManagerFactory): self
    {
        $this->schemaManagerFactory = $schemaManagerFactory;

        return $this;
    }

    public function getDisableTypeComments(): bool
    {
        return true;
    }

    /** @return $this */
    public function setDisableTypeComments(bool $disableTypeComments): self
    {
        if (! $disableTypeComments) {
            throw new InvalidArgumentException('Column comments cannot be enabled anymore.');
        }

        return $this;
    }
}
