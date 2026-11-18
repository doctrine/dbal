<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Collections\UnqualifiedNamedObjectSet;
use Doctrine\DBAL\Schema\Exception\UnspecifiedConstraintName;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;

use function array_any;
use function array_filter;
use function array_values;
use function assert;
use function count;
use function in_array;
use function strtolower;

/**
 * Compares two Schemas and return an instance of SchemaDiff.
 */
class Comparator
{
    /** @internal The comparator can be only instantiated by a schema manager. */
    public function __construct(
        private readonly AbstractPlatform $platform,
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

        $oldIndexes = $this->getIndexesExcept($oldTable, $this->getOldIndexesDerivedFromNewTable($oldTable, $newTable));
        $newIndexes = $this->getDeclaredIndexes($newTable);
        $folding    = $this->platform->getUnquotedIdentifierFolding();

        [$addedUniqueConstraints, $droppedUniqueConstraintNames] = $this->compareUniqueConstraints(
            $oldTable,
            $newTable,
            $oldIndexes,
            $newIndexes,
            $folding,
        );

        // See if all the indexes from the old table exist in the new one
        foreach ($newIndexes as $newIndex) {
            $newIndexName = $newIndex->getObjectName();

            if ($oldIndexes->get($newIndexName) === null) {
                $addedIndexes[] = $newIndex;
            }
        }

        // See if there are any removed indexes in the new table
        foreach ($oldIndexes as $oldIndex) {
            $oldIndexName = $oldIndex->getObjectName();

            $newIndex = $newIndexes->get($oldIndexName);

            if ($newIndex === null) {
                $droppedIndexes[] = $oldIndex;

                continue;
            }

            // See if index has changed in the new table.
            if ($oldIndex->equals($newIndex, $folding)) {
                continue;
            }

            $droppedIndexes[] = $oldIndex;
            $addedIndexes[]   = $newIndex;
        }

        if ($this->config->getDetectRenamedIndexes()) {
            $indexRenames = $this->detectIndexRenames($addedIndexes, $droppedIndexes, $folding);
        }

        if ($droppedPrimaryKeyConstraint === null) {
            $droppedIndexes = $this->withoutPrimaryKeyBackingIndex($droppedIndexes, $oldTable, $newIndexes);
        }

        [$addedForeignKeys, $droppedForeignKeyConstraintNames] = $this->compareConstraints(
            $oldTable->getForeignKeys(),
            $newTable->getForeignKeys(),
            static fn (ForeignKeyConstraint $old, ForeignKeyConstraint $new): bool => $old->equals($new, $folding),
            UnspecifiedConstraintName::forForeignKeyConstraint(...),
        );

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

    /**
     * Returns the given dropped indexes except the one that backs the old primary key, unless the new table declares an
     * index of the same name. The database refuses to drop the index while the key exists.
     *
     * @param array<Index>                     $droppedIndexes
     * @param UnqualifiedNamedObjectSet<Index> $newIndexes     The declared indexes of the new table.
     *
     * @return array<Index>
     */
    private function withoutPrimaryKeyBackingIndex(
        array $droppedIndexes,
        Table $oldTable,
        UnqualifiedNamedObjectSet $newIndexes,
    ): array {
        $backingIndex = $oldTable->getPrimaryKeyConstraint()?->getBackingIndex();

        if ($backingIndex === null || $newIndexes->get($backingIndex->getObjectName()) !== null) {
            return $droppedIndexes;
        }

        return array_values(array_filter(
            $droppedIndexes,
            static fn (Index $index): bool => $index !== $backingIndex,
        ));
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
     * Returns the unique constraints to add and the names of the ones to drop.
     *
     * Only declared constraints are compared. A declared constraint of one table that the other table has as a unique
     * index is left out, and that index is removed from the given indexes of its table.
     *
     * @param UnqualifiedNamedObjectSet<Index> $oldIndexes The declared indexes of the old table.
     * @param UnqualifiedNamedObjectSet<Index> $newIndexes The declared indexes of the new table.
     *
     * @return array{list<UniqueConstraint>, list<UnqualifiedName>} the constraints to add and the names of the ones to
     *                                                              drop
     */
    private function compareUniqueConstraints(
        Table $oldTable,
        Table $newTable,
        UnqualifiedNamedObjectSet $oldIndexes,
        UnqualifiedNamedObjectSet $newIndexes,
        UnquotedIdentifierFolding $folding,
    ): array {
        [$oldConstraints, $newConstraints, $modified] = $this->matchConstraints(
            $this->getDeclaredUniqueConstraints($oldTable),
            $this->getDeclaredUniqueConstraints($newTable),
            static fn (UniqueConstraint $old, UniqueConstraint $new): bool => $old->equals($new, $folding),
        );

        $newConstraints = $this->withoutUniqueConstraintsDerivedFromIndexes(
            $newConstraints,
            $oldIndexes,
            $newIndexes,
            $folding,
        );
        $oldConstraints = $this->withoutUniqueConstraintsDerivedFromIndexes(
            $oldConstraints,
            $newIndexes,
            $oldIndexes,
            $folding,
        );

        return $this->toAddedAndDropped(
            $oldConstraints,
            $newConstraints,
            $modified,
            UnspecifiedConstraintName::forUniqueConstraint(...),
        );
    }

    /**
     * Returns the given declared unique constraints of one table except those that equal a unique constraint derived
     * from an index of the other table, and removes the source index of each such constraint from
     * <code>$otherIndexes</code>.
     *
     * @param list<UniqueConstraint>           $constraints
     * @param UnqualifiedNamedObjectSet<Index> $otherIndexes The declared indexes of the other table.
     * @param UnqualifiedNamedObjectSet<Index> $indexes      The declared indexes of the constraints' table.
     *
     * @return list<UniqueConstraint>
     */
    private function withoutUniqueConstraintsDerivedFromIndexes(
        array $constraints,
        UnqualifiedNamedObjectSet $otherIndexes,
        UnqualifiedNamedObjectSet $indexes,
        UnquotedIdentifierFolding $folding,
    ): array {
        $remaining = [];

        foreach ($this->namedFirst($constraints) as $constraint) {
            $index = $this->findSourceIndex($constraint, $otherIndexes, $indexes, $folding);

            if ($index === null) {
                $remaining[] = $constraint;

                continue;
            }

            $otherIndexes->remove($index->getObjectName());
        }

        return $remaining;
    }

    /**
     * Returns the candidate that is the source of a derived unique constraint equal to the given one, or null if there
     * is none.
     *
     * @param UnqualifiedNamedObjectSet<Index> $candidates
     * @param UnqualifiedNamedObjectSet<Index> $indexes    The declared indexes of the constraint's table.
     */
    private function findSourceIndex(
        UniqueConstraint $constraint,
        UnqualifiedNamedObjectSet $candidates,
        UnqualifiedNamedObjectSet $indexes,
        UnquotedIdentifierFolding $folding,
    ): ?Index {
        foreach ($candidates as $index) {
            // The constraint's table also declares the index, so the two are compared as indexes.
            if ($indexes->get($index->getObjectName()) !== null) {
                continue;
            }

            // An index over column prefixes enforces more than the constraint derived from it.
            if ($this->hasPrefixLengths($index)) {
                continue;
            }

            if ($index->getDerivedUniqueConstraint()?->equals($constraint, $folding) === true) {
                return $index;
            }
        }

        return null;
    }

    private function hasPrefixLengths(Index $index): bool
    {
        return array_any(
            $index->getIndexedColumns(),
            static fn (IndexedColumn $column): bool => $column->getLength() !== null,
        );
    }

    /** @return list<UniqueConstraint> */
    private function getDeclaredUniqueConstraints(Table $table): array
    {
        $constraints = [];

        foreach ($table->getUniqueConstraints() as $constraint) {
            if ($constraint->getOrigin() === ObjectOrigin::DECLARED) {
                $constraints[] = $constraint;
            }
        }

        return $constraints;
    }

    /**
     * Compares the old constraints of a table with the new ones.
     *
     * @param list<T>                               $oldConstraints
     * @param list<T>                               $newConstraints
     * @param callable(T, T): bool                  $equals          Returns whether an old constraint and a new one
     *                                                               are equal.
     * @param callable(): UnspecifiedConstraintName $unspecifiedName Returns the exception reporting a constraint of
     *                                                               this kind whose name is unspecified.
     *
     * @return array{list<T>, list<UnqualifiedName>} the constraints to add and the names of the ones to drop
     *
     * @template T of OptionallyNamedObject<UnqualifiedName>
     */
    private function compareConstraints(
        array $oldConstraints,
        array $newConstraints,
        callable $equals,
        callable $unspecifiedName,
    ): array {
        [$oldConstraints, $newConstraints, $modified] = $this->matchConstraints(
            $oldConstraints,
            $newConstraints,
            $equals,
        );

        return $this->toAddedAndDropped($oldConstraints, $newConstraints, $modified, $unspecifiedName);
    }

    /**
     * @param list<T>                               $oldConstraints  The unmatched old constraints.
     * @param list<T>                               $newConstraints  The unmatched new constraints.
     * @param list<array{T, T}>                     $modified        The modified constraints as old and new.
     * @param callable(): UnspecifiedConstraintName $unspecifiedName Returns the exception reporting a constraint of
     *                                                               this kind whose name is unspecified.
     *
     * @return array{list<T>, list<UnqualifiedName>} the constraints to add and the names of the ones to drop
     *
     * @template T of OptionallyNamedObject<UnqualifiedName>
     */
    private function toAddedAndDropped(
        array $oldConstraints,
        array $newConstraints,
        array $modified,
        callable $unspecifiedName,
    ): array {
        $addedConstraints       = [];
        $droppedConstraintNames = [];

        foreach ($modified as [$oldConstraint, $newConstraint]) {
            $name = $oldConstraint->getObjectName();
            assert($name !== null);

            $droppedConstraintNames[] = $name;
            $addedConstraints[]       = $newConstraint;
        }

        foreach ($oldConstraints as $oldConstraint) {
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
     * Constraints equal to one on the other side are left out of the result. An old and a new constraint that share
     * a name but differ are returned as a modified pair.
     *
     * Named constraints are matched first, because an unnamed constraint equals a named one with the same definition
     * and could otherwise take the match of a named one.
     *
     * @param list<T>              $oldConstraints
     * @param list<T>              $newConstraints
     * @param callable(T, T): bool $equals         Returns whether an old constraint and a new one are equal.
     *
     * @return array{list<T>, list<T>, list<array{T, T}>} the unmatched old constraints, the unmatched new ones, and
     *                                                    the modified ones as old and new
     *
     * @template T of OptionallyNamedObject<UnqualifiedName>
     */
    private function matchConstraints(array $oldConstraints, array $newConstraints, callable $equals): array
    {
        $oldConstraints = $this->namedFirst($oldConstraints);
        $unmatchedNew   = [];
        $modified       = [];

        foreach ($this->namedFirst($newConstraints) as $newConstraint) {
            foreach ($oldConstraints as $oldKey => $oldConstraint) {
                if ($equals($oldConstraint, $newConstraint)) {
                    unset($oldConstraints[$oldKey]);

                    continue 2;
                }
            }

            $newName = $newConstraint->getObjectName();

            if ($newName !== null) {
                foreach ($oldConstraints as $oldKey => $oldConstraint) {
                    $oldName = $oldConstraint->getObjectName();

                    if (
                        $oldName !== null
                        && strtolower($oldName->getIdentifier()->getValue())
                        === strtolower($newName->getIdentifier()->getValue())
                    ) {
                        $modified[] = [$oldConstraint, $newConstraint];
                        unset($oldConstraints[$oldKey]);

                        continue 2;
                    }
                }
            }

            $unmatchedNew[] = $newConstraint;
        }

        return [array_values($oldConstraints), $unmatchedNew, $modified];
    }

    /**
     * Returns the given constraints with the named ones first, keeping the order within each group.
     *
     * @param list<T> $constraints
     *
     * @return list<T>
     *
     * @template T of OptionallyNamedObject<UnqualifiedName>
     */
    private function namedFirst(array $constraints): array
    {
        $named   = [];
        $unnamed = [];

        foreach ($constraints as $constraint) {
            if ($constraint->getObjectName() !== null) {
                $named[] = $constraint;
            } else {
                $unnamed[] = $constraint;
            }
        }

        return [...$named, ...$unnamed];
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

    /**
     * Returns the indexes of the old table that are derived from objects of the new table.
     *
     * @return list<Index>
     */
    protected function getOldIndexesDerivedFromNewTable(Table $oldTable, Table $newTable): array
    {
        $indexes = [];

        foreach ($oldTable->getIndexes() as $index) {
            if ($index->getOrigin() === ObjectOrigin::DERIVED) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /** @return UnqualifiedNamedObjectSet<Index> */
    private function getDeclaredIndexes(Table $table): UnqualifiedNamedObjectSet
    {
        /** @var UnqualifiedNamedObjectSet<Index> $indexes */
        $indexes = new UnqualifiedNamedObjectSet();

        foreach ($table->getIndexes() as $index) {
            if ($index->getOrigin() === ObjectOrigin::DECLARED) {
                $indexes->add($index);
            }
        }

        return $indexes;
    }

    /**
     * @param list<Index> $excludedIndexes
     *
     * @return UnqualifiedNamedObjectSet<Index>
     */
    private function getIndexesExcept(Table $table, array $excludedIndexes): UnqualifiedNamedObjectSet
    {
        /** @var UnqualifiedNamedObjectSet<Index> $indexes */
        $indexes = new UnqualifiedNamedObjectSet();

        foreach ($table->getIndexes() as $index) {
            if (! in_array($index, $excludedIndexes, true)) {
                $indexes->add($index);
            }
        }

        return $indexes;
    }
}
