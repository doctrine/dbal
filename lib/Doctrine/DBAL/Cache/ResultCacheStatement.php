<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Cache;

use ArrayIterator;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use Doctrine\DBAL\FetchMode;
use InvalidArgumentException;
use IteratorAggregate;
use function array_key_exists;
use function array_merge;
use function array_values;
use function count;
use function reset;

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
final class ResultCacheStatement implements IteratorAggregate, ResultStatement
{
    /** @var Cache */
    private $resultCache;

    /** @var string */
    private $cacheKey;

    /** @var string */
    private $realKey;

    /** @var int */
    private $lifetime;

    /** @var ResultStatement */
    private $statement;

    /**
     * Did we reach the end of the statement?
     *
     * @var bool
     */
    private $emptied = false;

    /** @var mixed[] */
    private $data;

    /** @var int */
    private $defaultFetchMode = FetchMode::MIXED;

    public function __construct(ResultStatement $stmt, Cache $resultCache, string $cacheKey, string $realKey, int $lifetime)
    {
        $this->statement   = $stmt;
        $this->resultCache = $resultCache;
        $this->cacheKey    = $cacheKey;
        $this->realKey     = $realKey;
        $this->lifetime    = $lifetime;
    }

    public function closeCursor() : void
    {
        $this->statement->closeCursor();

        if (! $this->emptied || $this->data === null) {
            return;
        }

        $data = $this->resultCache->fetch($this->cacheKey);
        if (! $data) {
            $data = [];
        }

        $data[$this->realKey] = $this->data;

        $this->resultCache->save($this->cacheKey, $data, $this->lifetime);
        unset($this->data);
    }

    public function columnCount() : int
    {
        return $this->statement->columnCount();
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode(int $fetchMode, ...$args) : void
    {
        $this->defaultFetchMode = $fetchMode;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $data = $this->fetchAll();

        return new ArrayIterator($data);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(?int $fetchMode = null, ...$args)
    {
        if ($this->data === null) {
            $this->data = [];
        }

        $row = $this->statement->fetch(FetchMode::ASSOCIATIVE);

        if ($row) {
            $this->data[] = $row;

            $fetchMode = $fetchMode ?: $this->defaultFetchMode;

            if ($fetchMode === FetchMode::ASSOCIATIVE) {
                return $row;
            }

            if ($fetchMode === FetchMode::NUMERIC) {
                return array_values($row);
            }

            if ($fetchMode === FetchMode::MIXED) {
                return array_merge($row, array_values($row));
            }

            if ($fetchMode === FetchMode::COLUMN) {
                return reset($row);
            }

            throw new InvalidArgumentException('Invalid fetch-style given for caching result.');
        }

        $this->emptied = true;

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(?int $fetchMode = null, ...$args) : array
    {
        $data = $this->statement->fetchAll($fetchMode, ...$args);

        if ($fetchMode === FetchMode::COLUMN) {
            foreach ($data as $key => $value) {
                $data[$key] = [$value];
            }
        }

        $this->data    = $data;
        $this->emptied = true;

        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn(int $columnIndex = 0)
    {
        $row = $this->fetch(FetchMode::NUMERIC);

        if ($row === false) {
            return false;
        }

        if (! array_key_exists($columnIndex, $row)) {
            throw InvalidColumnIndex::new($columnIndex, count($row));
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
     * @return int The number of rows.
     */
    public function rowCount() : int
    {
        return $this->statement->rowCount();
    }
}
