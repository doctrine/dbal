<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\ObjectOrigin;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Override;

use function array_all;
use function array_any;
use function array_find;
use function assert;
use function count;
use function usort;

/**
 * Compares schemas in the context of MySQL platform.
 *
 * In MySQL, unless specified explicitly, the column's character set and collation are inherited from its containing
 * table. So during comparison, an omitted value and the value that matches the default value of table in the
 * desired schema must be considered equal.
 */
class Comparator extends BaseComparator
{
    /** @internal The comparator can be only instantiated by a schema manager. */
    public function __construct(
        private readonly AbstractMySQLPlatform $platform,
        private readonly CharsetMetadataProvider $charsetMetadataProvider,
        private readonly CollationMetadataProvider $collationMetadataProvider,
        private readonly DefaultTableOptions $defaultTableOptions,
        ComparatorConfig $config = new ComparatorConfig(),
    ) {
        parent::__construct($platform, $config);
    }

    #[Override]
    public function compareTables(Table $oldTable, Table $newTable): TableDiff
    {
        $oldTable = $this->normalizeTable($oldTable);
        $newTable = $this->normalizeTable($newTable);

        $diff = parent::compareTables($oldTable, $newTable);

        return $this->withRecreatedForeignKeys($diff, $this->getForeignKeysToRecreate($oldTable, $newTable, $diff));
    }

    /**
     * MySQL doesn't report the index that it creates for a foreign key as derived. It also refuses to drop the last
     * index that can support a foreign key, so an old index that a foreign key of the new table needs, and that the new
     * table doesn't declare, is derived from that foreign key.
     */
    #[Override]
    protected function getOldIndexesDerivedFromNewTable(Table $oldTable, Table $newTable): array
    {
        return [
            ...parent::getOldIndexesDerivedFromNewTable($oldTable, $newTable),
            ...$this->getOldIndexesNeededByNewForeignKeys($oldTable, $newTable),
        ];
    }

    /**
     * Returns the indexes of the old table that the foreign keys of the new table need, because the new table has no
     * index that can support them.
     *
     * @return list<Index>
     */
    private function getOldIndexesNeededByNewForeignKeys(Table $oldTable, Table $newTable): array
    {
        $folding = $this->platform->getUnquotedIdentifierFolding();
        $indexes = [];

        foreach ($this->getForeignKeysWithoutSupportingIndex($oldTable, $newTable, $folding) as $foreignKey) {
            if ($this->canAnySupport($indexes, $foreignKey, $folding)) {
                continue;
            }

            $index = $this->findOldIndexNeededBy($oldTable, $newTable, $foreignKey, $folding);

            if ($index === null) {
                continue;
            }

            $indexes[] = $index;
        }

        return $indexes;
    }

    /**
     * Returns the foreign keys of the old table that the diff doesn't drop and that would lose their last supporting
     * index.
     *
     * @return list<ForeignKeyConstraint>
     */
    private function getForeignKeysToRecreate(Table $oldTable, Table $newTable, TableDiff $diff): array
    {
        $folding       = $this->platform->getUnquotedIdentifierFolding();
        $neededIndexes = $this->getOldIndexesNeededByNewForeignKeys($oldTable, $newTable);
        $foreignKeys   = [];

        foreach ($oldTable->getForeignKeys() as $foreignKey) {
            $name = $foreignKey->getObjectName();

            if ($name === null) {
                continue;
            }

            if ($this->isDropped($name, $diff, $folding)) {
                continue;
            }

            if ($this->hasSupportingIndex($newTable, $foreignKey, $folding)) {
                continue;
            }

            if ($this->canAnySupport($neededIndexes, $foreignKey, $folding)) {
                continue;
            }

            if (! $this->hasSupportingIndex($oldTable, $foreignKey, $folding)) {
                continue;
            }

            $foreignKeys[] = $foreignKey;
        }

        return $foreignKeys;
    }

    private function isDropped(
        UnqualifiedName $foreignKeyName,
        TableDiff $diff,
        UnquotedIdentifierFolding $folding,
    ): bool {
        return array_any(
            $diff->getDroppedForeignKeyConstraintNames(),
            static fn (UnqualifiedName $droppedName): bool => $droppedName->equals($foreignKeyName, $folding),
        );
    }

    /**
     * Returns the foreign keys of the new table that the old table also has and that the new table has no supporting
     * index for, the widest first.
     *
     * @return list<ForeignKeyConstraint>
     */
    private function getForeignKeysWithoutSupportingIndex(
        Table $oldTable,
        Table $newTable,
        UnquotedIdentifierFolding $folding,
    ): array {
        $foreignKeys = [];

        foreach ($newTable->getForeignKeys() as $foreignKey) {
            if (! $this->hasForeignKey($oldTable, $foreignKey, $folding)) {
                continue;
            }

            if ($this->hasSupportingIndex($newTable, $foreignKey, $folding)) {
                continue;
            }

            $foreignKeys[] = $foreignKey;
        }

        // An index that supports a wider foreign key may also support a narrower one.
        usort(
            $foreignKeys,
            static function (ForeignKeyConstraint $a, ForeignKeyConstraint $b): int {
                return count($b->getReferencingColumnNames()) <=> count($a->getReferencingColumnNames());
            },
        );

        return $foreignKeys;
    }

