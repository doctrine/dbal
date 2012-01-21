<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL;

use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\Common\Cache\Cache;

/**
 * Configuration container for the Doctrine DBAL.
 *
 * @since   2.0
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
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
    protected $_attributes = array();

    /**
     * Sets the SQL logger to use. Defaults to NULL which means SQL logging is disabled.
     *
     * @param SQLLogger $logger
     */
    public function setSQLLogger(SQLLogger $logger = null)
    {
        $this->_attributes['sqlLogger'] = $logger;
    }

    /**
     * Gets the SQL logger that is used.
     *
     * @return SQLLogger
     */
    public function getSQLLogger()
    {
        return isset($this->_attributes['sqlLogger']) ?
                $this->_attributes['sqlLogger'] : null;
    }

    /**
     * Gets the cache driver implementation that is used for query result caching.
     *
     * @return \Doctrine\Common\Cache\Cache
     */
    public function getResultCacheImpl()
    {
        return isset($this->_attributes['resultCacheImpl']) ?
                $this->_attributes['resultCacheImpl'] : null;
    }

    /**
     * Sets the cache driver implementation that is used for query result caching.
     *
     * @param \Doctrine\Common\Cache\Cache $cacheImpl
     */
    public function setResultCacheImpl(Cache $cacheImpl)
    {
        $this->_attributes['resultCacheImpl'] = $cacheImpl;
    }

    /**
     * Filter schema assets expression.
     *
     * Only include tables/sequences matching the filter expression regexp in
     * schema instances generated for the active connection when calling
     * {AbstractSchemaManager#createSchema()}.
     *
     * @param string $filterExpression
     */
    public function setFilterSchemaAssetsExpression($filterExpression)
    {
        $this->_attributes['filterSchemaAssetsExpression'] = $filterExpression;
    }

    /**
     * Return filter schema assets expression.
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
}
