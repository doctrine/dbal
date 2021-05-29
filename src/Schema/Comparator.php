<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function implode;
use function strtolower;

/**
 * Compares two Schemas and return an instance of SchemaDiff.
 */
class Comparator
{
    /**
     * @return SchemaDiff
     *
     * @throws SchemaException
     */
    public static function compareSchemas(Schema $fromSchema, Schema $toSchema, AbstractPlatform $platform)
    {
        $c = new self();

        return $c->compare($fromSchema, $toSchema, $platform);
    }

    /**
     * Returns a SchemaDiff object containing the differences between the schemas $fromSchema and $toSchema.
     *
     * The returned differences are returned in such a way that they contain the
     * operations to change the schema stored in $fromSchema to the schema that is
     * stored in $toSchema.
     *
     * @return SchemaDiff
     *
     * @throws SchemaException
     */
    public function compare(Schema $fromSchema, Schema $toSchema, AbstractPlatform $platform)
    {
        $diff             = new SchemaDiff();
        $diff->fromSchema = $fromSchema;

        $foreignKeysToTable = [];

        foreach ($toSchema->getNamespaces() as $namespace) {
            if ($fromSchema->hasNamespace($namespace)) {
                continue;
            }

            $diff->newNamespaces[$namespace] = $namespace;
        }

        foreach ($fromSchema->getNamespaces() as $namespace) {
            if ($toSchema->hasNamespace($namespace)) {
                continue;
            }

            $diff->removedNamespaces[$namespace] = $namespace;
        }

        foreach ($toSchema->getTables() as $table) {
            $tableName = $table->getShortestName($toSchema->getName());
            if (! $fromSchema->hasTable($tableName)) {
                $diff->newTables[$tableName] = $toSchema->getTable($tableName);
            } else {
                $tableDifferences = $this->diffTable(
                    $fromSchema->getTable($tableName),
                    $toSchema->getTable($tableName),
                    $platform
                );

                if ($tableDifferences !== false) {
                    $diff->changedTables[$tableName] = $tableDifferences;
                }
            }
        }

        /* Check if there are tables removed */
        foreach ($fromSchema->getTables() as $table) {
            $tableName = $table->getShortestName($fromSchema->getName());

            $table = $fromSchema->getTable($tableName);
            if (! $toSchema->hasTable($tableName)) {
                $diff->removedTables[$tableName] = $table;
            }

            // also remember all foreign keys that point to a specific table
            foreach ($table->getForeignKeys() as $foreignKey) {
                $foreignTable = strtolower($foreignKey->getForeignTableName());
                if (! isset($foreignKeysToTable[$foreignTable])) {
                    $foreignKeysToTable[$foreignTable] = [];
                }

                $foreignKeysToTable[$foreignTable][] = $foreignKey;
            }
        }

        foreach ($diff->removedTables as $tableName => $table) {
            if (! isset($foreignKeysToTable[$tableName])) {
                continue;
            }

            $diff->orphanedForeignKeys = array_merge($diff->orphanedForeignKeys, $foreignKeysToTable[$tableName]);

            // deleting duplicated foreign keys present on both on the orphanedForeignKey
            // and the removedForeignKeys from changedTables
            foreach ($foreignKeysToTable[$tableName] as $foreignKey) {
                // strtolower the table name to make if compatible with getShortestName
                $localTableName = strtolower($foreignKey->getLocalTableName());
                if (! isset($diff->changedTables[$localTableName])) {
                    continue;
                }

                foreach ($diff->changedTables[$localTableName]->removedForeignKeys as $key => $removedForeignKey) {
                    assert($removedForeignKey instanceof ForeignKeyConstraint);

                    // We check if the key is from the removed table if not we skip.
                    if ($tableName !== strtolower($removedForeignKey->getForeignTableName())) {
                        continue;
                    }

                    unset($diff->changedTables[$localTableName]->removedForeignKeys[$key]);
                }
            }
        }

        foreach ($toSchema->getSequences() as $sequence) {
            $sequenceName = $sequence->getShortestName($toSchema->getName());
            if (! $fromSchema->hasSequence($sequenceName)) {
                if (! $this->isAutoIncrementSequenceInSchema($fromSchema, $sequence)) {
                    $diff->newSequences[] = $sequence;
                }
            } else {
                if ($this->diffSequence($sequence, $fromSchema->getSequence($sequenceName))) {
                    $diff->changedSequences[] = $toSchema->getSequence($sequenceName);
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

            $diff->removedSequences[] = $sequence;
        }

        return $diff;
    }

    /**
     * @param Schema   $schema
     * @param Sequence $sequence
     *
     * @return bool
     */
    private function isAutoIncrementSequenceInSchema($schema, $sequence)
    {
        foreach ($schema->getTables() as $table) {
            if ($sequence->isAutoIncrementsFor($table)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function diffSequence(Sequence $sequence1, Sequence $sequence2)
    {
        if ($sequence1->getAllocationSize() !== $sequence2->getAllocationSize()) {
            return true;
        }

        return $sequence1->getInitialValue() !== $sequence2->getInitialValue();
    }

    /**
     * Returns the difference between the tables $fromTable and $toTable.
     *
     * If there are no differences this method returns the boolean false.
     *
     * @return TableDiff|false
     *
     * @throws SchemaException
     */
    public function diffTable(Table $fromTable, Table $toTable, AbstractPlatform $platform)
    {
        $changes                     = 0;
        $tableDifferences            = new TableDiff($fromTable->getName());
        $tableDifferences->fromTable = $fromTable;

        $fromTableColumns = $fromTable->getColumns();
        $toTableColumns   = $toTable->getColumns();

        /* See if all the columns in "from" table exist in "to" table */
        foreach ($toTableColumns as $columnName => $column) {
            if ($fromTable->hasColumn($columnName)) {
                continue;
            }

            $tableDifferences->addedColumns[$columnName] = $column;
            $changes++;
        }

        /* See if there are any removed columns in "to" table */
        foreach ($fromTableColumns as $columnName => $column) {
            // See if column is removed in "to" table.
            if (! $toTable->hasColumn($columnName)) {
                $tableDifferences->removedColumns[$columnName] = $column;
                $changes++;
                continue;
            }

            // See if column has changed properties in "to" table.
            $changedProperties = $this->diffColumn($column, $toTable->getColumn($columnName), $platform);

            if (count($changedProperties) === 0) {
                continue;
            }

            $columnDiff = new ColumnDiff($column->getName(), $toTable->getColumn($columnName), $changedProperties);

            $columnDiff->fromColumn                               = $column;
            $tableDifferences->changedColumns[$column->getName()] = $columnDiff;
            $changes++;
        }

        $this->detectColumnRenamings($tableDifferences, $platform);

        $fromTableIndexes = $fromTable->getIndexes();
        $toTableIndexes   = $toTable->getIndexes();

        /* See if all the indexes in "from" table exist in "to" table */
        foreach ($toTableIndexes as $indexName => $index) {
            if (($index->isPrimary() && $fromTable->hasPrimaryKey()) || $fromTable->hasIndex($indexName)) {
                continue;
            }

            $tableDifferences->addedIndexes[$indexName] = $index;
            $changes++;
        }

        /* See if there are any removed indexes in "to" table */
        foreach ($fromTableIndexes as $indexName => $index) {
            // See if index is removed in "to" table.
            if (
                ($index->isPrimary() && ! $toTable->hasPrimaryKey()) ||
                ! $index->isPrimary() && ! $toTable->hasIndex($indexName)
            ) {
                $tableDifferences->removedIndexes[$indexName] = $index;
                $changes++;
                continue;
            }

            // See if index has changed in "to" table.
            $toTableIndex = $index->isPrimary() ? $toTable->getPrimaryKey() : $toTable->getIndex($indexName);
            assert($toTableIndex instanceof Index);

            if (! $this->diffIndex($index, $toTableIndex)) {
                continue;
            }

            $tableDifferences->changedIndexes[$indexName] = $toTableIndex;
            $changes++;
        }

        $this->detectIndexRenamings($tableDifferences);

        $fromForeignKeys = $fromTable->getForeignKeys();
        $toForeignKeys   = $toTable->getForeignKeys();

        foreach ($fromForeignKeys as $fromKey => $fromConstraint) {
            foreach ($toForeignKeys as $toKey => $toConstraint) {
                if ($this->diffForeignKey($fromConstraint, $toConstraint) === false) {
                    unset($fromForeignKeys[$fromKey], $toForeignKeys[$toKey]);
                } else {
                    if (strtolower($fromConstraint->getName()) === strtolower($toConstraint->getName())) {
                        $tableDifferences->changedForeignKeys[] = $toConstraint;
                        $changes++;
                        unset($fromForeignKeys[$fromKey], $toForeignKeys[$toKey]);
                    }
                }
            }
        }

        foreach ($fromForeignKeys as $fromConstraint) {
            $tableDifferences->removedForeignKeys[] = $fromConstraint;
            $changes++;
        }

        foreach ($toForeignKeys as $toConstraint) {
            $tableDifferences->addedForeignKeys[] = $toConstraint;
            $changes++;
        }

        return $changes > 0 ? $tableDifferences : false;
    }

    /**
     * Try to find columns that only changed their name, rename operations maybe cheaper than add/drop
     * however ambiguities between different possibilities should not lead to renaming at all.
     *
     * @return void
     */
    private function detectColumnRenamings(TableDiff $tableDifferences, AbstractPlatform $platform)
    {
        $renameCandidates = [];
        foreach ($tableDifferences->addedColumns as $addedColumnName => $addedColumn) {
            foreach ($tableDifferences->removedColumns as $removedColumn) {
                if (count($this->diffColumn($addedColumn, $removedColumn, $platform)) !== 0) {
                    continue;
                }

                $renameCandidates[$addedColumn->getName()][] = [$removedColumn, $addedColumn, $addedColumnName];
            }
        }

        foreach ($renameCandidates as $candidateColumns) {
            if (count($candidateColumns) !== 1) {
                continue;
            }

            [$removedColumn, $addedColumn] = $candidateColumns[0];
            $removedColumnName             = strtolower($removedColumn->getName());
            $addedColumnName               = strtolower($addedColumn->getName());

            if (isset($tableDifferences->renamedColumns[$removedColumnName])) {
                continue;
            }

            $tableDifferences->renamedColumns[$removedColumnName] = $addedColumn;
            unset(
                $tableDifferences->addedColumns[$addedColumnName],
                $tableDifferences->removedColumns[$removedColumnName]
            );
        }
    }

    /**
     * Try to find indexes that only changed their name, rename operations maybe cheaper than add/drop
     * however ambiguities between different possibilities should not lead to renaming at all.
     *
     * @return void
     */
    private function detectIndexRenamings(TableDiff $tableDifferences)
    {
        $renameCandidates = [];

        // Gather possible rename candidates by comparing each added and removed index based on semantics.
        foreach ($tableDifferences->addedIndexes as $addedIndexName => $addedIndex) {
            foreach ($tableDifferences->removedIndexes as $removedIndex) {
                if ($this->diffIndex($addedIndex, $removedIndex)) {
                    continue;
                }

                $renameCandidates[$addedIndex->getName()][] = [$removedIndex, $addedIndex, $addedIndexName];
            }
        }

        foreach ($renameCandidates as $candidateIndexes) {
            // If the current rename candidate contains exactly one semantically equal index,
            // we can safely rename it.
            // Otherwise it is unclear if a rename action is really intended,
            // therefore we let those ambiguous indexes be added/dropped.
            if (count($candidateIndexes) !== 1) {
                continue;
            }

            [$removedIndex, $addedIndex] = $candidateIndexes[0];

            $removedIndexName = strtolower($removedIndex->getName());
            $addedIndexName   = strtolower($addedIndex->getName());

            if (isset($tableDifferences->renamedIndexes[$removedIndexName])) {
                continue;
            }

            $tableDifferences->renamedIndexes[$removedIndexName] = $addedIndex;
            unset(
                $tableDifferences->addedIndexes[$addedIndexName],
                $tableDifferences->removedIndexes[$removedIndexName]
            );
        }
    }

    /**
     * @return bool
     */
    public function diffForeignKey(ForeignKeyConstraint $key1, ForeignKeyConstraint $key2)
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
     * Returns the difference between the columns
     *
     * If there are differences this method returns the changed properties as a
     * string array, otherwise an empty array gets returned.
     *
     * @return string[]
     */
    public function diffColumn(Column $column1, Column $column2, AbstractPlatform $platform)
    {
        $properties1 = $column1->toArray();
        $properties2 = $column2->toArray();

        // Do not compare the column names
        unset($properties1['name'], $properties2['name']);

        $changedProperties = [];

        foreach (array_unique(array_merge(array_keys($properties1), array_keys($properties2))) as $key) {
            if (
                array_key_exists($key, $properties1)
                && array_key_exists($key, $properties2)
                && $properties1[$key] === $properties2[$key]
            ) {
                continue;
            }

            $changedProperties[] = $key;
        }

        return array_values(array_filter($changedProperties, function ($property) use ($column1, $column2, $platform): bool {
            return $this->changedPropertyHasEffect($property, $column1, $column2, $platform);
        }));
    }

    /**
     * Detects if a changed property has an actual effect on the used platform
     */
    private function changedPropertyHasEffect(
        string $property,
        Column $column1,
        Column $column2,
        AbstractPlatform $platform
    ): bool {
        $column2WithoutChange = clone $column2;

        if ($column1->hasCustomSchemaOption($property) || $column2->hasCustomSchemaOption($property)) {
            if (! $column1->hasCustomSchemaOption($property)) {
                $options = $column2WithoutChange->getCustomSchemaOptions();
                unset($options[$property]);
                $column2WithoutChange->setCustomSchemaOptions($options);
            } else {
                $column2WithoutChange->setCustomSchemaOption($property, $column1->getCustomSchemaOption($property));
            }
        } elseif ($column1->hasPlatformOption($property) || $column2->hasPlatformOption($property)) {
            if (! $column1->hasPlatformOption($property)) {
                $options = $column2WithoutChange->getPlatformOptions();
                unset($options[$property]);
                $column2WithoutChange->setPlatformOptions($options);
            } else {
                $column2WithoutChange->setPlatformOption($property, $column1->getPlatformOption($property));
            }
        } else {
            $column2WithoutChange->{'set' . $property}($column1->{'get' . $property}());
        }

        $sqlWithChange    = $platform->getCreateTableSQL(new Table('compare_table', [$column2]));
        $sqlWithoutChange = $platform->getCreateTableSQL(new Table('compare_table', [$column2WithoutChange]));

        return implode("\n", $sqlWithChange) !== implode("\n", $sqlWithoutChange);
    }

    /**
     * Finds the difference between the indexes $index1 and $index2.
     *
     * Compares $index1 with $index2 and returns $index2 if there are any
     * differences or false in case there are no differences.
     *
     * @return bool
     */
    public function diffIndex(Index $index1, Index $index2)
    {
        return ! ($index1->isFullfilledBy($index2) && $index2->isFullfilledBy($index1));
    }
}
