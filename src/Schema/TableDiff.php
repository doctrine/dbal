<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\Deprecations\Deprecation;

use function array_filter;
use function array_values;
use function count;

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
     * @param array<ForeignKeyConstraint> $droppedForeignKeys
     * @param array<Column>               $addedColumns
     * @param array<string, ColumnDiff>   $changedColumns
     * @param array<Column>               $droppedColumns
     * @param array<Index>                $addedIndexes
     * @param array<Index>                $modifiedIndexes
     * @param array<Index>                $droppedIndexes
     * @param array<string, Index>        $renamedIndexes
     * @param array<ForeignKeyConstraint> $addedForeignKeys
     * @param array<ForeignKeyConstraint> $modifiedForeignKeys
     */
    public function __construct(
        private readonly Table $oldTable,
        private readonly array $addedColumns = [],
        private readonly array $changedColumns = [],
        private readonly array $droppedColumns = [],
        private array $addedIndexes = [],
        private readonly array $modifiedIndexes = [],
        private array $droppedIndexes = [],
        private readonly array $renamedIndexes = [],
        private readonly array $addedForeignKeys = [],
        private readonly array $modifiedForeignKeys = [],
        private readonly array $droppedForeignKeys = [],
    ) {
    }

    public function getOldTable(): Table
    {
        return $this->oldTable;
    }

    /** @return array<Column> */
    public function getAddedColumns(): array
    {
        return $this->addedColumns;
    }

    /** @return array<string, ColumnDiff> */
    public function getChangedColumns(): array
    {
        return $this->changedColumns;
    }

    /**
     * @deprecated Use {@see getChangedColumns()} instead.
     *
     * @return list<ColumnDiff>
     */
    public function getModifiedColumns(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6280',
            '%s is deprecated, use `getChangedColumns()` instead.',
            __METHOD__,
        );

        return array_values(array_filter(
            $this->getChangedColumns(),
            static fn (ColumnDiff $diff): bool => $diff->countChangedProperties() > ($diff->hasNameChanged() ? 1 : 0),
        ));
    }

    /**
     * @deprecated Use {@see getChangedColumns()} instead.
     *
     * @return array<string,Column>
     */
    public function getRenamedColumns(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6280',
            '%s is deprecated, you should use `getChangedColumns()` instead.',
            __METHOD__,
        );
        $renamed = [];
        foreach ($this->getChangedColumns() as $diff) {
            if (! $diff->hasNameChanged()) {
                continue;
            }

            $oldColumnName           = $diff->getOldColumn()->getName();
            $renamed[$oldColumnName] = $diff->getNewColumn();
        }

        return $renamed;
    }

    /** @return array<Column> */
    public function getDroppedColumns(): array
    {
        return $this->droppedColumns;
    }

    /** @return array<Index> */
    public function getAddedIndexes(): array
    {
        return $this->addedIndexes;
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
        return $this->modifiedIndexes;
    }

    /** @return array<Index> */
    public function getDroppedIndexes(): array
    {
        return $this->droppedIndexes;
    }

    /**
     * @internal This method exists only for compatibility with the current implementation of schema managers
     *           that modify the diff while processing it.
     */
    public function unsetDroppedIndex(Index $index): void
    {
        $this->droppedIndexes = array_filter(
            $this->droppedIndexes,
            static function (Index $droppedIndex) use ($index): bool {
                return $droppedIndex !== $index;
            },
        );
    }

    /** @return array<string,Index> */
    public function getRenamedIndexes(): array
    {
        return $this->renamedIndexes;
    }

    /** @return array<ForeignKeyConstraint> */
    public function getAddedForeignKeys(): array
    {
        return $this->addedForeignKeys;
    }

    /** @return array<ForeignKeyConstraint> */
    public function getModifiedForeignKeys(): array
    {
        return $this->modifiedForeignKeys;
    }

    /** @return array<ForeignKeyConstraint> */
    public function getDroppedForeignKeys(): array
    {
        return $this->droppedForeignKeys;
    }

    /**
     * Returns whether the diff is empty (contains no changes).
     */
    public function isEmpty(): bool
    {
        return count($this->addedColumns) === 0
            && count($this->changedColumns) === 0
            && count($this->droppedColumns) === 0
            && count($this->addedIndexes) === 0
            && count($this->modifiedIndexes) === 0
            && count($this->droppedIndexes) === 0
            && count($this->renamedIndexes) === 0
            && count($this->addedForeignKeys) === 0
            && count($this->modifiedForeignKeys) === 0
            && count($this->droppedForeignKeys) === 0;
    }
}
