<?php

namespace Doctrine\DBAL;

use Doctrine\DBAL\Logging\NullLogger;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\Common\Cache\Cache;

/**
 * Configuration container for the Doctrine DBAL.
 *
 * @since    2.0
 * @author   Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author   Jonathan Wage <jonwage@gmail.com>
 * @author   Roman Borschel <roman@code-factory.org>
 * @internal When adding a new configuration option just write a getter/setter
 *           pair and add the option to the _attributes array with a proper default value.
 */
class Configuration
{
    /**
     * The attributes that are contained in the configuration.
     * Values are default values.
     *
     * @var array
     */
    protected $_attributes = [];

    /**
     * Sets the SQL logger to use.
     */
    public function setSQLLogger(SQLLogger $logger) : void
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
     *
     * @return \Doctrine\Common\Cache\Cache|null
     */
    public function getResultCacheImpl()
    {
        return $this->_attributes['resultCacheImpl'] ?? null;
    }

    /**
     * Sets the cache driver implementation that is used for query result caching.
     *
     * @param \Doctrine\Common\Cache\Cache $cacheImpl
     *
     * @return void
     */
    public function setResultCacheImpl(Cache $cacheImpl)
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
     * @param string $filterExpression
     *
     * @return void
     */
    public function setFilterSchemaAssetsExpression($filterExpression)
    {
        $this->_attributes['filterSchemaAssetsExpression'] = $filterExpression;
    }

    /**
     * Returns filter schema assets expression.
     *
     * @return string|null
     */
    public function getFilterSchemaAssetsExpression()
    {
        return $this->_attributes['filterSchemaAssetsExpression'] ?? null;
    }

    /**
     * Sets the default auto-commit mode for connections.
     *
     * If a connection is in auto-commit mode, then all its SQL statements will be executed and committed as individual
     * transactions. Otherwise, its SQL statements are grouped into transactions that are terminated by a call to either
     * the method commit or the method rollback. By default, new connections are in auto-commit mode.
     *
     * @param bool $autoCommit True to enable auto-commit mode; false to disable it.
     *
     * @see   getAutoCommit
     */
    public function setAutoCommit($autoCommit)
    {
        $this->_attributes['autoCommit'] = (boolean) $autoCommit;
    }

    /**
     * Returns the default auto-commit mode for connections.
     *
     * @return bool True if auto-commit mode is enabled by default for connections, false otherwise.
     *
     * @see    setAutoCommit
     */
    public function getAutoCommit()
    {
        return $this->_attributes['autoCommit'] ?? true;
    }
}
