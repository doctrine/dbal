<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\Deprecations\Deprecation;

use function array_filter;
use function array_values;
use function count;

/**
 * Table Diff.
 */
final readonly class TableDiff
{
    /**
     * Constructs a TableDiff object.
     *
     * @internal The diff can be only instantiated by a {@see Comparator}.
     *
     * @param array<Column>               $addedColumns
     * @param array<string, ColumnDiff>   $changedColumns
     * @param array<Column>               $droppedColumns
     * @param array<Index>                $addedIndexes
     * @param array<Index>                $droppedIndexes
     * @param list<IndexRename>           $indexRenames
     * @param array<ForeignKeyConstraint> $addedForeignKeys
     * @param array<UnqualifiedName>      $droppedForeignKeyConstraintNames
     * @param array<UniqueConstraint>     $addedUniqueConstraints
     * @param array<UnqualifiedName>      $droppedUniqueConstraintNames
     */
    public function __construct(
        private Table $oldTable,
        private array $addedColumns = [],
        private array $changedColumns = [],
        private array $droppedColumns = [],
        private array $addedIndexes = [],
        private array $droppedIndexes = [],
        private array $indexRenames = [],
        private array $addedForeignKeys = [],
        private array $droppedForeignKeyConstraintNames = [],
        private ?PrimaryKeyConstraint $addedPrimaryKeyConstraint = null,
        private ?PrimaryKeyConstraint $droppedPrimaryKeyConstraint = null,
        private array $addedUniqueConstraints = [],
        private array $droppedUniqueConstraintNames = [],
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

            $oldColumnName = $diff->getOldColumn()
                ->getObjectName()
                ->getIdentifier()
                ->getValue();

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

    /** @return array<Index> */
    public function getDroppedIndexes(): array
    {
        return $this->droppedIndexes;
    }

    /** @return list<IndexRename> */
    public function getIndexRenames(): array
    {
        return $this->indexRenames;
    }

    /** @return array<ForeignKeyConstraint> */
    public function getAddedForeignKeys(): array
    {
        return $this->addedForeignKeys;
    }

    /** @return array<UnqualifiedName> */
    public function getDroppedForeignKeyConstraintNames(): array
    {
        return $this->droppedForeignKeyConstraintNames;
    }

    public function getAddedPrimaryKeyConstraint(): ?PrimaryKeyConstraint
    {
        return $this->addedPrimaryKeyConstraint;
    }

    public function getDroppedPrimaryKeyConstraint(): ?PrimaryKeyConstraint
    {
        return $this->droppedPrimaryKeyConstraint;
    }

    /** @return array<UniqueConstraint> */
    public function getAddedUniqueConstraints(): array
    {
        return $this->addedUniqueConstraints;
    }

    /** @return array<UnqualifiedName> */
    public function getDroppedUniqueConstraintNames(): array
    {
        return $this->droppedUniqueConstraintNames;
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
            && count($this->droppedIndexes) === 0
            && count($this->indexRenames) === 0
            && count($this->addedForeignKeys) === 0
            && count($this->droppedForeignKeyConstraintNames) === 0
            && $this->addedPrimaryKeyConstraint === null
            && $this->droppedPrimaryKeyConstraint === null
            && count($this->addedUniqueConstraints) === 0
            && count($this->droppedUniqueConstraintNames) === 0;
    }
}
