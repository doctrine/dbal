<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Exception\UnspecifiedConstraintName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;

use function array_values;
use function assert;
use function count;
use function strtolower;

/**
 * Compares two Schemas and return an instance of SchemaDiff.
 */
class Comparator
{
    /** @internal The comparator can be only instantiated by a schema manager. */
    public function __construct(
        private readonly AbstractPlatform $platform,
        private readonly DerivedObjectProvider $derivedObjectProvider,
        private readonly ComparatorConfig $config = new ComparatorConfig(),
    ) {
    }

    /**
     * Returns the differences between the schemas.
     */
    public function compareSchemas(Schema $oldSchema, Schema $newSchema): SchemaDiff
    {
        $createdSchemas   = [];
        $droppedSchemas   = [];
        $createdTables    = [];
        $alteredTables    = [];
        $droppedTables    = [];
        $createdSequences = [];
        $alteredSequences = [];
        $droppedSequences = [];

        foreach ($newSchema->getNamespaces() as $newNamespace) {
            if (! $oldSchema->hasNamespace($newNamespace)) {
                $createdSchemas[] = $newNamespace;
            }
        }

        foreach ($oldSchema->getNamespaces() as $oldNamespace) {
            if (! $newSchema->hasNamespace($oldNamespace)) {
                $droppedSchemas[] = $oldNamespace;
            }
        }

        foreach ($newSchema->getTables() as $newTable) {
            $newTableName = $newTable->getObjectName()->toString();
            if (! $oldSchema->hasTable($newTableName)) {
                $createdTables[] = $newTable;
            } else {
                $tableDiff = $this->compareTables(
                    $oldSchema->getTable($newTableName),
                    $newTable,
                );

                if (! $tableDiff->isEmpty()) {
                    $alteredTables[] = $tableDiff;
                }
            }
        }

        // Check if there are tables removed
        foreach ($oldSchema->getTables() as $oldTable) {
            $oldTableName = $oldTable->getObjectName()->toString();

            $oldTable = $oldSchema->getTable($oldTableName);
            if (! $newSchema->hasTable($oldTableName)) {
                $droppedTables[] = $oldTable;
            }
        }

        foreach ($newSchema->getSequences() as $newSequence) {
            $newSequenceName = $newSequence->getObjectName()->toString();
            if (! $oldSchema->hasSequence($newSequenceName)) {
                $createdSequences[] = $newSequence;
            } else {
                if ($this->diffSequence($newSequence, $oldSchema->getSequence($newSequenceName))) {
                    $alteredSequences[] = $newSequence;
                }
            }
        }

        foreach ($oldSchema->getSequences() as $oldSequence) {
            $oldSequenceName = $oldSequence->getObjectName()->toString();

            if (! $newSchema->hasSequence($oldSequenceName)) {
                $droppedSequences[] = $oldSequence;
            }
        }

        return new SchemaDiff(
            $createdSchemas,
            $droppedSchemas,
            $createdTables,
            $alteredTables,
            $droppedTables,
            $createdSequences,
            $alteredSequences,
            $droppedSequences,
        );
    }

    public function diffSequence(Sequence $sequence1, Sequence $sequence2): bool
    {
        if ($sequence1->getAllocationSize() !== $sequence2->getAllocationSize()) {
            return true;
        }

        return $sequence1->getInitialValue() !== $sequence2->getInitialValue();
    }

