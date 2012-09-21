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
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Connection;
use Doctrine\Common\Cache\Cache;
use PDO;

/**
 * Cache statement for SQL results.
 *
 * A result is saved in multiple cache keys, there is the originally specified
 * cache key which is just pointing to result rows by key. The following things
 * have to be ensured:
 *
 * 1. lifetime of the original key has to be longer than that of all the individual rows keys
 * 2. if any one row key is missing the query has to be re-executed.
 *
 * Also you have to realize that the cache will load the whole result into memory at once to ensure 2.
 * This means that the memory usage for cached results might increase by using this feature.
 */
class ResultCacheStatement implements \IteratorAggregate, ResultStatement
{
    /**
     * @var \Doctrine\Common\Cache\Cache
     */
    private $resultCache;

    /**
     *
     * @var string
     */
    private $cacheKey;

    /**
     * @var string
     */
    private $realKey;

    /**
     * @var int
     */
    private $lifetime;

    /**
     * @var \Doctrine\DBAL\Driver\Statement
     */
    private $statement;

    /**
     * Did we reach the end of the statement?
     *
     * @var bool
     */
    private $emptied = false;

    /**
     * @var array
     */
    private $data;

    /**
     * @var int
     */
    private $defaultFetchMode = PDO::FETCH_BOTH;

    /**
     * @param Statement $stmt
     * @param Cache $resultCache
     * @param string $cacheKey
     * @param string $realKey
     * @param int $lifetime
     */
    public function __construct(Statement $stmt, Cache $resultCache, $cacheKey, $realKey, $lifetime)
    {
        $this->statement = $stmt;
        $this->resultCache = $resultCache;
        $this->cacheKey = $cacheKey;
        $this->realKey = $realKey;
        $this->lifetime = $lifetime;
    }

    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return boolean              Returns TRUE on success or FALSE on failure.
     */
    public function closeCursor()
    {
        $this->statement->closeCursor();
        if ($this->emptied && $this->data !== null) {
            $data = $this->resultCache->fetch($this->cacheKey);
            if ( ! $data) {
                $data = array();
            }
            $data[$this->realKey] = $this->data;

            $this->resultCache->save($this->cacheKey, $data, $this->lifetime);
            unset($this->data);
        }
    }

    /**
     * columnCount
     * Returns the number of columns in the result set
     *
     * @return integer              Returns the number of columns in the result set represented
     *                              by the PDOStatement object. If there is no result set,
     *                              this method should return 0.
     */
    public function columnCount()
    {
        return $this->statement->columnCount();
    }

    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->defaultFetchMode = $fetchMode;
    }

    public function getIterator()
    {
        $data = $this->fetchAll();
        return new \ArrayIterator($data);
    }

    /**
     * fetch
     *
     * @see Query::HYDRATE_* constants
     * @param integer $fetchMode            Controls how the next row will be returned to the caller.
     *                                      This value must be one of the Query::HYDRATE_* constants,
     *                                      defaulting to Query::HYDRATE_BOTH
     *
     * @return mixed
     */
    public function fetch($fetchMode = null)
    {
        if ($this->data === null) {
            $this->data = array();
        }

        $row = $this->statement->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->data[] = $row;

            $fetchMode = $fetchMode ?: $this->defaultFetchMode;

            if ($fetchMode == PDO::FETCH_ASSOC) {
                return $row;
            } else if ($fetchMode == PDO::FETCH_NUM) {
                return array_values($row);
            } else if ($fetchMode == PDO::FETCH_BOTH) {
                return array_merge($row, array_values($row));
            } else if ($fetchMode == PDO::FETCH_COLUMN) {
                return reset($row);
            } else {
                throw new \InvalidArgumentException("Invalid fetch-style given for caching result.");
            }
        }
        $this->emptied = true;
        return false;
    }

    /**
     * Returns an array containing all of the result set rows
     *
     * @param integer $fetchMode            Controls how the next row will be returned to the caller.
     *                                      This value must be one of the Query::HYDRATE_* constants,
     *                                      defaulting to Query::HYDRATE_BOTH
     *
     * @return array
     */
    public function fetchAll($fetchMode = null)
    {
        $rows = array();
        while ($row = $this->fetch($fetchMode)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * fetchColumn
     * Returns a single column from the next row of a
     * result set or FALSE if there are no more rows.
     *
     * @param integer $columnIndex          0-indexed number of the column you wish to retrieve from the row. If no
     *                                      value is supplied, PDOStatement->fetchColumn()
     *                                      fetches the first column.
     *
     * @return string                       returns a single column in the next row of a result set.
     */
    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(PDO::FETCH_NUM);
        if (!isset($row[$columnIndex])) {
            // TODO: verify this is correct behavior
            return false;
        }
        return $row[$columnIndex];
    }

    /**
     * rowCount
     * rowCount() returns the number of rows affected by the last DELETE, INSERT, or UPDATE statement
     * executed by the corresponding object.
     *
     * If the last SQL statement executed by the associated Statement object was a SELECT statement,
     * some databases may return the number of rows returned by that statement. However,
     * this behaviour is not guaranteed for all databases and should not be
     * relied on for portable applications.
     *
     * @return integer                      Returns the number of rows.
     */
    public function rowCount()
    {
        return $this->statement->rowCount();
    }
}
