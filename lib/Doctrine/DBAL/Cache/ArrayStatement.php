<?php

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\FetchMode;

class ArrayStatement implements \IteratorAggregate, ResultStatement
{
    /**
     * @var array
     */
    private $data;

    /**
     * @var int
     */
    private $columnCount = 0;

    /**
     * @var int
     */
    private $num = 0;

    /**
     * @var FetchMode
     */
    private $defaultFetchMode;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        if (count($data)) {
            $this->columnCount = count($data[0]);
        }

        $this->defaultFetchMode = FetchMode::MIXED();
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        unset ($this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->columnCount;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode(FetchMode $fetchMode, ...$args)
    {
        if (count($args) > 0) {
            throw new \InvalidArgumentException("Caching layer does not support 2nd/3rd argument to setFetchMode()");
        }

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
    public function fetch(?FetchMode $fetchMode = null, ...$args)
    {
        if (! isset($this->data[$this->num])) {
            return false;
        }

        $row       = $this->data[$this->num++];
        $fetchMode = $fetchMode ?? $this->defaultFetchMode;

        if ($fetchMode === FetchMode::ASSOCIATIVE()) {
            return $row;
        }

        if ($fetchMode === FetchMode::NUMERIC()) {
            return array_values($row);
        }

        if ($fetchMode === FetchMode::MIXED()) {
            return array_merge($row, array_values($row));
        }

        if ($fetchMode === FetchMode::COLUMN()) {
            return reset($row);
        }

        throw new \InvalidArgumentException('Invalid fetch-style given for fetching result.');
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(?FetchMode $fetchMode = null, ...$args)
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
        $row = $this->fetch(FetchMode::NUMERIC());

        // TODO: verify that return false is the correct behavior
        return $row[$columnIndex] ?? false;
    }
}
