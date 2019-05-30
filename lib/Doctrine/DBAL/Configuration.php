<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Logging\NullLogger;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Schema\AbstractAsset;
use function preg_match;

/**
 * Configuration container for the Doctrine DBAL.
 *
 * @internal When adding a new configuration option just write a getter/setter
 *           pair and add the option to the _attributes array with a proper default value.
 */
class Configuration
{
    /**
     * The attributes that are contained in the configuration.
     * Values are default values.
     *
     * @var mixed[]
     */
    protected $_attributes = [];

    /**
     * Sets the SQL logger to use.
     */
    public function setSQLLogger(?SQLLogger $logger) : void
    {
        $this->_attributes['sqlLogger'] = $logger;
    }

    /**
     * Gets the SQL logger that is used.
     */
    public function getSQLLogger() : SQLLogger
    {
        return $this->_attributes['sqlLogger'] ?? $this->_attributes['sqlLogger'] = new NullLogger();
    }

    /**
     * Gets the cache driver implementation that is used for query result caching.
     */
    public function getResultCacheImpl() : ?Cache
    {
        return $this->_attributes['resultCacheImpl'] ?? null;
    }

    /**
     * Sets the cache driver implementation that is used for query result caching.
     */
    public function setResultCacheImpl(Cache $cacheImpl) : void
    {
        $this->_attributes['resultCacheImpl'] = $cacheImpl;
    }

    /**
     * Sets the filter schema assets expression.
     *
     * Only include tables/sequences matching the filter expression regexp in
     * schema instances generated for the active connection when calling
     * {AbstractSchemaManager#createSchema()}.
     *
     * @deprecated Use Configuration::setSchemaAssetsFilter() instead
     */
    public function setFilterSchemaAssetsExpression(?string $filterExpression) : void
    {
        $this->_attributes['filterSchemaAssetsExpression'] = $filterExpression;
        if ($filterExpression) {
            $this->_attributes['filterSchemaAssetsExpressionCallable'] = $this->buildSchemaAssetsFilterFromExpression($filterExpression);
        } else {
            $this->_attributes['filterSchemaAssetsExpressionCallable'] = null;
        }
    }

    private function buildSchemaAssetsFilterFromExpression(string $filterExpression) : callable
    {
        return static function ($assetName) use ($filterExpression) : bool {
            if ($assetName instanceof AbstractAsset) {
                $assetName = $assetName->getName();
            }

            return preg_match($filterExpression, $assetName) > 0;
        };
    }

    /**
     * Sets the callable to use to filter schema assets.
     */
    public function setSchemaAssetsFilter(?callable $callable = null) : ?callable
    {
        $this->_attributes['filterSchemaAssetsExpression'] = null;

        return $this->_attributes['filterSchemaAssetsExpressionCallable'] = $callable;
    }

    /**
     * Returns the callable to use to filter schema assets.
     */
    public function getSchemaAssetsFilter() : ?callable
    {
        return $this->_attributes['filterSchemaAssetsExpressionCallable'] ?? null;
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
     * @param bool $autoCommit True to enable auto-commit mode; false to disable it.
     */
    public function setAutoCommit(bool $autoCommit) : void
    {
        $this->_attributes['autoCommit'] = $autoCommit;
    }

    /**
     * Returns the default auto-commit mode for connections.
     *
     * @see    setAutoCommit
     *
     * @return bool True if auto-commit mode is enabled by default for connections, false otherwise.
     */
    public function getAutoCommit() : bool
    {
        return $this->_attributes['autoCommit'] ?? true;
    }
}
