<?php

namespace Doctrine\DBAL\Cache;

use ArrayIterator;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\FetchMode;
use InvalidArgumentException;
use IteratorAggregate;
use function array_merge;
use function array_values;
use function count;
use function reset;

class ArrayStatement implements IteratorAggregate, ResultStatement
{
    /** @var mixed[] */
    private $data;

    /** @var int */
    private $columnCount = 0;

    /** @var int */
    private $num = 0;

    /** @var int */
    private $defaultFetchMode = FetchMode::MIXED;

    /**
     * @param mixed[] $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        if (count($data) === 0) {
            return;
        }

        $this->columnCount = count($data[0]);
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        unset($this->data);

        return true;
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
    public function setFetchMode($fetchMode)
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

        return new ArrayIterator($data);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null)
    {
        if (! isset($this->data[$this->num])) {
            return false;
        }

        $row       = $this->data[$this->num++];
        $fetchMode = $fetchMode ?? $this->defaultFetchMode;

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

        throw new InvalidArgumentException('Invalid fetch-style given for fetching result.');
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null)
    {
        $rows = [];
        while ($row = $this->fetch($fetchMode)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn()
    {
        $row = $this->fetch(FetchMode::NUMERIC);

        // TODO: verify that return false is the correct behavior
        return $row[0] ?? false;
    }
}
