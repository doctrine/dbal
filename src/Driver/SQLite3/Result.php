<?php

namespace Doctrine\DBAL\Driver\SQLite3;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use SQLite3Result;

use const SQLITE3_ASSOC;
use const SQLITE3_NUM;

class Result implements ResultInterface
{
    protected ?SQLite3Result $result;
    private int $changes;

    /** @internal The result can be only instantiated by its driver connection or statement. */
    public function __construct(SQLite3Result $result, int $changes)
    {
        $this->result  = $result;
        $this->changes = $changes;
    }

    /** @inheritdoc */
    public function fetchNumeric()
    {
        if ($this->result === null) {
            return false;
        }

        return $this->result->fetchArray(SQLITE3_NUM);
    }

    /** @inheritdoc */
    public function fetchAssociative()
    {
        if ($this->result === null) {
            return false;
        }

        return $this->result->fetchArray(SQLITE3_ASSOC);
    }

    /** @inheritdoc */
    public function fetchOne()
    {
        return FetchUtils::fetchOne($this);
    }

    /** @inheritdoc */
    public function fetchAllNumeric(): array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    /** @inheritdoc */
    public function fetchAllAssociative(): array
    {
        return FetchUtils::fetchAllAssociative($this);
    }

    /** @inheritdoc */
    public function fetchFirstColumn(): array
    {
        return FetchUtils::fetchFirstColumn($this);
    }

    public function rowCount(): int
    {
        return $this->changes;
    }

    public function columnCount(): int
    {
        if ($this->result === null) {
            return 0;
        }

        return $this->result->numColumns();
    }

    public function free(): void
    {
        if ($this->result === null) {
            return;
        }

        $this->result->finalize();
        $this->result = null;
    }

    /**
     * Returns the column metadata.
     *
     * @param int $index
     *
     * @return array Column metadata.
     *
     * @throws Exception
     */
    public function getColumnMeta($index): array
    {
        return [];
    }
}
