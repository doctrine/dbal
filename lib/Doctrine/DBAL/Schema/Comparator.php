<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Schema;

/**
 * Compare to Schemas and return an instance of SchemaDiff
 *
 * @copyright Copyright (C) 2005-2009 eZ Systems AS. All rights reserved.
 * @license http://ez.no/licenses/new_bsd New BSD License
 * 
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision$
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 */
class Comparator
{
    /**
     * @param Schema $fromSchema
     * @param Schema $toSchema
     * @return SchemaDiff
     */
    static public function compareSchemas( Schema $fromSchema, Schema $toSchema )
    {
        $c = new self();
        return $c->compare($fromSchema, $toSchema);
    }

    /**
     * Returns a SchemaDiff object containing the differences between the schemas $fromSchema and $toSchema.
     *
     * The returned diferences are returned in such a way that they contain the
     * operations to change the schema stored in $fromSchema to the schema that is
     * stored in $toSchema.
     *
     * @param Schema $fromSchema
     * @param Schema $toSchema
     *
     * @return SchemaDiff
     */
    public function compare(Schema $fromSchema, Schema $toSchema)
    {
        $diff = new SchemaDiff();

        $foreignKeysToTable = array();

        foreach ( $toSchema->getTables() as $table ) {
            $tableName = $table->getShortestName($toSchema->getName());
            if ( ! $fromSchema->hasTable($tableName)) {
                $diff->newTables[$tableName] = $toSchema->getTable($tableName);
            } else {
                $tableDifferences = $this->diffTable($fromSchema->getTable($tableName), $toSchema->getTable($tableName));
                if ($tableDifferences !== false) {
                    $diff->changedTables[$tableName] = $tableDifferences;
                }
            }
        }

        /* Check if there are tables removed */
        foreach ($fromSchema->getTables() as $table) {
            $tableName = $table->getShortestName($fromSchema->getName());

            $table = $fromSchema->getTable($tableName);
            if ( ! $toSchema->hasTable($tableName) ) {
                $diff->removedTables[$tableName] = $table;
            }

            // also remember all foreign keys that point to a specific table
            foreach ($table->getForeignKeys() as $foreignKey) {
                $foreignTable = strtolower($foreignKey->getForeignTableName());
                if (!isset($foreignKeysToTable[$foreignTable])) {
                    $foreignKeysToTable[$foreignTable] = array();
                }
                $foreignKeysToTable[$foreignTable][] = $foreignKey;
            }
        }

        foreach ($diff->removedTables as $tableName => $table) {
            if (isset($foreignKeysToTable[$tableName])) {
                $diff->orphanedForeignKeys = array_merge($diff->orphanedForeignKeys, $foreignKeysToTable[$tableName]);
            }
        }

        foreach ($toSchema->getSequences() as $sequence) {
            $sequenceName = $sequence->getShortestName($toSchema->getName());
            if ( ! $fromSchema->hasSequence($sequenceName)) {
                $diff->newSequences[] = $sequence;
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

            if ( ! $toSchema->hasSequence($sequenceName)) {
                $diff->removedSequences[] = $sequence;
            }
        }

        return $diff;
    }

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
     *
     * @param Sequence $sequence1
     * @param Sequence $sequence2
     */
    public function diffSequence(Sequence $sequence1, Sequence $sequence2)
    {
        if($sequence1->getAllocationSize() != $sequence2->getAllocationSize()) {
            return true;
        }

        if($sequence1->getInitialValue() != $sequence2->getInitialValue()) {
            return true;
        }

        return false;
    }

    /**
     * Returns the difference between the tables $table1 and $table2.
     *
     * If there are no differences this method returns the boolean false.
     *
     * @param Table $table1
     * @param Table $table2
     *
     * @return bool|TableDiff
     */
    public function diffTable(Table $table1, Table $table2)
    {
        $changes = 0;
        $tableDifferences = new TableDiff($table1->getName());

        $table1Columns = $table1->getColumns();
        $table2Columns = $table2->getColumns();

        /* See if all the fields in table 1 exist in table 2 */
        foreach ( $table2Columns as $columnName => $column ) {
            if ( !$table1->hasColumn($columnName) ) {
                $tableDifferences->addedColumns[$columnName] = $column;
                $changes++;
            }
        }
        /* See if there are any removed fields in table 2 */
        foreach ( $table1Columns as $columnName => $column ) {
            if ( !$table2->hasColumn($columnName) ) {
                $tableDifferences->removedColumns[$columnName] = $column;
                $changes++;
            }
        }

        foreach ( $table1Columns as $columnName => $column ) {
            if ( $table2->hasColumn($columnName) ) {
                $changedProperties = $this->diffColumn( $column, $table2->getColumn($columnName) );
                if (count($changedProperties) ) {
                    $columnDiff = new ColumnDiff($column->getName(), $table2->getColumn($columnName), $changedProperties);
                    $tableDifferences->changedColumns[$column->getName()] = $columnDiff;
                    $changes++;
                }
            }
        }

        $this->detectColumnRenamings($tableDifferences);

        $table1Indexes = $table1->getIndexes();
        $table2Indexes = $table2->getIndexes();

        foreach ($table2Indexes as $index2Name => $index2Definition) {
            foreach ($table1Indexes as $index1Name => $index1Definition) {
                if ($this->diffIndex($index1Definition, $index2Definition) === false) {
                    unset($table1Indexes[$index1Name]);
                    unset($table2Indexes[$index2Name]);
                } else {
                    if ($index1Name == $index2Name) {
                        $tableDifferences->changedIndexes[$index2Name] = $table2Indexes[$index2Name];
                        unset($table1Indexes[$index1Name]);
                        unset($table2Indexes[$index2Name]);
                        $changes++;
                    }
                }
            }
        }

        foreach ($table1Indexes as $index1Name => $index1Definition) {
            $tableDifferences->removedIndexes[$index1Name] = $index1Definition;
            $changes++;
        }

        foreach ($table2Indexes as $index2Name => $index2Definition) {
            $tableDifferences->addedIndexes[$index2Name] = $index2Definition;
            $changes++;
        }

        $fromFkeys = $table1->getForeignKeys();
        $toFkeys = $table2->getForeignKeys();

        foreach ($fromFkeys as $key1 => $constraint1) {
            foreach ($toFkeys as $key2 => $constraint2) {
                if($this->diffForeignKey($constraint1, $constraint2) === false) {
                    unset($fromFkeys[$key1]);
                    unset($toFkeys[$key2]);
                } else {
                    if (strtolower($constraint1->getName()) == strtolower($constraint2->getName())) {
                        $tableDifferences->changedForeignKeys[] = $constraint2;
                        $changes++;
                        unset($fromFkeys[$key1]);
                        unset($toFkeys[$key2]);
                    }
                }
            }
        }

        foreach ($fromFkeys as $key1 => $constraint1) {
            $tableDifferences->removedForeignKeys[] = $constraint1;
            $changes++;
        }

        foreach ($toFkeys as $key2 => $constraint2) {
            $tableDifferences->addedForeignKeys[] = $constraint2;
            $changes++;
        }

        return $changes ? $tableDifferences : false;
    }

    /**
     * Try to find columns that only changed their name, rename operations maybe cheaper than add/drop
     * however ambiguouties between different possibilites should not lead to renaming at all.
     *
     * @param TableDiff $tableDifferences
     */
    private function detectColumnRenamings(TableDiff $tableDifferences)
    {
        $renameCandidates = array();
        foreach ($tableDifferences->addedColumns as $addedColumnName => $addedColumn) {
            foreach ($tableDifferences->removedColumns as $removedColumnName => $removedColumn) {
                if (count($this->diffColumn($addedColumn, $removedColumn)) == 0) {
                    $renameCandidates[$addedColumn->getName()][] = array($removedColumn, $addedColumn, $addedColumnName);
                }
            }
        }

        foreach ($renameCandidates as $candidateColumns) {
            if (count($candidateColumns) == 1) {
                list($removedColumn, $addedColumn) = $candidateColumns[0];
                $removedColumnName = strtolower($removedColumn->getName());
                $addedColumnName = strtolower($addedColumn->getName());

                $tableDifferences->renamedColumns[$removedColumnName] = $addedColumn;
                unset($tableDifferences->addedColumns[$addedColumnName]);
                unset($tableDifferences->removedColumns[$removedColumnName]);
            }
        }
    }

    /**
     * @param ForeignKeyConstraint $key1
     * @param ForeignKeyConstraint $key2
     * @return bool
     */
    public function diffForeignKey(ForeignKeyConstraint $key1, ForeignKeyConstraint $key2)
    {
        if (array_map('strtolower', $key1->getLocalColumns()) != array_map('strtolower', $key2->getLocalColumns())) {
            return true;
        }

        if (array_map('strtolower', $key1->getForeignColumns()) != array_map('strtolower', $key2->getForeignColumns())) {
            return true;
        }

        if ($key1->onUpdate() != $key2->onUpdate()) {
            return true;
        }

        if ($key1->onDelete() != $key2->onDelete()) {
            return true;
        }

        return false;
    }

    /**
     * Returns the difference between the fields $field1 and $field2.
     *
     * If there are differences this method returns $field2, otherwise the
     * boolean false.
     *
     * @param Column $column1
     * @param Column $column2
     *
     * @return array
     */
    public function diffColumn(Column $column1, Column $column2)
    {
        $changedProperties = array();
        if ( $column1->getType() != $column2->getType() ) {
            $changedProperties[] = 'type';
        }

        if ($column1->getNotnull() != $column2->getNotnull()) {
            $changedProperties[] = 'notnull';
        }

        if ($column1->getDefault() != $column2->getDefault()) {
            $changedProperties[] = 'default';
        }

        if ($column1->getUnsigned() != $column2->getUnsigned()) {
            $changedProperties[] = 'unsigned';
        }

        if ($column1->getType() instanceof \Doctrine\DBAL\Types\StringType) {
            // check if value of length is set at all, default value assumed otherwise.
            $length1 = $column1->getLength() ?: 255;
            $length2 = $column2->getLength() ?: 255;
            if ($length1 != $length2) {
                $changedProperties[] = 'length';
            }

            if ($column1->getFixed() != $column2->getFixed()) {
                $changedProperties[] = 'fixed';
            }
        }

        if ($column1->getType() instanceof \Doctrine\DBAL\Types\DecimalType) {
            if (($column1->getPrecision()?:10) != ($column2->getPrecision()?:10)) {
                $changedProperties[] = 'precision';
            }
            if ($column1->getScale() != $column2->getScale()) {
                $changedProperties[] = 'scale';
            }
        }

        if ($column1->getAutoincrement() != $column2->getAutoincrement()) {
            $changedProperties[] = 'autoincrement';
        }

        // only allow to delete comment if its set to '' not to null.
        if ($column1->getComment() !== null && $column1->getComment() != $column2->getComment()) {
            $changedProperties[] = 'comment';
        }

        $options1 = $column1->getCustomSchemaOptions();
        $options2 = $column2->getCustomSchemaOptions();

        $commonKeys = array_keys(array_intersect_key($options1, $options2));

        foreach ($commonKeys as $key) {
            if ($options1[$key] !== $options2[$key]) {
                $changedProperties[] = $key;
            }
        }

        $diffKeys = array_keys(array_diff_key($options1, $options2) + array_diff_key($options2, $options1));

        $changedProperties = array_merge($changedProperties, $diffKeys);

        return $changedProperties;
    }

    /**
     * Finds the difference between the indexes $index1 and $index2.
     *
     * Compares $index1 with $index2 and returns $index2 if there are any
     * differences or false in case there are no differences.
     *
     * @param Index $index1
     * @param Index $index2
     * @return bool
     */
    public function diffIndex(Index $index1, Index $index2)
    {
        if ($index1->isFullfilledBy($index2) && $index2->isFullfilledBy($index1)) {
            return false;
        }
        return true;
    }
}