    /**
     * Compares the tables and returns the difference between them.
     */
    public function compareTables(Table $oldTable, Table $newTable): TableDiff
    {
        $addedColumns                = [];
        $modifiedColumns             = [];
        $droppedColumns              = [];
        $addedIndexes                = [];
        $droppedIndexes              = [];
        $indexRenames                = [];
        $addedPrimaryKeyConstraint   = null;
        $droppedPrimaryKeyConstraint = null;

        $oldColumns = $oldTable->getColumns();
        $newColumns = $newTable->getColumns();

        // See if all the columns in the old table exist in the new table
        foreach ($newColumns as $newColumn) {
            $newColumnName = strtolower(
                $newColumn->getObjectName()
                    ->getIdentifier()
                    ->getValue(),
            );

            if (! $oldTable->hasColumn($newColumnName)) {
                $addedColumns[$newColumnName] = $newColumn;
            }
        }

        // See if there are any removed columns in the new table
        foreach ($oldColumns as $oldColumn) {
            $oldColumnName = strtolower(
                $oldColumn->getObjectName()
                    ->getIdentifier()
                    ->getValue(),
            );

            // See if column is removed in the new table.
            if (! $newTable->hasColumn($oldColumnName)) {
                $droppedColumns[$oldColumnName] = $oldColumn;

                continue;
            }

            $newColumn = $newTable->getColumn($oldColumnName);

            if (! $this->columnsEqual($oldColumn, $newColumn)) {
                $modifiedColumns[$oldColumnName] = new ColumnDiff($oldColumn, $newColumn);
            }
        }

        $renamedColumnNames = $newTable->getRenamedColumns();

        foreach ($addedColumns as $addedColumnName => $addedColumn) {
            if (
                ! isset(
                    $renamedColumnNames[$addedColumn->getObjectName()
                        ->getIdentifier()
                        ->getValue()],
                )
            ) {
                continue;
            }

            $removedColumnName = strtolower(
                $renamedColumnNames[$addedColumn->getObjectName()
                        ->getIdentifier()
                        ->getValue()],
            );

            // Explicitly renamed columns need to be diffed, because their types can also have changed
            $modifiedColumns[$removedColumnName] = new ColumnDiff(
                $droppedColumns[$removedColumnName],
                $addedColumn,
            );

            unset(
                $addedColumns[$addedColumnName],
                $droppedColumns[$removedColumnName],
            );
        }

        if ($this->config->getDetectRenamedColumns()) {
            $this->detectRenamedColumns($modifiedColumns, $addedColumns, $droppedColumns);
        }

        $oldPrimaryKeyConstraint = $oldTable->getPrimaryKeyConstraint();
        $newPrimaryKeyConstraint = $newTable->getPrimaryKeyConstraint();

        if (! $this->primaryKeyConstraintsEqual($oldPrimaryKeyConstraint, $newPrimaryKeyConstraint)) {
            $droppedPrimaryKeyConstraint = $oldPrimaryKeyConstraint;
            $addedPrimaryKeyConstraint   = $newPrimaryKeyConstraint;
        }

        $folding = $this->platform->getUnquotedIdentifierFolding();

        $derivedFromNewTable = $this->derivedObjectProvider->getDerivedObjects($newTable);
        $derivedFromOldTable = $this->derivedObjectProvider->getDerivedObjects($oldTable);

        [$addedUniqueConstraints, $droppedUniqueConstraintNames] = $this->compareConstraints(
            $oldTable->getUniqueConstraints(),
            $newTable->getUniqueConstraints(),
            static fn (UniqueConstraint $old, UniqueConstraint $new): bool => $old->equals($new, $folding),
            UnspecifiedConstraintName::forUniqueConstraint(...),
            $derivedFromNewTable,
            static fn (DerivedObject $d, UniqueConstraint $c): bool => $d->matchesUniqueConstraint($c, $folding),
        );

        [$addedForeignKeys, $droppedForeignKeyConstraintNames] = $this->compareConstraints(
            $oldTable->getForeignKeys(),
            $newTable->getForeignKeys(),
            static fn (ForeignKeyConstraint $old, ForeignKeyConstraint $new): bool => $old->equals($new, $folding),
            UnspecifiedConstraintName::forForeignKeyConstraint(...),
            $derivedFromNewTable,
            // A database can expose a unique constraint the table did not declare, but not a
            // foreign key one.
            static fn (): bool => false,
        );

        $oldIndexes = $oldTable->getIndexes();
        $newIndexes = $newTable->getIndexes();

        // See if all the indexes from the old table exist in the new one
        foreach ($newIndexes as $newIndex) {
            $newIndexName = $newIndex->getObjectName();

            if (! $oldTable->hasIndex($newIndexName->toString())) {
                $addedIndexes[] = $newIndex;
            }
        }

        // See if there are any removed indexes in the new table
        foreach ($oldIndexes as $oldIndex) {
            $oldIndexName = $oldIndex->getObjectName();

            if (! $newTable->hasIndex($oldIndexName->toString())) {
                $matchesIndex = static fn (DerivedObject $d): bool => $d->matchesIndex($oldIndex, $folding);

                if (
                    $this->consumeDerivedObject($derivedFromNewTable, $matchesIndex)
                    || $this->consumeDerivedObject($derivedFromOldTable, $matchesIndex)
                ) {
                    continue;
                }

                $droppedIndexes[] = $oldIndex;

                continue;
            }

            // See if index has changed in the new table.
            $newIndex = $newTable->getIndex($oldIndexName->toString());

            if ($oldIndex->equals($newIndex, $folding)) {
                continue;
            }

            $droppedIndexes[] = $oldIndex;
            $addedIndexes[]   = $newIndex;
        }

        if ($this->config->getDetectRenamedIndexes()) {
            $indexRenames = $this->detectIndexRenames($addedIndexes, $droppedIndexes, $folding);
        }

        return new TableDiff(
            $oldTable,
            addedColumns: $addedColumns,
            changedColumns: $modifiedColumns,
            droppedColumns: $droppedColumns,
            addedIndexes: $addedIndexes,
            droppedIndexes: $droppedIndexes,
            indexRenames: $indexRenames,
            addedForeignKeys: $addedForeignKeys,
            droppedForeignKeyConstraintNames: $droppedForeignKeyConstraintNames,
            addedPrimaryKeyConstraint: $addedPrimaryKeyConstraint,
            droppedPrimaryKeyConstraint: $droppedPrimaryKeyConstraint,
            addedUniqueConstraints: $addedUniqueConstraints,
            droppedUniqueConstraintNames: $droppedUniqueConstraintNames,
        );
    }

