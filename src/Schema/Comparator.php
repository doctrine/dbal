<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

use function array_map;
use function assert;
use function count;
use function strtolower;

/**
 * Compares two Schemas and return an instance of SchemaDiff.
 */
class Comparator
{
    /** @internal The comparator can be only instantiated by a schema manager. */
    public function __construct(private readonly AbstractPlatform $platform)
    {
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
            if ($oldSchema->hasNamespace($newNamespace)) {
                continue;
            }

            $createdSchemas[] = $newNamespace;
        }

        foreach ($oldSchema->getNamespaces() as $oldNamespace) {
            if ($newSchema->hasNamespace($oldNamespace)) {
                continue;
            }

            $droppedSchemas[] = $oldNamespace;
        }

        foreach ($newSchema->getTables() as $newTable) {
            $newTableName = $newTable->getShortestName($newSchema->getName());
            if (! $oldSchema->hasTable($newTableName)) {
                $createdTables[] = $newSchema->getTable($newTableName);
            } else {
                $tableDiff = $this->compareTables(
                    $oldSchema->getTable($newTableName),
                    $newSchema->getTable($newTableName),
                );

                if (! $tableDiff->isEmpty()) {
                    $alteredTables[] = $tableDiff;
                }
            }
        }

        // Check if there are tables removed
        foreach ($oldSchema->getTables() as $oldTable) {
            $oldTableName = $oldTable->getShortestName($oldSchema->getName());

            $oldTable = $oldSchema->getTable($oldTableName);
            if ($newSchema->hasTable($oldTableName)) {
                continue;
            }

            $droppedTables[] = $oldTable;
        }

        foreach ($newSchema->getSequences() as $newSequence) {
            $newSequenceName = $newSequence->getShortestName($newSchema->getName());
            if (! $oldSchema->hasSequence($newSequenceName)) {
                if (! $this->isAutoIncrementSequenceInSchema($oldSchema, $newSequence)) {
                    $createdSequences[] = $newSequence;
                }
            } else {
                if ($this->diffSequence($newSequence, $oldSchema->getSequence($newSequenceName))) {
                    $alteredSequences[] = $newSchema->getSequence($newSequenceName);
                }
            }
        }

