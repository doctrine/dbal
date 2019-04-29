<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Cache;

use ArrayIterator;
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
        if (! count($data)) {
            return;
        }

        $this->columnCount = count($data[0]);
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor() : void
    {
        unset($this->data);
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
    public function rowCount() : int
    {
        if ($this->data === null) {
            return 0;
        }

        return count($this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, ...$args) : void
    {
        if (count($args) > 0) {
            throw new InvalidArgumentException('Caching layer does not support 2nd/3rd argument to setFetchMode()');
        }

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
    public function fetch($fetchMode = null, ...$args)
    {
        if (! isset($this->data[$this->num])) {
            return false;
        }

        $row       = $this->data[$this->num++];
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

        throw new InvalidArgumentException('Invalid fetch-style given for fetching result.');
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

        if ($row === false) {
            return false;
        }

        if (! array_key_exists($columnIndex, $row)) {
            throw InvalidColumnIndex::new($columnIndex, count($row));
        }

        return $row[$columnIndex];
    }
}
