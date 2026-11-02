<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Collections\UnqualifiedNamedObjectSet;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Override;

use function array_any;
use function array_filter;
use function array_values;
use function count;
use function in_array;
use function ksort;
use function reset;
use function strlen;
use function substr;
use function usort;

use const SORT_STRING;

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
        $newTable = $this->normalizeTable($newTable);

        return $this->ensureForeignKeyCoverage(
            parent::compareTables($this->normalizeTable($oldTable), $newTable),
            $newTable,
        );
    }

    /**
     * Ensures that an index covers each foreign key that remains after the change.
     *
     * MySQL requires an index whose leading columns are a foreign key's referencing columns and refuses to drop the
     * last one. It creates such an index when a foreign key is added, but not when an existing cover goes away.
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html
     */
    private function ensureForeignKeyCoverage(TableDiff $diff, Table $newTable): TableDiff
    {
        $folding = $this->platform->getUnquotedIdentifierFolding();

        $oldTable = $diff->getOldTable();

        $oldIndexes = array_values(array_filter(
            $oldTable->getIndexes(),
            static fn (Index $index): bool => ! $index->isDerived(),
        ));

        $resultingIndexes = $this->getResultingIndexes($oldIndexes, $diff);

        $droppedIndexes = $diff->getDroppedIndexes();
        $addedIndexes   = $diff->getAddedIndexes();

        $oldUniqueConstraints = array_values(array_filter(
            $oldTable->getUniqueConstraints(),
            static fn (UniqueConstraint $constraint): bool => ! $constraint->isDerived(),
        ));

        $resultingUniqueConstraints = $this->getResultingUniqueConstraints($oldUniqueConstraints, $diff, $folding);

        foreach ($this->getSurvivingForeignKeys($diff, $newTable) as $foreignKey) {
            $columnNames = $foreignKey->getReferencingColumnNames();

            // Without a cover in the old table, the one MySQL created is still in place and the diff doesn't touch it.
            if (
                ! $this->isCovered(
                    $columnNames,
                    $oldIndexes,
                    $oldTable->getPrimaryKeyConstraint(),
                    $oldUniqueConstraints,
                    $folding,
                )
                || $this->isCovered(
                    $columnNames,
                    $resultingIndexes,
                    $newTable->getPrimaryKeyConstraint(),
                    $resultingUniqueConstraints,
                    $folding,
                )
            ) {
                continue;
            }

            // Foreign keys are added after the indexes change, so an added foreign key can't supply the cover by then.
            // Covering its columns instead lets it reuse the index rather than have MySQL create another one.
            $coveringForeignKey = $this->findWidestAddedForeignKeyStartingWith($columnNames, $diff, $folding)
                ?? $foreignKey;

            $columnNames = $coveringForeignKey->getReferencingColumnNames();

            $candidate = $this->findRegularCover(
                $columnNames,
                $droppedIndexes,
                $resultingIndexes,
                $resultingUniqueConstraints,
                $folding,
            );

            if ($candidate !== null) {
                $droppedIndexes = array_values(array_filter(
                    $droppedIndexes,
                    static fn (Index $index): bool => $index !== $candidate,
                ));

                $resultingIndexes->add($candidate);

                continue;
            }

            $cover = $this->createCover(
                $coveringForeignKey,
                $newTable,
                $resultingIndexes,
                $resultingUniqueConstraints,
                $folding,
            );

            $addedIndexes[] = $cover;
            $resultingIndexes->add($cover);
        }

        if ($droppedIndexes === $diff->getDroppedIndexes() && $addedIndexes === $diff->getAddedIndexes()) {
            return $diff;
        }

        return new TableDiff(
            $diff->getOldTable(),
            addedColumns: $diff->getAddedColumns(),
            changedColumns: $diff->getChangedColumns(),
            droppedColumns: $diff->getDroppedColumns(),
            addedIndexes: $addedIndexes,
            droppedIndexes: $droppedIndexes,
            indexRenames: $diff->getIndexRenames(),
            addedForeignKeys: $diff->getAddedForeignKeys(),
            droppedForeignKeyConstraintNames: $diff->getDroppedForeignKeyConstraintNames(),
            addedPrimaryKeyConstraint: $diff->getAddedPrimaryKeyConstraint(),
            droppedPrimaryKeyConstraint: $diff->getDroppedPrimaryKeyConstraint(),
            addedUniqueConstraints: $diff->getAddedUniqueConstraints(),
            droppedUniqueConstraintNames: $diff->getDroppedUniqueConstraintNames(),
        );
    }

    /**
     * @param list<Index> $oldIndexes
     *
     * @return UnqualifiedNamedObjectSet<Index>
     */
    private function getResultingIndexes(array $oldIndexes, TableDiff $diff): UnqualifiedNamedObjectSet
    {
        $resultingIndexes = new UnqualifiedNamedObjectSet(...$oldIndexes);

        foreach ($diff->getDroppedIndexes() as $index) {
            $resultingIndexes->remove($index->getObjectName());
        }

        foreach ($diff->getIndexRenames() as $rename) {
            $resultingIndexes->remove($rename->getOldName());
            $resultingIndexes->add($rename->getNewIndex());
        }

        foreach ($diff->getAddedIndexes() as $index) {
            $resultingIndexes->add($index);
        }

        return $resultingIndexes;
    }

    /**
     * @param list<UniqueConstraint> $oldUniqueConstraints
     *
     * @return list<UniqueConstraint>
     */
    private function getResultingUniqueConstraints(
        array $oldUniqueConstraints,
        TableDiff $diff,
        UnquotedIdentifierFolding $folding,
    ): array {
        $resultingUniqueConstraints = [];

        foreach ($oldUniqueConstraints as $constraint) {
            $name = $constraint->getObjectName();

            if (
                $name === null || ! array_any(
                    $diff->getDroppedUniqueConstraintNames(),
                    static fn (UnqualifiedName $droppedName): bool => $droppedName->equals($name, $folding),
                )
            ) {
                $resultingUniqueConstraints[] = $constraint;
            }
        }

        return [...$resultingUniqueConstraints, ...array_values($diff->getAddedUniqueConstraints())];
    }

    /**
     * Returns the foreign keys of the new table that the diff neither adds nor drops.
     *
     * @return list<ForeignKeyConstraint>
     */
    private function getSurvivingForeignKeys(TableDiff $diff, Table $newTable): array
    {
        $foreignKeys = [];

        foreach ($newTable->getForeignKeys() as $foreignKey) {
            if (! in_array($foreignKey, $diff->getAddedForeignKeys(), true)) {
                $foreignKeys[] = $foreignKey;
            }
        }

        // A narrower foreign key may be covered by the index added for a wider one.
        usort(
            $foreignKeys,
            static function (ForeignKeyConstraint $a, ForeignKeyConstraint $b): int {
                return count($b->getReferencingColumnNames()) <=> count($a->getReferencingColumnNames());
            },
        );

        return $foreignKeys;
    }

    /**
     * @param non-empty-list<UnqualifiedName> $columnNames
     * @param iterable<Index>                 $indexes
     * @param list<UniqueConstraint>          $uniqueConstraints
     */
    private function isCovered(
        array $columnNames,
        iterable $indexes,
        ?PrimaryKeyConstraint $primaryKeyConstraint,
        array $uniqueConstraints,
        UnquotedIdentifierFolding $folding,
    ): bool {
        foreach ($indexes as $index) {
            if ($this->indexCovers($index, $columnNames, $folding)) {
                return true;
            }
        }

        if (
            $primaryKeyConstraint !== null
            && $this->columnsStartWith($primaryKeyConstraint->getColumnNames(), $columnNames, $folding)
        ) {
            return true;
        }

        foreach ($uniqueConstraints as $uniqueConstraint) {
            if ($this->columnsStartWith($uniqueConstraint->getColumnNames(), $columnNames, $folding)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the first, by name, of the dropped regular indexes that cover the given columns and whose name is not
     * taken in the altered table.
     *
     * @param non-empty-list<UnqualifiedName>  $columnNames
     * @param array<Index>                     $droppedIndexes
     * @param UnqualifiedNamedObjectSet<Index> $resultingIndexes
     * @param list<UniqueConstraint>           $resultingUniqueConstraints
     */
    private function findRegularCover(
        array $columnNames,
        array $droppedIndexes,
        UnqualifiedNamedObjectSet $resultingIndexes,
        array $resultingUniqueConstraints,
        UnquotedIdentifierFolding $folding,
    ): ?Index {
        $candidates = [];

        foreach ($droppedIndexes as $index) {
            if (
                $index->getType() === IndexType::REGULAR
                && ! $this->isNameTaken(
                    $index->getObjectName(),
                    $resultingIndexes,
                    $resultingUniqueConstraints,
                    $folding,
                )
                && $this->indexCovers($index, $columnNames, $folding)
            ) {
                $candidates[$index->getObjectName()->getIdentifier()->toNormalizedValue($folding)] = $index;
            }
        }

        if (count($candidates) === 0) {
            return null;
        }

        ksort($candidates, SORT_STRING);

        return reset($candidates);
    }

    /**
     * @param UnqualifiedNamedObjectSet<Index> $resultingIndexes
     * @param list<UniqueConstraint>           $resultingUniqueConstraints
     */
    private function createCover(
        ForeignKeyConstraint $foreignKey,
        Table $newTable,
        UnqualifiedNamedObjectSet $resultingIndexes,
        array $resultingUniqueConstraints,
        UnquotedIdentifierFolding $folding,
    ): Index {
        $editor = Index::editor()
            ->setColumnNames(...$foreignKey->getReferencingColumnNames());

        $name = $foreignKey->getObjectName();

        if (
            $name !== null
            && ! $this->isNameTaken($name, $resultingIndexes, $resultingUniqueConstraints, $folding)
        ) {
            return $editor
                ->setName($name)
                ->create();
        }

        $maxIdentifierLength = $this->platform->getMaxIdentifierLength();

        $cover = $editor->createForTable($newTable->getObjectName()->getUnqualifiedName(), $maxIdentifierLength);
        $base  = $cover->getObjectName()->getIdentifier()->getValue();

        for (
            $suffix = 2;
            $this->isNameTaken($cover->getObjectName(), $resultingIndexes, $resultingUniqueConstraints, $folding);
            $suffix++
        ) {
            $suffixed = '_' . $suffix;

            $cover = $editor
                ->setUnquotedName(substr($base, 0, $maxIdentifierLength - strlen($suffixed)) . $suffixed)
                ->create();
        }

        return $cover;
    }

    /**
     * Returns whether an index or a unique constraint of the altered table has the given name. MySQL implements a
     * unique constraint as an index, so both kinds share one namespace.
     *
     * @param UnqualifiedNamedObjectSet<Index> $resultingIndexes
     * @param list<UniqueConstraint>           $resultingUniqueConstraints
     */
    private function isNameTaken(
        UnqualifiedName $name,
        UnqualifiedNamedObjectSet $resultingIndexes,
        array $resultingUniqueConstraints,
        UnquotedIdentifierFolding $folding,
    ): bool {
        if ($resultingIndexes->get($name) !== null) {
            return true;
        }

        foreach ($resultingUniqueConstraints as $constraint) {
            $constraintName = $constraint->getObjectName();

            if ($constraintName !== null && $constraintName->equals($name, $folding)) {
                return true;
            }
        }

        return false;
    }

    /** @param non-empty-list<UnqualifiedName> $columnNames */
    private function findWidestAddedForeignKeyStartingWith(
        array $columnNames,
        TableDiff $diff,
        UnquotedIdentifierFolding $folding,
    ): ?ForeignKeyConstraint {
        $widest = null;

        foreach ($diff->getAddedForeignKeys() as $foreignKey) {
            $referencingColumnNames = $foreignKey->getReferencingColumnNames();

            if (
                $this->columnsStartWith($referencingColumnNames, $columnNames, $folding)
                && (
                    $widest === null
                    || count($referencingColumnNames) > count($widest->getReferencingColumnNames())
                )
            ) {
                $widest = $foreignKey;
            }
        }

        return $widest;
    }

    /** @param non-empty-list<UnqualifiedName> $columnNames */
    private function indexCovers(Index $index, array $columnNames, UnquotedIdentifierFolding $folding): bool
    {
        $indexedColumns = $index->getIndexedColumns();

        if (count($indexedColumns) < count($columnNames)) {
            return false;
        }

        if ($index->getType() !== IndexType::REGULAR && $index->getType() !== IndexType::UNIQUE) {
            return false;
        }

        foreach ($columnNames as $i => $columnName) {
            if (
                $indexedColumns[$i]->getLength() !== null
                || ! $indexedColumns[$i]->getColumnName()->equals($columnName, $folding)
            ) {
                return false;
            }
        }

        return true;
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

        foreach ($prefix as $i => $columnName) {
            if (! $columns[$i]->equals($columnName, $folding)) {
                return false;
            }
        }

        return true;
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

        if ($editor === null) {
            return $table;
        }

        return $editor->create();
    }
}