    /**
     * Returns the index of the old table that the foreign key needs, or null if there is none.
     */
    private function findOldIndexNeededBy(
        Table $oldTable,
        Table $newTable,
        ForeignKeyConstraint $foreignKey,
        UnquotedIdentifierFolding $folding,
    ): ?Index {
        // MySQL creates an index for the added foreign key, and that index supports this one too.
        if ($this->addsForeignKeyStartingWith($oldTable, $newTable, $foreignKey, $folding)) {
            return null;
        }

        return array_find(
            $oldTable->getIndexes(),
            fn (Index $index): bool => $this->canBeNeededBy($index, $foreignKey, $newTable, $folding),
        );
    }

    /**
     * Returns whether the index can be the one that the foreign key needs.
     */
    private function canBeNeededBy(
        Index $index,
        ForeignKeyConstraint $foreignKey,
        Table $newTable,
        UnquotedIdentifierFolding $folding,
    ): bool {
        if ($index->getOrigin() !== ObjectOrigin::DECLARED || $index->getType() !== IndexType::REGULAR) {
            return false;
        }

        if (! $this->canSupport($index, $foreignKey, $folding)) {
            return false;
        }

        // The new table replaces an index whose name it gives to another object.
        return ! $this->hasIndexOrUniqueConstraintNamed($newTable, $index->getObjectName(), $folding);
    }

    /**
     * MySQL implements a unique constraint as an index, so both kinds share one namespace.
     */
    private function hasIndexOrUniqueConstraintNamed(
        Table $table,
        UnqualifiedName $soughtName,
        UnquotedIdentifierFolding $folding,
    ): bool {
        $hasName = static function (Index|UniqueConstraint $object) use ($soughtName, $folding): bool {
            $name = $object->getObjectName();

            return $name !== null && $name->equals($soughtName, $folding);
        };

        return array_any($table->getIndexes(), $hasName) || array_any($table->getUniqueConstraints(), $hasName);
    }

