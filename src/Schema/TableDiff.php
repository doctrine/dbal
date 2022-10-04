<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use function array_filter;
use function array_values;

/**
 * Table Diff.
 */
class TableDiff
{
    /**
     * Constructs a TableDiff object.
     *
     * @internal The diff can be only instantiated by a {@see Comparator}.
     *
     * @param array<Column>              $addedColumns
     * @param array<ColumnDiff>          $changedColumns
     * @param array<Column>              $removedColumns
     * @param array<Index>               $addedIndexes
     * @param array<Index>               $changedIndexes
     * @param array<Index>               $removedIndexes
     * @param list<ForeignKeyConstraint> $addedForeignKeys
     * @param list<ForeignKeyConstraint> $changedForeignKeys
     * @param list<ForeignKeyConstraint> $removedForeignKeys
     * @param array<string, Column>      $renamedColumns
     * @param array<string, Index>       $renamedIndexes
     */
    public function __construct(
        private Table $oldTable,
        /** @internal Use {@see getAddedColumns()} instead. */
        public array $addedColumns = [],
        /** @internal Use {@see getModifiedColumns()} instead. */
        public array $changedColumns = [],
        /** @internal Use {@see getDroppedColumns()} instead. */
        public array $removedColumns = [],
        /** @internal Use {@see getAddedIndexes()} instead. */
        public array $addedIndexes = [],
        /** @internal Use {@see getModifiedIndexes()} instead. */
        public array $changedIndexes = [],
        /** @internal Use {@see getDroppedIndexes()} instead. */
        public array $removedIndexes = [],
        /** @internal Use {@see getAddedForeignKeys()} instead. */
        public array $addedForeignKeys = [],
        /** @internal Use {@see getModifiedForeignKeys()} instead. */
        public array $changedForeignKeys = [],
        /** @internal Use {@see getDroppedForeignKeys()} instead. */
        public array $removedForeignKeys = [],
        /** @internal Use {@see getRenamedColumns()} instead. */
        public array $renamedColumns = [],
        /** @internal Use {@see getRenamedIndexes()} instead. */
        public array $renamedIndexes = [],
    ) {
    }

    public function getOldTable(): Table
    {
        return $this->oldTable;
    }

    /** @return list<Column> */
    public function getAddedColumns(): array
    {
        return array_values($this->addedColumns);
    }

    /** @return list<ColumnDiff> */
    public function getModifiedColumns(): array
    {
        return array_values($this->changedColumns);
    }

    /** @return list<Column> */
    public function getDroppedColumns(): array
    {
        return array_values($this->removedColumns);
    }

    /** @return array<string,Column> */
    public function getRenamedColumns(): array
    {
        return $this->renamedColumns;
    }

    /** @return list<Index> */
    public function getAddedIndexes(): array
    {
        return array_values($this->addedIndexes);
    }

    /**
     * @internal This method exists only for compatibility with the current implementation of schema managers
     *           that modify the diff while processing it.
     */
    public function unsetAddedIndex(Index $index): void
    {
        $this->addedIndexes = array_filter(
            $this->addedIndexes,
            static function (Index $addedIndex) use ($index): bool {
                return $addedIndex !== $index;
            },
        );
    }

    /** @return array<Index> */
    public function getModifiedIndexes(): array
    {
        return array_values($this->changedIndexes);
    }

    /** @return list<Index> */
    public function getDroppedIndexes(): array
    {
        return array_values($this->removedIndexes);
    }

    /**
     * @internal This method exists only for compatibility with the current implementation of schema managers
     *           that modify the diff while processing it.
     */
    public function unsetDroppedIndex(Index $index): void
    {
        $this->removedIndexes = array_filter(
            $this->removedIndexes,
            static function (Index $removedIndex) use ($index): bool {
                return $removedIndex !== $index;
            },
        );
    }

    /** @return array<string,Index> */
    public function getRenamedIndexes(): array
    {
        return $this->renamedIndexes;
    }

    /** @return list<ForeignKeyConstraint> */
    public function getAddedForeignKeys(): array
    {
        return $this->addedForeignKeys;
    }

    /** @return list<ForeignKeyConstraint> */
    public function getModifiedForeignKeys(): array
    {
        return $this->changedForeignKeys;
    }

    /** @return list<ForeignKeyConstraint> */
    public function getDroppedForeignKeys(): array
    {
        return $this->removedForeignKeys;
    }

    /** @internal This method exists only for compatibility with the current implementation of the schema comparator. */
    public function unsetDroppedForeignKey(ForeignKeyConstraint $foreignKey): void
    {
        $this->removedForeignKeys = array_values(
            array_filter(
                $this->removedForeignKeys,
                static function (ForeignKeyConstraint $removedForeignKey) use ($foreignKey): bool {
                    return $removedForeignKey !== $foreignKey;
                },
            ),
        );
    }
}