    /**
     * Try to find columns that only changed their name, rename operations maybe cheaper than add/drop
     * however ambiguities between different possibilities should not lead to renaming at all.
     *
     * @param array<string,ColumnDiff> $modifiedColumns
     * @param array<string,Column>     $addedColumns
     * @param array<string,Column>     $removedColumns
     */
    private function detectRenamedColumns(array &$modifiedColumns, array &$addedColumns, array &$removedColumns): void
    {
        /** @var array<string, array<array<Column>>> $candidatesByName */
        $candidatesByName = [];

        foreach ($addedColumns as $addedColumnName => $addedColumn) {
            foreach ($removedColumns as $removedColumn) {
                if ($this->columnsEqual($addedColumn, $removedColumn)) {
                    $candidatesByName[$addedColumnName][] = [$removedColumn, $addedColumn];
                }
            }
        }

        foreach ($candidatesByName as $addedColumnName => $candidates) {
            if (count($candidates) !== 1) {
                continue;
            }

            [$oldColumn, $newColumn] = $candidates[0];
            $oldColumnName           = strtolower(
                $oldColumn->getObjectName()
                    ->getIdentifier()
                    ->getValue(),
            );

            if (isset($modifiedColumns[$oldColumnName])) {
                continue;
            }

            $modifiedColumns[$oldColumnName] = new ColumnDiff(
                $oldColumn,
                $newColumn,
            );

            unset(
                $addedColumns[$addedColumnName],
                $removedColumns[$oldColumnName],
            );
        }
    }

