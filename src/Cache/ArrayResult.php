<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Exception\InvalidColumnIndex;

use function array_combine;
use function count;

/** @internal The class is internal to the caching layer implementation. */
final class ArrayResult implements Result
{
    private int $num = 0;

    /**
     * @param list<string>      $columnNames The names of the result columns. Must be non-empty.
     * @param list<list<mixed>> $rows        The rows of the result. Each row must have the same number of columns
     *                                       as the number of column names.
     */
    public function __construct(private readonly array $columnNames, private array $rows)
    {
    }

    public function fetchNumeric(): array|false
    {
        return $this->fetch();
    }

    public function fetchAssociative(): array|false
    {
        $row = $this->fetch();

        if ($row === false) {
            return false;
        }

        return array_combine($this->columnNames, $row);
    }

    public function fetchOne(): mixed
    {
        $row = $this->fetch();

        if ($row === false) {
            return false;
        }

        return $row[0];
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllNumeric(): array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllAssociative(): array
    {
        return FetchUtils::fetchAllAssociative($this);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchFirstColumn(): array
    {
        return FetchUtils::fetchFirstColumn($this);
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    public function columnCount(): int
    {
        return count($this->columnNames);
    }

    public function getColumnName(int $index): string
    {
        return $this->columnNames[$index] ?? throw InvalidColumnIndex::new($index);
    }

    public function free(): void
    {
        $this->rows = [];
    }

    /** @return list<mixed>|false */
    private function fetch(): array|false
    {
        if (! isset($this->rows[$this->num])) {
            return false;
        }

        return $this->rows[$this->num++];
    }
}
