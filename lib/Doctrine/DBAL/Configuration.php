<?php

namespace Doctrine\DBAL;

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
     * Sets the SQL logger to use. Defaults to NULL which means SQL logging is disabled.
     *
     * @param \Doctrine\DBAL\Logging\SQLLogger|null $logger
     *
     * @return void
     */
    public function setSQLLogger(SQLLogger $logger = null)
    {
        $this->_attributes['sqlLogger'] = $logger;
    }

    /**
     * Gets the SQL logger that is used.
     *
     * @return \Doctrine\DBAL\Logging\SQLLogger|null
     */
    public function getSQLLogger()
    {
        return $this->_attributes['sqlLogger'] ?? null;
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
        if (isset($this->_attributes['filterSchemaAssetsExpression'])) {
            return $this->_attributes['filterSchemaAssetsExpression'];
        }

        return null;
    }

    /**
     * Sets the default auto-commit mode for connections.
     *
     * If a connection is in auto-commit mode, then all its SQL statements will be executed and committed as individual
     * transactions. Otherwise, its SQL statements are grouped into transactions that are terminated by a call to either
     * the method commit or the method rollback. By default, new connections are in auto-commit mode.
     *
     * @param boolean $autoCommit True to enable auto-commit mode; false to disable it.
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
     * @return boolean True if auto-commit mode is enabled by default for connections, false otherwise.
     *
     * @see    setAutoCommit
     */
    public function getAutoCommit()
    {
        if (isset($this->_attributes['autoCommit'])) {
            return $this->_attributes['autoCommit'];
        }

        return true;
    }
}
