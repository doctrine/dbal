<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use function array_filter;
use function count;

/**
 * Differences between two schemas.
 */
class SchemaDiff
{
    /** @var array<TableDiff> */
    private readonly array $alteredTables;

    /**
     * Constructs an SchemaDiff object.
     *
     * @internal The diff can be only instantiated by a {@see Comparator}.
     *
     * @param array<string>    $createdSchemas
     * @param array<string>    $droppedSchemas
     * @param array<Table>     $createdTables
     * @param array<TableDiff> $alteredTables
     * @param array<Table>     $droppedTables
     * @param array<Sequence>  $createdSequences
     * @param array<Sequence>  $alteredSequences
     * @param array<Sequence>  $droppedSequences
     */
    public function __construct(
        private readonly array $createdSchemas,
        private readonly array $droppedSchemas,
        private readonly array $createdTables,
        array $alteredTables,
        private readonly array $droppedTables,
        private readonly array $createdSequences,
        private readonly array $alteredSequences,
        private readonly array $droppedSequences,
    ) {
        $this->alteredTables = array_filter($alteredTables, static function (TableDiff $diff): bool {
            return ! $diff->isEmpty();
        });
    }

    /** @return array<string> */
    public function getCreatedSchemas(): array
    {
        return $this->createdSchemas;
    }

    /** @return array<string> */
    public function getDroppedSchemas(): array
    {
        return $this->droppedSchemas;
    }

    /** @return array<Table> */
    public function getCreatedTables(): array
    {
        return $this->createdTables;
    }

    /** @return array<TableDiff> */
    public function getAlteredTables(): array
    {
        return $this->alteredTables;
    }

    /** @return array<Table> */
    public function getDroppedTables(): array
    {
        return $this->droppedTables;
    }

    /** @return array<Sequence> */
    public function getCreatedSequences(): array
    {
        return $this->createdSequences;
    }

    /** @return array<Sequence> */
    public function getAlteredSequences(): array
    {
        return $this->alteredSequences;
    }

    /** @return array<Sequence> */
    public function getDroppedSequences(): array
    {
        return $this->droppedSequences;
    }

    /**
     * Returns whether the diff is empty (contains no changes).
     */
    public function isEmpty(): bool
    {
        return count($this->createdSchemas) === 0
            && count($this->droppedSchemas) === 0
            && count($this->createdTables) === 0
            && count($this->alteredTables) === 0
            && count($this->droppedTables) === 0
            && count($this->createdSequences) === 0
            && count($this->alteredSequences) === 0
            && count($this->droppedSequences) === 0;
    }
}