    /**
     * Returns whether the new table adds a foreign key whose referencing columns start with those of the given one.
     */
    private function addsForeignKeyStartingWith(
        Table $oldTable,
        Table $newTable,
        ForeignKeyConstraint $foreignKey,
        UnquotedIdentifierFolding $folding,
    ): bool {
        $columnNames = $foreignKey->getReferencingColumnNames();

        foreach ($newTable->getForeignKeys() as $newForeignKey) {
            if (! $this->columnsStartWith($newForeignKey->getReferencingColumnNames(), $columnNames, $folding)) {
                continue;
            }

            if (! $this->hasForeignKey($oldTable, $newForeignKey, $folding)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Index> $indexes */
    private function canAnySupport(
        array $indexes,
        ForeignKeyConstraint $foreignKey,
        UnquotedIdentifierFolding $folding,
    ): bool {
        return array_any($indexes, fn (Index $index): bool => $this->canSupport($index, $foreignKey, $folding));
    }

    /**
     * MySQL implements a primary key and a unique constraint as indexes, so they can support a foreign key too.
     */
    private function hasSupportingIndex(
        Table $table,
        ForeignKeyConstraint $foreignKey,
        UnquotedIdentifierFolding $folding,
    ): bool {
        if ($this->canAnySupport($table->getIndexes(), $foreignKey, $folding)) {
            return true;
        }

        $columnNames          = $foreignKey->getReferencingColumnNames();
        $primaryKeyConstraint = $table->getPrimaryKeyConstraint();

        if (
            $primaryKeyConstraint !== null
            && $this->columnsStartWith($primaryKeyConstraint->getColumnNames(), $columnNames, $folding)
        ) {
            return true;
        }

        foreach ($table->getUniqueConstraints() as $uniqueConstraint) {
            $canSupport = match ($uniqueConstraint->getOrigin()) {
                ObjectOrigin::DECLARED => $this->columnsStartWith(
                    $uniqueConstraint->getColumnNames(),
                    $columnNames,
                    $folding,
                ),
                // The source index of a derived unique constraint is checked with the indexes.
                ObjectOrigin::DERIVED => false,
            };

            if ($canSupport) {
                return true;
            }
        }

        return false;
    }

    private function hasForeignKey(
        Table $table,
        ForeignKeyConstraint $foreignKey,
        UnquotedIdentifierFolding $folding,
    ): bool {
        return array_any(
            $table->getForeignKeys(),
            static fn (ForeignKeyConstraint $tableForeignKey): bool => $tableForeignKey->equals($foreignKey, $folding),
        );
    }

    /**
     * Returns the diff with the given foreign keys dropped and added again.
     *
     * MySQL refuses to drop the last index that can support a foreign key, and it creates a supporting index when a
     * foreign key is added.
     *
     * @param list<ForeignKeyConstraint> $foreignKeys
     */
    private function withRecreatedForeignKeys(TableDiff $diff, array $foreignKeys): TableDiff
    {
        if (count($foreignKeys) === 0) {
            return $diff;
        }

        $droppedForeignKeyConstraintNames = $diff->getDroppedForeignKeyConstraintNames();

        foreach ($foreignKeys as $foreignKey) {
            $name = $foreignKey->getObjectName();
            assert($name !== null);

            $droppedForeignKeyConstraintNames[] = $name;
        }

        return new TableDiff(
            $diff->getOldTable(),
            addedColumns: $diff->getAddedColumns(),
            changedColumns: $diff->getChangedColumns(),
            droppedColumns: $diff->getDroppedColumns(),
            addedIndexes: $diff->getAddedIndexes(),
            droppedIndexes: $diff->getDroppedIndexes(),
            indexRenames: $diff->getIndexRenames(),
            addedForeignKeys: [...$diff->getAddedForeignKeys(), ...$foreignKeys],
            droppedForeignKeyConstraintNames: $droppedForeignKeyConstraintNames,
            addedPrimaryKeyConstraint: $diff->getAddedPrimaryKeyConstraint(),
            droppedPrimaryKeyConstraint: $diff->getDroppedPrimaryKeyConstraint(),
        );
    }

    private function canSupport(
        Index $index,
        ForeignKeyConstraint $foreignKey,
        UnquotedIdentifierFolding $folding,
    ): bool {
        $columnNames    = $foreignKey->getReferencingColumnNames();
        $indexedColumns = $index->getIndexedColumns();

        if (count($indexedColumns) < count($columnNames)) {
            return false;
        }

        if ($index->getType() !== IndexType::REGULAR && $index->getType() !== IndexType::UNIQUE) {
            return false;
        }

        return array_all(
            $columnNames,
            static fn (UnqualifiedName $columnName, int $i): bool => $indexedColumns[$i]->getLength() === null
                && $indexedColumns[$i]->getColumnName()->equals($columnName, $folding),
        );
    }

    /**
     * @param non-empty-list<UnqualifiedName> $columns
     * @param non-empty-list<UnqualifiedName> $prefix
     */
    private function columnsStartWith(array $columns, array $prefix, UnquotedIdentifierFolding $folding): bool
    {
        if (count($columns) < count($prefix)) {
            return false;
        }

        return array_all(
            $prefix,
            static fn (UnqualifiedName $columnName, int $i): bool => $columns[$i]->equals($columnName, $folding),
        );
    }

    private function normalizeTable(Table $table): Table
    {
        $charset   = $table->getOption('charset');
        $collation = $table->getOption('collation');

        if ($charset === null && $collation !== null) {
            $charset = $this->collationMetadataProvider->getCollationCharset($collation);
        } elseif ($charset !== null && $collation === null) {
            $collation = $this->charsetMetadataProvider->getDefaultCharsetCollation($charset);
        } elseif ($charset === null && $collation === null) {
            $charset   = $this->defaultTableOptions->getCharset();
            $collation = $this->defaultTableOptions->getCollation();
        }

        $editor = null;

        foreach ($table->getColumns() as $column) {
            $originalCharset   = $column->getCharset();
            $originalCollation = $column->getCollation();

            $normalizedCharset   = $originalCharset;
            $normalizedCollation = $originalCollation;

            if ($originalCharset !== null && $originalCollation === null) {
                $normalizedCollation = $this->charsetMetadataProvider->getDefaultCharsetCollation($originalCharset);
            } elseif ($originalCollation !== null && $originalCharset === null) {
                $normalizedCharset = $this->collationMetadataProvider->getCollationCharset($originalCollation);
            }

            $modifications = [];

            if ($normalizedCharset === $charset) {
                $modifications[] = static function (ColumnEditor $editor): void {
                    $editor->setCharset(null);
                };
            } elseif ($normalizedCharset !== $originalCharset) {
                $modifications[] = static function (ColumnEditor $editor) use ($normalizedCharset): void {
                    $editor->setCharset($normalizedCharset);
                };
            }

            if ($normalizedCollation === $collation) {
                $modifications[] = static function (ColumnEditor $editor): void {
                    $editor->setCollation(null);
                };
            } elseif ($normalizedCollation !== $originalCollation) {
                $modifications[] = static function (ColumnEditor $editor) use ($normalizedCollation): void {
                    $editor->setCollation($normalizedCollation);
                };
            }

            if (count($modifications) === 0) {
                continue;
            }

            $editor ??= $table->edit();
            $name     = $column->getObjectName();

            foreach ($modifications as $modification) {
                $editor->modifyColumn($name, $modification);
            }
        }

        return $editor === null ? $table : $editor->create();
    }
}
