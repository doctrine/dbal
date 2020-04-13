<?php

declare(strict_types=1);

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
        if (count($data) === 0) {
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

    public function setFetchMode(int $fetchMode) : void
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
    public function fetch(?int $fetchMode = null)
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

        throw new InvalidArgumentException(
            sprintf('Invalid fetch mode given for fetching result, %d given.', $fetchMode)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(?int $fetchMode = null) : array
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

        if ($row === false) {
            return false;
        }

        return $row[0];
    }
}