        foreach ($oldSchema->getSequences() as $oldSequence) {
            if ($this->isAutoIncrementSequenceInSchema($newSchema, $oldSequence)) {
                continue;
            }

            $oldSequenceName = $oldSequence->getShortestName($oldSchema->getName());

            if ($newSchema->hasSequence($oldSequenceName)) {
                continue;
            }

            $droppedSequences[] = $oldSequence;
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

    private function isAutoIncrementSequenceInSchema(Schema $schema, Sequence $sequence): bool
    {
        foreach ($schema->getTables() as $table) {
            if ($sequence->isAutoIncrementsFor($table)) {
                return true;
            }
        }

        return false;
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
        $addedColumns        = [];
        $modifiedColumns     = [];
        $droppedColumns      = [];
        $addedIndexes        = [];
        $modifiedIndexes     = [];
        $droppedIndexes      = [];
        $addedForeignKeys    = [];
        $modifiedForeignKeys = [];
        $droppedForeignKeys  = [];

        $oldColumns = $oldTable->getColumns();
        $newColumns = $newTable->getColumns();

        // See if all the columns in the old table exist in the new table
        foreach ($newColumns as $newColumn) {
            $newColumnName = strtolower($newColumn->getName());

            if ($oldTable->hasColumn($newColumnName)) {
                continue;
            }

            $addedColumns[$newColumnName] = $newColumn;
        }

        // See if there are any removed columns in the new table
        foreach ($oldColumns as $oldColumn) {
            $oldColumnName = strtolower($oldColumn->getName());

            // See if column is removed in the new table.
            if (! $newTable->hasColumn($oldColumnName)) {
                $droppedColumns[$oldColumnName] = $oldColumn;

                continue;
            }

            $newColumn = $newTable->getColumn($oldColumnName);

            if ($this->columnsEqual($oldColumn, $newColumn)) {
                continue;
            }

            $modifiedColumns[$oldColumnName] = new ColumnDiff($oldColumn, $newColumn);
        }

        $renamedColumnNames = $newTable->getRenamedColumns();

        foreach ($addedColumns as $addedColumnName => $addedColumn) {
            if (! isset($renamedColumnNames[$addedColumn->getName()])) {
                continue;
            }

            $removedColumnName = strtolower($renamedColumnNames[$addedColumn->getName()]);
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

        $this->detectRenamedColumns($modifiedColumns, $addedColumns, $droppedColumns);

        $oldIndexes = $oldTable->getIndexes();
        $newIndexes = $newTable->getIndexes();

        // See if all the indexes from the old table exist in the new one
        foreach ($newIndexes as $newIndexName => $newIndex) {
            if (($newIndex->isPrimary() && $oldTable->getPrimaryKey() !== null) || $oldTable->hasIndex($newIndexName)) {
                continue;
            }

            $addedIndexes[$newIndexName] = $newIndex;
        }

        // See if there are any removed indexes in the new table
        foreach ($oldIndexes as $oldIndexName => $oldIndex) {
            // See if the index is removed in the new table.
            if (
                ($oldIndex->isPrimary() && $newTable->getPrimaryKey() === null) ||
                ! $oldIndex->isPrimary() && ! $newTable->hasIndex($oldIndexName)
            ) {
                $droppedIndexes[$oldIndexName] = $oldIndex;

                continue;
            }

            // See if index has changed in the new table.
            $newIndex = $oldIndex->isPrimary() ? $newTable->getPrimaryKey() : $newTable->getIndex($oldIndexName);
            assert($newIndex instanceof Index);

            if (! $this->diffIndex($oldIndex, $newIndex)) {
                continue;
            }

            $modifiedIndexes[] = $newIndex;
        }

        $renamedIndexes = $this->detectRenamedIndexes($addedIndexes, $droppedIndexes);

        $oldForeignKeys = $oldTable->getForeignKeys();
        $newForeignKeys = $newTable->getForeignKeys();

        foreach ($oldForeignKeys as $oldKey => $oldForeignKey) {
            foreach ($newForeignKeys as $newKey => $newForeignKey) {
                if ($this->diffForeignKey($oldForeignKey, $newForeignKey) === false) {
                    unset($oldForeignKeys[$oldKey], $newForeignKeys[$newKey]);
                } else {
                    if (strtolower($oldForeignKey->getName()) === strtolower($newForeignKey->getName())) {
                        $modifiedForeignKeys[] = $newForeignKey;

                        unset($oldForeignKeys[$oldKey], $newForeignKeys[$newKey]);
                    }
                }
            }
        }

        foreach ($oldForeignKeys as $oldForeignKey) {
            $droppedForeignKeys[] = $oldForeignKey;
        }

        foreach ($newForeignKeys as $newForeignKey) {
            $addedForeignKeys[] = $newForeignKey;
        }

        return new TableDiff(
            $oldTable,
            addedColumns: $addedColumns,
            changedColumns: $modifiedColumns,
            droppedColumns: $droppedColumns,
            addedIndexes: $addedIndexes,
            modifiedIndexes: $modifiedIndexes,
            droppedIndexes: $droppedIndexes,
            renamedIndexes: $renamedIndexes,
            addedForeignKeys: $addedForeignKeys,
            modifiedForeignKeys: $modifiedForeignKeys,
            droppedForeignKeys: $droppedForeignKeys,
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
                if (! $this->columnsEqual($addedColumn, $removedColumn)) {
                    continue;
                }

                $candidatesByName[$addedColumnName][] = [$removedColumn, $addedColumn];
            }
        }

        foreach ($candidatesByName as $addedColumnName => $candidates) {
            if (count($candidates) !== 1) {
                continue;
            }

            [$oldColumn, $newColumn] = $candidates[0];
            $oldColumnName           = strtolower($oldColumn->getName());

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
     * Try to find indexes that only changed their name, rename operations maybe cheaper than add/drop
     * however ambiguities between different possibilities should not lead to renaming at all.
     *
     * @param array<string,Index> $addedIndexes
     * @param array<string,Index> $removedIndexes
     *
     * @return array<string,Index>
     */
    private function detectRenamedIndexes(array &$addedIndexes, array &$removedIndexes): array
    {
        $candidatesByName = [];

        // Gather possible rename candidates by comparing each added and removed index based on semantics.
        foreach ($addedIndexes as $addedIndexName => $addedIndex) {
            foreach ($removedIndexes as $removedIndex) {
                if ($this->diffIndex($addedIndex, $removedIndex)) {
                    continue;
                }

                $candidatesByName[$addedIndex->getName()][] = [$removedIndex, $addedIndex, $addedIndexName];
            }
        }

        $renamedIndexes = [];

        foreach ($candidatesByName as $candidates) {
            // If the current rename candidate contains exactly one semantically equal index,
            // we can safely rename it.
            // Otherwise, it is unclear if a rename action is really intended,
            // therefore we let those ambiguous indexes be added/dropped.
            if (count($candidates) !== 1) {
                continue;
            }

            [$removedIndex, $addedIndex] = $candidates[0];

            $removedIndexName = strtolower($removedIndex->getName());
            $addedIndexName   = strtolower($addedIndex->getName());

            if (isset($renamedIndexes[$removedIndexName])) {
                continue;
            }

            $renamedIndexes[$removedIndexName] = $addedIndex;
            unset(
                $addedIndexes[$addedIndexName],
                $removedIndexes[$removedIndexName],
            );
        }

        return $renamedIndexes;
    }

    protected function diffForeignKey(ForeignKeyConstraint $key1, ForeignKeyConstraint $key2): bool
    {
        if (
            array_map('strtolower', $key1->getUnquotedLocalColumns())
            !== array_map('strtolower', $key2->getUnquotedLocalColumns())
        ) {
            return true;
        }

        if (
            array_map('strtolower', $key1->getUnquotedForeignColumns())
            !== array_map('strtolower', $key2->getUnquotedForeignColumns())
        ) {
            return true;
        }

        if ($key1->getUnqualifiedForeignTableName() !== $key2->getUnqualifiedForeignTableName()) {
            return true;
        }

        if ($key1->onUpdate() !== $key2->onUpdate()) {
            return true;
        }

        return $key1->onDelete() !== $key2->onDelete();
    }

    /**
     * Compares the definitions of the given columns
     */
    protected function columnsEqual(Column $column1, Column $column2): bool
    {
        return $this->platform->columnsEqual($column1, $column2);
    }

    /**
     * Finds the difference between the indexes $index1 and $index2.
     *
     * Compares $index1 with $index2 and returns true if there are any
     * differences or false in case there are no differences.
     */
    protected function diffIndex(Index $index1, Index $index2): bool
    {
        return ! ($index1->isFulfilledBy($index2) && $index2->isFulfilledBy($index1));
    }
}
