<?php

namespace Doctrine\DBAL\Cache;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\ResultStatement;
use function array_map;
use function array_values;

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
class ResultCacheStatement implements ResultStatement
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

    /** @var array<int,array<string,mixed>> */
    private $data;

    /**
     * @param string $cacheKey
     * @param string $realKey
     * @param int    $lifetime
     */
    public function __construct(ResultStatement $stmt, Cache $resultCache, $cacheKey, $realKey, $lifetime)
    {
        $this->statement   = $stmt;
        $this->resultCache = $resultCache;
        $this->cacheKey    = $cacheKey;
        $this->realKey     = $realKey;
        $this->lifetime    = $lifetime;
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        $this->statement->closeCursor();
        if (! $this->emptied || $this->data === null) {
            return true;
        }

        $data = $this->resultCache->fetch($this->cacheKey);
        if ($data === false) {
            $data = [];
        }

        $data[$this->realKey] = $this->data;

        $this->resultCache->save($this->cacheKey, $data, $this->lifetime);
        unset($this->data);

        return true;
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
    public function fetchNumeric()
    {
        $row = $this->fetch();

        if ($row === false) {
            return false;
        }

        return array_values($row);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssociative()
    {
        return $this->fetch();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne()
    {
        return FetchUtils::fetchOne($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllNumeric() : array
    {
        $this->store(
            $this->statement->fetchAllAssociative()
        );

        return array_map('array_values', $this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative() : array
    {
        $this->store(
            $this->statement->fetchAllAssociative()
        );

        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn() : array
    {
        return FetchUtils::fetchColumn($this);
    }

    /**
     * @return array<string,mixed>|false
     *
     * @throws DriverException
     */
    private function fetch()
    {
        if ($this->data === null) {
            $this->data = [];
        }

        $row = $this->statement->fetchAssociative();

        if ($row !== false) {
            $this->data[] = $row;

            return $row;
        }

        $this->emptied = true;

        return false;
    }

    /**
     * @param array<int,array<string,mixed>> $data
     */
    private function store(array $data) : void
    {
        $this->data    = $data;
        $this->emptied = true;
    }
}
