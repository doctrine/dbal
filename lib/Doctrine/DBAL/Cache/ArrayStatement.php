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
use function sprintf;

final class ArrayStatement implements IteratorAggregate, ResultStatement
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

    public function closeCursor() : void
    {
        $this->data = [];
    }

    public function columnCount() : int
    {
        return $this->columnCount;
    }

    public function rowCount() : int
    {
        return count($this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode(int $fetchMode, ...$args) : void
    {
        if (count($args) > 0) {
            throw new InvalidArgumentException('Caching layer does not support 2nd/3rd argument to setFetchMode().');
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
    public function fetch(?int $fetchMode = null, ...$args)
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

        throw new InvalidArgumentException(
            sprintf('Invalid fetch mode given for fetching result, %d given.', $fetchMode)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(?int $fetchMode = null, ...$args) : array
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
}