    private function primaryKeyConstraintsEqual(
        ?PrimaryKeyConstraint $oldPrimaryKeyConstraint,
        ?PrimaryKeyConstraint $newPrimaryKeyConstraint,
    ): bool {
        if ($oldPrimaryKeyConstraint !== null && $newPrimaryKeyConstraint !== null) {
            return $oldPrimaryKeyConstraint->equals(
                $newPrimaryKeyConstraint,
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        return $oldPrimaryKeyConstraint === null && $newPrimaryKeyConstraint === null;
    }

    /**
     * Compares the old constraints of a table with the new ones.
     *
     * An old constraint that one of the derived objects matches is consumed along with it: the
     * database will have the constraint without the table declaring it.
     *
     * @param array<T>                              $oldConstraints
     * @param array<T>                              $newConstraints
     * @param callable(T, T): bool                  $equals          Returns whether an old constraint and a new one are
     *                                                               equal.
     * @param callable(): UnspecifiedConstraintName $unspecifiedName Returns the exception reporting a constraint of
     *                                                               this kind whose name is unspecified.
     * @param array<DerivedObject>                  $derivedObjects  The objects the platform derives from the new
     *                                                               table. A matched one is removed.
     * @param callable(DerivedObject, T): bool      $matches         Returns whether a derived object is the one the
     *                                                               platform derives for an old constraint.
     *
     * @return array{list<T>, list<UnqualifiedName>} the constraints to add and the names of the
     *                                               ones to drop
     *
     * @template T of OptionallyNamedObject<UnqualifiedName>
     */
    private function compareConstraints(
        array $oldConstraints,
        array $newConstraints,
        callable $equals,
        callable $unspecifiedName,
        array &$derivedObjects,
        callable $matches,
    ): array {
        $addedConstraints       = [];
        $droppedConstraintNames = [];

        [$oldConstraints, $newConstraints, $modifiedConstraints] = $this->matchConstraints(
            $oldConstraints,
            $newConstraints,
            $equals,
        );

        foreach ($modifiedConstraints as [$oldConstraint, $newConstraint]) {
            $name = $oldConstraint->getObjectName();
            assert($name !== null);

            $droppedConstraintNames[] = $name;
            $addedConstraints[]       = $newConstraint;
        }

        foreach ($oldConstraints as $oldConstraint) {
            $matchesConstraint = static fn (DerivedObject $d): bool => $matches($d, $oldConstraint);

            if ($this->consumeDerivedObject($derivedObjects, $matchesConstraint)) {
                continue;
            }

            $name = $oldConstraint->getObjectName();

            if ($name === null) {
                throw $unspecifiedName();
            }

            $droppedConstraintNames[] = $name;
        }

        foreach ($newConstraints as $newConstraint) {
            $addedConstraints[] = $newConstraint;
        }

        return [$addedConstraints, $droppedConstraintNames];
    }

    /**
     * Matches the old constraints of a table with the new ones.
     *
     * A constraint equal to one on the other side matches it and is left out of the result. One
     * that keeps its name but changes its definition is modified: it has to be dropped and added
     * back. What remains unmatched is dropped or added outright.
     *
     * @param array<T>             $oldConstraints
     * @param array<T>             $newConstraints
     * @param callable(T, T): bool $equals         Returns whether an old constraint and a new one
     *                                             are equal.
     *
     * @return array{list<T>, list<T>, list<array{T, T}>} the unmatched old constraints, the
     *                                                    unmatched new ones, and the modified ones
     *                                                    as old and new
     *
     * @template T of OptionallyNamedObject<UnqualifiedName>
     */
    private function matchConstraints(array $oldConstraints, array $newConstraints, callable $equals): array
    {
        $modifiedConstraints = [];

        foreach ($oldConstraints as $oldKey => $oldConstraint) {
            foreach ($newConstraints as $newKey => $newConstraint) {
                if ($equals($oldConstraint, $newConstraint)) {
                    unset($oldConstraints[$oldKey], $newConstraints[$newKey]);

                    continue 2;
                }

                $oldName = $oldConstraint->getObjectName();
                $newName = $newConstraint->getObjectName();

                if (
                    $oldName === null
                    || $newName === null
                    || strtolower($oldName->getIdentifier()->getValue())
                    !== strtolower($newName->getIdentifier()->getValue())
                ) {
                    continue;
                }

                $modifiedConstraints[] = [$oldConstraint, $newConstraint];

                unset($oldConstraints[$oldKey], $newConstraints[$newKey]);

                continue 2;
            }
        }

        return [array_values($oldConstraints), array_values($newConstraints), $modifiedConstraints];
    }

    /**
     * Removes the first derived object the predicate matches and reports whether there was one.
     *
     * @param array<DerivedObject>          $derivedObjects
     * @param callable(DerivedObject): bool $matches
     */
    private function consumeDerivedObject(array &$derivedObjects, callable $matches): bool
    {
        foreach ($derivedObjects as $key => $derivedObject) {
            if (! $matches($derivedObject)) {
                continue;
            }

            unset($derivedObjects[$key]);

            return true;
        }

        return false;
    }

    /**
     * Try to find indexes that only changed their name, rename operations maybe cheaper than add/drop
     * however ambiguities between different possibilities should not lead to renaming at all.
     *
     * @param array<Index> $addedIndexes
     * @param array<Index> $removedIndexes
     *
     * @return list<IndexRename>
     */
    private function detectIndexRenames(
        array &$addedIndexes,
        array &$removedIndexes,
        UnquotedIdentifierFolding $folding,
    ): array {
        $candidatesByName       = [];
        $removedIndexMatchCount = [];

        // Gather possible rename candidates by comparing each added and removed index based on semantics.
        foreach ($addedIndexes as $addedIndexKey => $addedIndex) {
            foreach ($removedIndexes as $removedIndexKey => $removedIndex) {
                if (! $addedIndex->equals($removedIndex, $folding)) {
                    continue;
                }

                $candidatesByName[$addedIndex->getObjectName()
                    ->getIdentifier()
                    ->getValue()][] = [$removedIndexKey, $addedIndexKey];

                $removedIndexMatchCount[$removedIndexKey] = ($removedIndexMatchCount[$removedIndexKey] ?? 0) + 1;
            }
        }

        $indexRenames = [];

        foreach ($candidatesByName as $candidates) {
            // If the current rename candidate contains exactly one semantically equal index,
            // we can safely rename it.
            // Otherwise, it is unclear if a rename action is really intended,
            // therefore we let those ambiguous indexes be added/dropped.
            if (count($candidates) !== 1) {
                continue;
            }

            [$removedIndexKey, $addedIndexKey] = $candidates[0];

            // Likewise, a removed index that matches more than one added index is ambiguous.
            if ($removedIndexMatchCount[$removedIndexKey] > 1) {
                continue;
            }

            $removedIndex = $removedIndexes[$removedIndexKey];

            $indexRenames[] = new IndexRename($removedIndex->getObjectName(), $addedIndexes[$addedIndexKey]);
            unset(
                $addedIndexes[$addedIndexKey],
                $removedIndexes[$removedIndexKey],
            );
        }

        return $indexRenames;
    }

    /**
     * Compares the definitions of the given columns
     */
    protected function columnsEqual(Column $column1, Column $column2): bool
    {
        return $this->platform->columnsEqual($column1, $column2);
    }
}
