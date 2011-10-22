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

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Driver\ResultStatement;
use PDO;
use Doctrine\DBAL\Connection;

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
class RowCacheStatement implements ResultStatement
{
    /**
     * @var \Doctrine\Common\Cache\Cache
     */
    private $cache;

    /**
     *
     * @var string
     */
    private $cacheKey;

    /**
     * @var int
     */
    private $lifetime;

    /**
     * @var Doctrine\DBAL\Driver\Statement
     */
    private $statement;

    /**
     * @var array
     */
    private $rowPointers = array();

    /**
     * @var int
     */
    private $num = 0;

    /**
     * Did we reach the end of the statement?
     * 
     * @var bool
     */
    private $emptied = false;

    /**
     * @param Connection $conn
     * @param string $cacheKey
     * @param int|null $lifetime
     * @param string $query
     * @param array $params
     * @param array $types
     * @return RowCacheStatement
     */
    static public function create(Connection $conn, $cacheKey, $lifetime, $query, $params, $types)
    {
        $resultCache = $conn->getConfiguration()->getResultCacheImpl();
        if (!$resultCache) {
            return $conn->executeQuery($query, $params, $types);
        }

        if ($rowPointers = $resultCache->fetch($cacheKey)) {
            $data = array();
            foreach ($rowPointers AS $rowPointer) {
                if ($row = $resultCache->fetch($rowPointer)) {
                    $data[] = $row;
                } else {
                    return new self($conn->executeQuery($query, $params, $types), $resultCache, $cacheKey, $lifetime);
                }
            }
            return new ArrayStatement($data);
        }
        return new self($conn->executeQuery($query, $params, $types), $resultCache, $cacheKey, $lifetime);
    }

    /**
     *
     * @param Statement $stmt
     * @param Cache $resultCache
     * @param string $cacheKey
     * @param int $lifetime
     */
    public function __construct($stmt, $resultCache, $cacheKey, $lifetime = 0)
    {
        $this->statement = $stmt;
        $this->resultCache = $resultCache;
        $this->cacheKey = $cacheKey;
        $this->lifetime = $lifetime;
    }

    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return boolean              Returns TRUE on success or FALSE on failure.
     */
    public function closeCursor()
    {
        // the "important" key is written as the last one. This way we ensure it has a longer lifetime than the rest
        // avoiding potential cache "misses" during the reconstruction.
        if ($this->emptied && $this->rowPointers) {
            $this->resultCache->save($this->cacheKey, $this->rowPointers, $this->lifetime);
            unset($this->rowPointers);
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

    /**
     * fetch
     *
     * @see Query::HYDRATE_* constants
     * @param integer $fetchStyle           Controls how the next row will be returned to the caller.
     *                                      This value must be one of the Query::HYDRATE_* constants,
     *                                      defaulting to Query::HYDRATE_BOTH
     *
     * @param integer $cursorOrientation    For a PDOStatement object representing a scrollable cursor,
     *                                      this value determines which row will be returned to the caller.
     *                                      This value must be one of the Query::HYDRATE_ORI_* constants, defaulting to
     *                                      Query::HYDRATE_ORI_NEXT. To request a scrollable cursor for your
     *                                      PDOStatement object,
     *                                      you must set the PDO::ATTR_CURSOR attribute to Doctrine::CURSOR_SCROLL when you
     *                                      prepare the SQL statement with Doctrine_Adapter_Interface->prepare().
     *
     * @param integer $cursorOffset         For a PDOStatement object representing a scrollable cursor for which the
     *                                      $cursorOrientation parameter is set to Query::HYDRATE_ORI_ABS, this value specifies
     *                                      the absolute number of the row in the result set that shall be fetched.
     *
     *                                      For a PDOStatement object representing a scrollable cursor for
     *                                      which the $cursorOrientation parameter is set to Query::HYDRATE_ORI_REL, this value
     *                                      specifies the row to fetch relative to the cursor position before
     *                                      PDOStatement->fetch() was called.
     *
     * @return mixed
     */
    public function fetch($fetchStyle = PDO::FETCH_BOTH)
    {
        $row = $this->statement->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $rowCacheKey = $this->cacheKey . "#row". ($this->num++);
            $this->rowPointers[] = $rowCacheKey;
            $this->resultCache->save($rowCacheKey, $row, $this->lifetime);
            if ($fetchStyle == PDO::FETCH_ASSOC) {
                return $row;
            } else if ($fetchStyle == PDO::FETCH_NUM) {
                return array_values($row);
            } else if ($fetchStyle == PDO::FETCH_BOTH) {
                return array_merge($row, array_values($row));
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
     * @param integer $fetchStyle           Controls how the next row will be returned to the caller.
     *                                      This value must be one of the Query::HYDRATE_* constants,
     *                                      defaulting to Query::HYDRATE_BOTH
     *
     * @param integer $columnIndex          Returns the indicated 0-indexed column when the value of $fetchStyle is
     *                                      Query::HYDRATE_COLUMN. Defaults to 0.
     *
     * @return array
     */
    public function fetchAll($fetchStyle = PDO::FETCH_BOTH)
    {
        $rows = array();
        while ($row = $this->fetch($fetchStyle)) {
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