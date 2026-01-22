<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLite3;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use Override;
use SQLite3Result;

use const SQLITE3_ASSOC;
use const SQLITE3_NUM;

final class Result implements ResultInterface
{
    private ?SQLite3Result $result;

    /** @internal The result can be only instantiated by its driver connection or statement. */
    public function __construct(SQLite3Result $result, private readonly int $changes)
    {
        $this->result = $result;
    }

    #[Override]
    public function fetchNumeric(): array|false
    {
        if ($this->result === null) {
            return false;
        }

        return $this->result->fetchArray(SQLITE3_NUM);
    }

    #[Override]
    public function fetchAssociative(): array|false
    {
        if ($this->result === null) {
            return false;
        }

        return $this->result->fetchArray(SQLITE3_ASSOC);
    }

    #[Override]
    public function fetchOne(): mixed
    {
        return FetchUtils::fetchOne($this);
    }

    /** @inheritDoc */
    #[Override]
    public function fetchAllNumeric(): array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    /** @inheritDoc */
    #[Override]
    public function fetchAllAssociative(): array
    {
        return FetchUtils::fetchAllAssociative($this);
    }

    /** @inheritDoc */
    #[Override]
    public function fetchFirstColumn(): array
    {
        return FetchUtils::fetchFirstColumn($this);
    }

    #[Override]
    public function rowCount(): int
    {
        return $this->changes;
    }

    #[Override]
    public function columnCount(): int
    {
        if ($this->result === null) {
            return 0;
        }

        return $this->result->numColumns();
    }

    #[Override]
    public function getColumnName(int $index): string
    {
        if ($this->result === null) {
            throw InvalidColumnIndex::new($index);
        }

        $name = $this->result->columnName($index);

        if ($name === false) {
            throw InvalidColumnIndex::new($index);
        }

        return $name;
    }

    #[Override]
    public function free(): void
    {
        if ($this->result === null) {
            return;
        }

        $this->result->finalize();
        $this->result = null;
    }
}
