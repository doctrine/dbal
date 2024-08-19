<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Exception\InvalidColumnIndex;

use function array_keys;
use function array_values;
use function count;
use function reset;

/** @internal The class is internal to the caching layer implementation. */
final class ArrayResult implements Result
{
    private readonly int $columnCount;
    private int $num = 0;

    /** @param list<array<string, mixed>> $data */
    public function __construct(private array $data)
    {
        $this->columnCount = $data === [] ? 0 : count($data[0]);
    }

    public function fetchNumeric(): array|false
    {
        $row = $this->fetch();

        if ($row === false) {
            return false;
        }

        return array_values($row);
    }

    public function fetchAssociative(): array|false
    {
        return $this->fetch();
    }

    public function fetchOne(): mixed
    {
        $row = $this->fetch();

        if ($row === false) {
            return false;
        }

        return reset($row);
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
        return count($this->data);
    }

    public function columnCount(): int
    {
        return $this->columnCount;
    }

    public function getColumnName(int $index): string
    {
        return array_keys($this->data[0] ?? [])[$index]
            ?? throw InvalidColumnIndex::new($index);
    }

    public function free(): void
    {
        $this->data = [];
    }

    /** @return array<string, mixed>|false */
    private function fetch(): array|false
    {
        if (! isset($this->data[$this->num])) {
            return false;
        }

        return $this->data[$this->num++];
    }
}
