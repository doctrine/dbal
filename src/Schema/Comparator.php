<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
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
     * Returns a SchemaDiff object containing the differences between the schemas $fromSchema and $toSchema.
     */
    public function compareSchemas(Schema $fromSchema, Schema $toSchema): SchemaDiff
    {
        $createdSchemas   = [];
        $droppedSchemas   = [];
        $createdTables    = [];
        $alteredTables    = [];
        $droppedTables    = [];
        $createdSequences = [];
        $alteredSequences = [];
        $droppedSequences = [];

        foreach ($toSchema->getNamespaces() as $namespace) {
            if ($fromSchema->hasNamespace($namespace)) {
                continue;
            }

            $createdSchemas[$namespace] = $namespace;
        }

        foreach ($fromSchema->getNamespaces() as $namespace) {
            if ($toSchema->hasNamespace($namespace)) {
                continue;
            }

            $droppedSchemas[$namespace] = $namespace;
        }

        foreach ($toSchema->getTables() as $table) {
            $tableName = $table->getShortestName($toSchema->getName());
            if (! $fromSchema->hasTable($tableName)) {
                $createdTables[$tableName] = $toSchema->getTable($tableName);
            } else {
                $tableDifferences = $this->diffTable(
                    $fromSchema->getTable($tableName),
                    $toSchema->getTable($tableName),
                );

                if ($tableDifferences !== null) {
                    $alteredTables[$tableName] = $tableDifferences;
                }
            }
        }

        /* Check if there are tables removed */
        foreach ($fromSchema->getTables() as $table) {
            $tableName = $table->getShortestName($fromSchema->getName());

            $table = $fromSchema->getTable($tableName);
            if ($toSchema->hasTable($tableName)) {
                continue;
            }

            $droppedTables[$tableName] = $table;
        }

        foreach ($toSchema->getSequences() as $sequence) {
            $sequenceName = $sequence->getShortestName($toSchema->getName());
            if (! $fromSchema->hasSequence($sequenceName)) {
                if (! $this->isAutoIncrementSequenceInSchema($fromSchema, $sequence)) {
                    $createdSequences[] = $sequence;
                }
            } else {
                if ($this->diffSequence($sequence, $fromSchema->getSequence($sequenceName))) {
                    $alteredSequences[] = $toSchema->getSequence($sequenceName);
                }
            }
        }

        foreach ($fromSchema->getSequences() as $sequence) {
            if ($this->isAutoIncrementSequenceInSchema($toSchema, $sequence)) {
                continue;
            }

            $sequenceName = $sequence->getShortestName($fromSchema->getName());

            if ($toSchema->hasSequence($sequenceName)) {
                continue;
            }

            $droppedSequences[] = $sequence;
        }

        return new SchemaDiff(
            $createdTables,
            $alteredTables,
            $droppedTables,
            $createdSchemas,
            $droppedSchemas,
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
     * Returns the difference between the tables $fromTable and $toTable.
     *
     * If there are no differences this method returns null.
     *
     * @throws Exception
     */
    public function diffTable(Table $fromTable, Table $toTable): ?TableDiff
    {
        $hasChanges = false;

        $addedColumns        = [];
        $modifiedColumns     = [];
        $droppedColumns      = [];
        $addedIndexes        = [];
        $modifiedIndexes     = [];
        $droppedIndexes      = [];
        $addedForeignKeys    = [];
        $modifiedForeignKeys = [];
        $droppedForeignKeys  = [];

        $fromTableColumns = $fromTable->getColumns();
        $toTableColumns   = $toTable->getColumns();

        /* See if all the columns in "from" table exist in "to" table */
        foreach ($toTableColumns as $column) {
            $columnName = strtolower($column->getName());

            if ($fromTable->hasColumn($columnName)) {
                continue;
            }

            $addedColumns[$columnName] = $column;

            $hasChanges = true;
        }

        /* See if there are any removed columns in "to" table */
        foreach ($fromTableColumns as $column) {
            $columnName = strtolower($column->getName());

            // See if column is removed in "to" table.
            if (! $toTable->hasColumn($columnName)) {
                $droppedColumns[$columnName] = $column;

                $hasChanges = true;
                continue;
            }

            $toColumn = $toTable->getColumn($columnName);

            if ($this->columnsEqual($column, $toColumn)) {
                continue;
            }

            $modifiedColumns[] = new ColumnDiff($column, $toColumn);

            $hasChanges = true;
        }

        $renamedColumns = $this->detectRenamedColumns($addedColumns, $droppedColumns);

        $fromTableIndexes = $fromTable->getIndexes();
        $toTableIndexes   = $toTable->getIndexes();

        /* See if all the indexes in "from" table exist in "to" table */
        foreach ($toTableIndexes as $indexName => $index) {
            if (($index->isPrimary() && $fromTable->getPrimaryKey() !== null) || $fromTable->hasIndex($indexName)) {
                continue;
            }

            $addedIndexes[$indexName] = $index;

            $hasChanges = true;
        }

        /* See if there are any removed indexes in "to" table */
        foreach ($fromTableIndexes as $indexName => $index) {
            // See if index is removed in "to" table.
            if (
                ($index->isPrimary() && $toTable->getPrimaryKey() === null) ||
                ! $index->isPrimary() && ! $toTable->hasIndex($indexName)
            ) {
                $droppedIndexes[$indexName] = $index;

                $hasChanges = true;
                continue;
            }

            // See if index has changed in "to" table.
            $toTableIndex = $index->isPrimary() ? $toTable->getPrimaryKey() : $toTable->getIndex($indexName);
            assert($toTableIndex instanceof Index);

            if (! $this->diffIndex($index, $toTableIndex)) {
                continue;
            }

            $modifiedIndexes[] = $toTableIndex;

            $hasChanges = true;
        }

        $renamedIndexes = $this->detectRenamedIndexes($addedIndexes, $droppedIndexes);

        $fromForeignKeys = $fromTable->getForeignKeys();
        $toForeignKeys   = $toTable->getForeignKeys();

        foreach ($fromForeignKeys as $fromKey => $fromConstraint) {
            foreach ($toForeignKeys as $toKey => $toConstraint) {
                if ($this->diffForeignKey($fromConstraint, $toConstraint) === false) {
                    unset($fromForeignKeys[$fromKey], $toForeignKeys[$toKey]);
                } else {
                    if (strtolower($fromConstraint->getName()) === strtolower($toConstraint->getName())) {
                        $modifiedForeignKeys[] = $toConstraint;

                        $hasChanges = true;
                        unset($fromForeignKeys[$fromKey], $toForeignKeys[$toKey]);
                    }
                }
            }
        }

        foreach ($fromForeignKeys as $fromConstraint) {
            $droppedForeignKeys[] = $fromConstraint;

            $hasChanges = true;
        }

        foreach ($toForeignKeys as $toConstraint) {
            $addedForeignKeys[] = $toConstraint;

            $hasChanges = true;
        }

        if (! $hasChanges) {
            return null;
        }

        return new TableDiff(
            $fromTable,
            $addedColumns,
            $modifiedColumns,
            $droppedColumns,
            $renamedColumns,
            $addedIndexes,
            $modifiedIndexes,
            $droppedIndexes,
            $renamedIndexes,
            $addedForeignKeys,
            $modifiedForeignKeys,
            $droppedForeignKeys,
        );
    }

    /**
     * Try to find columns that only changed their name, rename operations maybe cheaper than add/drop
     * however ambiguities between different possibilities should not lead to renaming at all.
     *
     * @param array<string,Column> $addedColumns
     * @param array<string,Column> $removedColumns
     *
     * @return array<string,Column>
     *
     * @throws Exception
     */
    private function detectRenamedColumns(array &$addedColumns, array &$removedColumns): array
    {
        $candidatesByName = [];

        foreach ($addedColumns as $addedColumnName => $addedColumn) {
            foreach ($removedColumns as $removedColumn) {
                if (! $this->columnsEqual($addedColumn, $removedColumn)) {
                    continue;
                }

                $candidatesByName[$addedColumn->getName()][] = [$removedColumn, $addedColumn, $addedColumnName];
            }
        }

        $renamedColumns = [];

        foreach ($candidatesByName as $candidates) {
            if (count($candidates) !== 1) {
                continue;
            }

            [$removedColumn, $addedColumn] = $candidates[0];
            $removedColumnName             = $removedColumn->getName();
            $addedColumnName               = strtolower($addedColumn->getName());

            if (isset($renamedColumns[$removedColumnName])) {
                continue;
            }

            $renamedColumns[$removedColumnName] = $addedColumn;
            unset(
                $addedColumns[$addedColumnName],
                $removedColumns[strtolower($removedColumnName)],
            );
        }

        return $renamedColumns;
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
     *
     * @throws Exception
     */
    protected function columnsEqual(Column $column1, Column $column2): bool
    {
        return $this->platform->columnsEqual($column1, $column2);
    }

    /**
     * Finds the difference between the indexes $index1 and $index2.
     *
     * Compares $index1 with $index2 and returns $index2 if there are any
     * differences or false in case there are no differences.
     */
    protected function diffIndex(Index $index1, Index $index2): bool
    {
        return ! ($index1->isFulfilledBy($index2) && $index2->isFulfilledBy($index1));
    }
}
