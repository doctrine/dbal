<?php

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\FetchMode;

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
     * @var integer
     */
    private $lifetime;

    /**
     * @var \Doctrine\DBAL\Driver\Statement
     */
    private $statement;

    /**
     * Did we reach the end of the statement?
     *
     * @var boolean
     */
    private $emptied = false;

    /**
     * @var array
     */
    private $data;

    /**
     * @var integer
     */
    private $defaultFetchMode = FetchMode::MIXED;

    /**
     * @param \Doctrine\DBAL\Driver\Statement $stmt
     * @param \Doctrine\Common\Cache\Cache    $resultCache
     * @param string                          $cacheKey
     * @param string                          $realKey
     * @param integer                         $lifetime
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
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        $this->statement->closeCursor();
        if ($this->emptied && $this->data !== null) {
            $data = $this->resultCache->fetch($this->cacheKey);
            if ( ! $data) {
                $data = [];
            }
            $data[$this->realKey] = $this->data;

            $this->resultCache->save($this->cacheKey, $data, $this->lifetime);
            unset($this->data);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->statement->columnCount();
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, ...$args)
    {
        $this->defaultFetchMode = $fetchMode;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $data = $this->fetchAll();

        return new \ArrayIterator($data);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, ...$args)
    {
        if ($this->data === null) {
            $this->data = [];
        }

        $row = $this->statement->fetch(FetchMode::ASSOCIATIVE);

        if ($row) {
            $this->data[] = $row;

            $fetchMode = $fetchMode ?: $this->defaultFetchMode;

            if ($fetchMode == FetchMode::ASSOCIATIVE) {
                return $row;
            }

            if ($fetchMode == FetchMode::NUMERIC) {
                return array_values($row);
            }

            if ($fetchMode == FetchMode::MIXED) {
                return array_merge($row, array_values($row));
            }

            if ($fetchMode == FetchMode::COLUMN) {
                return reset($row);
            }

            throw new \InvalidArgumentException('Invalid fetch-style given for caching result.');
        }

        $this->emptied = true;

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, ...$args)
    {
        $rows = [];
        while ($row = $this->fetch($fetchMode, ...$args)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(FetchMode::NUMERIC);

        if (!isset($row[$columnIndex])) {
            // TODO: verify this is correct behavior
            return false;
        }

        return $row[$columnIndex];
    }

    /**
     * Returns the number of rows affected by the last DELETE, INSERT, or UPDATE statement
     * executed by the corresponding object.
     *
     * If the last SQL statement executed by the associated Statement object was a SELECT statement,
     * some databases may return the number of rows returned by that statement. However,
     * this behaviour is not guaranteed for all databases and should not be
     * relied on for portable applications.
     *
     * @return integer The number of rows.
     */
    public function rowCount()
    {
        return $this->statement->rowCount();
    }
}
