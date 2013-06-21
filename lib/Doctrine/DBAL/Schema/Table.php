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

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Schema\Visitor\Visitor;
use Doctrine\DBAL\DBALException;

/**
 * Object Representation of a table.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class Table extends AbstractAsset
{
    /**
     * @var string
     */
    protected $_name = null;

    /**
     * @var \Doctrine\DBAL\Schema\Column[]
     */
    protected $_columns = array();

    /**
     * @var \Doctrine\DBAL\Schema\Index[]
     */
    protected $_indexes = array();

    /**
     * @var string
     */
    protected $_primaryKeyName = false;

    /**
     * @var \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    protected $_fkConstraints = array();

    /**
     * @var array
     */
    protected $_options = array();

    /**
     * @var \Doctrine\DBAL\Schema\SchemaConfig
     */
    protected $_schemaConfig = null;

    /**
     * @param string  $tableName
     * @param array   $columns
     * @param array   $indexes
     * @param array   $fkConstraints
     * @param integer $idGeneratorType
     * @param array   $options
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function __construct($tableName, array $columns=array(), array $indexes=array(), array $fkConstraints=array(), $idGeneratorType = 0, array $options=array())
    {
        if (strlen($tableName) == 0) {
            throw DBALException::invalidTableName($tableName);
        }

        $this->_setName($tableName);
        $this->_idGeneratorType = $idGeneratorType;

        foreach ($columns as $column) {
            $this->_addColumn($column);
        }

        foreach ($indexes as $idx) {
            $this->_addIndex($idx);
        }

        foreach ($fkConstraints as $constraint) {
            $this->_addForeignKeyConstraint($constraint);
        }

        $this->_options = $options;
    }

    /**
     * @param \Doctrine\DBAL\Schema\SchemaConfig $schemaConfig
     *
     * @return void
     */
    public function setSchemaConfig(SchemaConfig $schemaConfig)
    {
        $this->_schemaConfig = $schemaConfig;
    }

    /**
     * @return integer
     */
    protected function _getMaxIdentifierLength()
    {
        if ($this->_schemaConfig instanceof SchemaConfig) {
            return $this->_schemaConfig->getMaxIdentifierLength();
        } else {
            return 63;
        }
    }

    /**
     * Sets the Primary Key.
     *
     * @param array          $columns
     * @param string|boolean $indexName
     *
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function setPrimaryKey(array $columns, $indexName = false)
    {
        $primaryKey = $this->_createIndex($columns, $indexName ?: "primary", true, true);

        foreach ($columns as $columnName) {
            $column = $this->getColumn($columnName);
            $column->setNotnull(true);
        }

        return $primaryKey;
    }

    /**
     * @param array       $columnNames
     * @param string|null $indexName
     * @param array       $flags
     *
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function addIndex(array $columnNames, $indexName = null, array $flags = array())
    {
        if($indexName == null) {
            $indexName = $this->_generateIdentifierName(
                array_merge(array($this->getName()), $columnNames), "idx", $this->_getMaxIdentifierLength()
            );
        }

        return $this->_createIndex($columnNames, $indexName, false, false, $flags);
    }

    /**
     * Drops the primary key from this table.
     *
     * @return void
     */
    public function dropPrimaryKey()
    {
        $this->dropIndex($this->_primaryKeyName);
        $this->_primaryKeyName = false;
    }

    /**
     * Drops an index from this table.
     *
     * @param string $indexName The index name.
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException If the index does not exist.
     */
    public function dropIndex($indexName)
    {
        $indexName = strtolower($indexName);
        if ( ! $this->hasIndex($indexName)) {
            throw SchemaException::indexDoesNotExist($indexName, $this->_name);
        }
        unset($this->_indexes[$indexName]);
    }

    /**
     * @param array       $columnNames
     * @param string|null $indexName
     *
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function addUniqueIndex(array $columnNames, $indexName = null)
    {
        if ($indexName === null) {
            $indexName = $this->_generateIdentifierName(
                array_merge(array($this->getName()), $columnNames), "uniq", $this->_getMaxIdentifierLength()
            );
        }

        return $this->_createIndex($columnNames, $indexName, true, false);
    }

    /**
     * Checks if an index begins in the order of the given columns.
     *
     * @param array $columnsNames
     *
     * @return boolean
     */
    public function columnsAreIndexed(array $columnsNames)
    {
        foreach ($this->getIndexes() as $index) {
            /* @var $index Index */
            if ($index->spansColumns($columnsNames)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array   $columnNames
     * @param string  $indexName
     * @param boolean $isUnique
     * @param boolean $isPrimary
     * @param array   $flags
     *
     * @return \Doctrine\DBAL\Schema\Table
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    private function _createIndex(array $columnNames, $indexName, $isUnique, $isPrimary, array $flags = array())
    {
        if (preg_match('(([^a-zA-Z0-9_]+))', $indexName)) {
            throw SchemaException::indexNameInvalid($indexName);
        }

        foreach ($columnNames as $columnName => $indexColOptions) {
            if (is_numeric($columnName) && is_string($indexColOptions)) {
                $columnName = $indexColOptions;
            }

            if ( ! $this->hasColumn($columnName)) {
                throw SchemaException::columnDoesNotExist($columnName, $this->_name);
            }
        }

        $this->_addIndex(new Index($indexName, $columnNames, $isUnique, $isPrimary, $flags));

        return $this;
    }

    /**
     * @param string $columnName
     * @param string $typeName
     * @param array  $options
     *
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function addColumn($columnName, $typeName, array $options=array())
    {
        $column = new Column($columnName, Type::getType($typeName), $options);

        $this->_addColumn($column);

        return $column;
    }

    /**
     * Renames a Column.
     *
     * @param string $oldColumnName
     * @param string $newColumnName
     *
     * @return \Doctrine\DBAL\Schema\Table
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function renameColumn($oldColumnName, $newColumnName)
    {
        throw new DBALException("Table#renameColumn() was removed, because it drops and recreates " .
            "the column instead. There is no fix available, because a schema diff cannot reliably detect if a " .
            "column was renamed or one column was created and another one dropped.");
    }

    /**
     * Change Column Details.
     *
     * @param string $columnName
     * @param array  $options
     *
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function changeColumn($columnName, array $options)
    {
        $column = $this->getColumn($columnName);
        $column->setOptions($options);

        return $this;
    }

    /**
     * Drops a Column from the Table.
     *
     * @param string $columnName
     *
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function dropColumn($columnName)
    {
        $columnName = strtolower($columnName);
        unset($this->_columns[$columnName]);

        return $this;
    }

    /**
     * Adds a foreign key constraint.
     *
     * Name is inferred from the local columns.
     *
     * @param \Doctrine\DBAL\Schema\Table $foreignTable
     * @param array                       $localColumnNames
     * @param array                       $foreignColumnNames
     * @param array                       $options
     * @param string|null                 $constraintName
     *
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function addForeignKeyConstraint($foreignTable, array $localColumnNames, array $foreignColumnNames, array $options=array(), $constraintName = null)
    {
        $constraintName = $constraintName ?: $this->_generateIdentifierName(array_merge((array)$this->getName(), $localColumnNames), "fk", $this->_getMaxIdentifierLength());

        return $this->addNamedForeignKeyConstraint($constraintName, $foreignTable, $localColumnNames, $foreignColumnNames, $options);
    }

    /**
     * Adds a foreign key constraint.
     *
     * Name is to be generated by the database itself.
     *
     * @deprecated Use {@link addForeignKeyConstraint}
     *
     * @param \Doctrine\DBAL\Schema\Table $foreignTable
     * @param array                       $localColumnNames
     * @param array                       $foreignColumnNames
     * @param array                       $options
     *
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function addUnnamedForeignKeyConstraint($foreignTable, array $localColumnNames, array $foreignColumnNames, array $options=array())
    {
        return $this->addForeignKeyConstraint($foreignTable, $localColumnNames, $foreignColumnNames, $options);
    }

    /**
     * Adds a foreign key constraint with a given name.
     *
     * @deprecated Use {@link addForeignKeyConstraint}
     *
     * @param string                      $name
     * @param \Doctrine\DBAL\Schema\Table $foreignTable
     * @param array                       $localColumnNames
     * @param array                       $foreignColumnNames
     * @param array                       $options
     *
     * @return \Doctrine\DBAL\Schema\Table
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function addNamedForeignKeyConstraint($name, $foreignTable, array $localColumnNames, array $foreignColumnNames, array $options=array())
    {
        if ($foreignTable instanceof Table) {
            foreach ($foreignColumnNames as $columnName) {
                if ( ! $foreignTable->hasColumn($columnName)) {
                    throw SchemaException::columnDoesNotExist($columnName, $foreignTable->getName());
                }
            }
        }

        foreach ($localColumnNames as $columnName) {
            if ( ! $this->hasColumn($columnName)) {
                throw SchemaException::columnDoesNotExist($columnName, $this->_name);
            }
        }

        $constraint = new ForeignKeyConstraint(
            $localColumnNames, $foreignTable, $foreignColumnNames, $name, $options
        );
        $this->_addForeignKeyConstraint($constraint);

        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function addOption($name, $value)
    {
        $this->_options[$name] = $value;

        return $this;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Column $column
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function _addColumn(Column $column)
    {
        $columnName = $column->getName();
        $columnName = strtolower($columnName);

        if (isset($this->_columns[$columnName])) {
            throw SchemaException::columnAlreadyExists($this->getName(), $columnName);
        }

        $this->_columns[$columnName] = $column;
    }

    /**
     * Adds an index to the table.
     *
     * @param \Doctrine\DBAL\Schema\Index $indexCandidate
     *
     * @return \Doctrine\DBAL\Schema\Table
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function _addIndex(Index $indexCandidate)
    {
        // check for duplicates
        foreach ($this->_indexes as $existingIndex) {
            if ($indexCandidate->isFullfilledBy($existingIndex)) {
                return $this;
            }
        }

        $indexName = $indexCandidate->getName();
        $indexName = strtolower($indexName);

        if (isset($this->_indexes[$indexName]) || ($this->_primaryKeyName != false && $indexCandidate->isPrimary())) {
            throw SchemaException::indexAlreadyExists($indexName, $this->_name);
        }

        // remove overruled indexes
        foreach ($this->_indexes as $idxKey => $existingIndex) {
            if ($indexCandidate->overrules($existingIndex)) {
                unset($this->_indexes[$idxKey]);
            }
        }

        if ($indexCandidate->isPrimary()) {
            $this->_primaryKeyName = $indexName;
        }

        $this->_indexes[$indexName] = $indexCandidate;

        return $this;
    }

    /**
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint $constraint
     *
     * @return void
     */
    protected function _addForeignKeyConstraint(ForeignKeyConstraint $constraint)
    {
        $constraint->setLocalTable($this);

        if(strlen($constraint->getName())) {
            $name = $constraint->getName();
        } else {
            $name = $this->_generateIdentifierName(
                array_merge((array)$this->getName(), $constraint->getLocalColumns()), "fk", $this->_getMaxIdentifierLength()
            );
        }
        $name = strtolower($name);

        $this->_fkConstraints[$name] = $constraint;
        // add an explicit index on the foreign key columns. If there is already an index that fulfils this requirements drop the request.
        // In the case of __construct calling this method during hydration from schema-details all the explicitly added indexes
        // lead to duplicates. This creates computation overhead in this case, however no duplicate indexes are ever added (based on columns).
        $this->addIndex($constraint->getColumns());
    }

    /**
     * Returns whether this table has a foreign key constraint with the given name.
     *
     * @param string $constraintName
     *
     * @return boolean
     */
    public function hasForeignKey($constraintName)
    {
        $constraintName = strtolower($constraintName);

        return isset($this->_fkConstraints[$constraintName]);
    }

    /**
     * Returns the foreign key constraint with the given name.
     *
     * @param string $constraintName The constraint name.
     *
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException If the foreign key does not exist.
     */
    public function getForeignKey($constraintName)
    {
        $constraintName = strtolower($constraintName);
        if(!$this->hasForeignKey($constraintName)) {
            throw SchemaException::foreignKeyDoesNotExist($constraintName, $this->_name);
        }

        return $this->_fkConstraints[$constraintName];
    }

    /**
     * Removes the foreign key constraint with the given name.
     *
     * @param string $constraintName The constraint name.
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function removeForeignKey($constraintName)
    {
        $constraintName = strtolower($constraintName);
        if(!$this->hasForeignKey($constraintName)) {
            throw SchemaException::foreignKeyDoesNotExist($constraintName, $this->_name);
        }

        unset($this->_fkConstraints[$constraintName]);
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column[]
     */
    public function getColumns()
    {
        $columns = $this->_columns;

        $pkCols = array();
        $fkCols = array();

        if ($this->hasPrimaryKey()) {
            $pkCols = $this->getPrimaryKey()->getColumns();
        }
        foreach ($this->getForeignKeys() as $fk) {
            /* @var $fk ForeignKeyConstraint */
            $fkCols = array_merge($fkCols, $fk->getColumns());
        }
        $colNames = array_unique(array_merge($pkCols, $fkCols, array_keys($columns)));

        uksort($columns, function($a, $b) use($colNames) {
            return (array_search($a, $colNames) >= array_search($b, $colNames));
        });

        return $columns;
    }

    /**
     * Returns whether this table has a Column with the given name.
     *
     * @param string $columnName The column name.
     *
     * @return boolean
     */
    public function hasColumn($columnName)
    {
        $columnName = $this->trimQuotes(strtolower($columnName));

        return isset($this->_columns[$columnName]);
    }

    /**
     * Returns the Column with the given name.
     *
     * @param string $columnName The column name.
     *
     * @return \Doctrine\DBAL\Schema\Column
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException If the column does not exist.
     */
    public function getColumn($columnName)
    {
        $columnName = strtolower($this->trimQuotes($columnName));
        if ( ! $this->hasColumn($columnName)) {
            throw SchemaException::columnDoesNotExist($columnName, $this->_name);
        }

        return $this->_columns[$columnName];
    }

    /**
     * Returns the primary key.
     *
     * @return \Doctrine\DBAL\Schema\Index|null The primary key, or null if this Table has no primary key.
     */
    public function getPrimaryKey()
    {
        if ( ! $this->hasPrimaryKey()) {
            return null;
        }

        return $this->getIndex($this->_primaryKeyName);
    }

    /**
     * Returns the primary key columns.
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getPrimaryKeyColumns()
    {
        if ( ! $this->hasPrimaryKey()) {
            throw new DBALException("Table " . $this->getName() . " has no primary key.");
        }

        return $this->getPrimaryKey()->getColumns();
    }

    /**
     * Returns whether this table has a primary key.
     *
     * @return boolean
     */
    public function hasPrimaryKey()
    {
        return ($this->_primaryKeyName && $this->hasIndex($this->_primaryKeyName));
    }

    /**
     * Returns whether this table has an Index with the given name.
     *
     * @param string $indexName The index name.
     *
     * @return boolean
     */
    public function hasIndex($indexName)
    {
        $indexName = strtolower($indexName);

        return (isset($this->_indexes[$indexName]));
    }

    /**
     * Returns the Index with the given name.
     *
     * @param string $indexName The index name.
     *
     * @return \Doctrine\DBAL\Schema\Index
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException If the index does not exist.
     */
    public function getIndex($indexName)
    {
        $indexName = strtolower($indexName);
        if ( ! $this->hasIndex($indexName)) {
            throw SchemaException::indexDoesNotExist($indexName, $this->_name);
        }

        return $this->_indexes[$indexName];
    }

    /**
     * @return \Doctrine\DBAL\Schema\Index[]
     */
    public function getIndexes()
    {
        return $this->_indexes;
    }

    /**
     * Returns the foreign key constraints.
     *
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    public function getForeignKeys()
    {
        return $this->_fkConstraints;
    }

    /**
     * @param string $name
     *
     * @return boolean
     */
    public function hasOption($name)
    {
        return isset($this->_options[$name]);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getOption($name)
    {
        return $this->_options[$name];
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Visitor\Visitor $visitor
     *
     * @return void
     */
    public function visit(Visitor $visitor)
    {
        $visitor->acceptTable($this);

        foreach ($this->getColumns() as $column) {
            $visitor->acceptColumn($this, $column);
        }

        foreach ($this->getIndexes() as $index) {
            $visitor->acceptIndex($this, $index);
        }

        foreach ($this->getForeignKeys() as $constraint) {
            $visitor->acceptForeignKey($this, $constraint);
        }
    }

    /**
     * Clone of a Table triggers a deep clone of all affected assets.
     *
     * @return void
     */
    public function __clone()
    {
        foreach ($this->_columns as $k => $column) {
            $this->_columns[$k] = clone $column;
        }
        foreach ($this->_indexes as $k => $index) {
            $this->_indexes[$k] = clone $index;
        }
        foreach ($this->_fkConstraints as $k => $fk) {
            $this->_fkConstraints[$k] = clone $fk;
            $this->_fkConstraints[$k]->setLocalTable($this);
        }
    }
}
