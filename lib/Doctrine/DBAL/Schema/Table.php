<?php

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
    protected $name = null;

    /**
     * @var Column[]
     */
    protected $columns = [];

    /**
     * @var Index[]
     */
    private $implicitIndexes = [];

    /**
     * @var Index[]
     */
    protected $indexes = [];

    /**
     * @var string
     */
    protected $primaryKeyName = false;

    /**
     * @var UniqueConstraint[]
     */
    protected $uniqueConstraints = [];

    /**
     * @var ForeignKeyConstraint[]
     */
    protected $fkConstraints = [];

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var SchemaConfig
     */
    protected $schemaConfig = null;

    /**
     * @param string                 $tableName
     * @param Column[]               $columns
     * @param Index[]                $indexes
     * @param UniqueConstraint[]     $uniqueConstraints
     * @param ForeignKeyConstraint[] $fkConstraints
     * @param array                  $options
     *
     * @throws DBALException
     */
    public function __construct(
        $tableName,
        array $columns = [],
        array $indexes = [],
        array $uniqueConstraints = [],
        array $fkConstraints = [],
        array $options = []
    )
    {
        if (strlen($tableName) == 0) {
            throw DBALException::invalidTableName($tableName);
        }

        $this->setName($tableName);

        foreach ($columns as $column) {
            $this->addColumnCandidate($column);
        }

        foreach ($indexes as $idx) {
            $this->addIndexCandidate($idx);
        }

        foreach ($uniqueConstraints as $uniqueConstraint) {
            $this->addUniqueConstraintCandidate($uniqueConstraint);
        }

        foreach ($fkConstraints as $fkConstraint) {
            $this->addForeignKeyConstraintCandidate($fkConstraint);
        }

        $this->options = $options;
    }

    /**
     * @param SchemaConfig $schemaConfig
     *
     * @return void
     */
    public function setSchemaConfig(SchemaConfig $schemaConfig)
    {
        $this->schemaConfig = $schemaConfig;
    }

    /**
     * Sets the Primary Key.
     *
     * @param array          $columns
     * @param string|boolean $indexName
     *
     * @return self
     */
    public function setPrimaryKey(array $columns, $indexName = false)
    {
        $this->addIndexCandidate($this->createIndex($columns, $indexName ?: "primary", true, true));

        foreach ($columns as $columnName) {
            $column = $this->getColumn($columnName);
            $column->setNotnull(true);
        }

        return $this;
    }

    /**
     * @param array       $columnNames
     * @param string|null $indexName
     * @param array       $flags
     * @param array       $options
     *
     * @return self
     */
    public function addUniqueConstraint(array $columnNames, $indexName = null, array $flags = array(), array $options = [])
    {
        if ($indexName == null) {
            $indexName = $this->generateIdentifierName(
                array_merge(array($this->getName()), $columnNames), "uniq", $this->getMaxIdentifierLength()
            );
        }

        return $this->addUniqueConstraintCandidate($this->createUniqueConstraint($columnNames, $indexName, $flags, $options));
    }

    /**
     * @param array       $columnNames
     * @param string|null $indexName
     * @param array       $flags
     * @param array       $options
     *
     * @return self
     */
    public function addIndex(array $columnNames, $indexName = null, array $flags = [], array $options = [])
    {
        if ($indexName == null) {
            $indexName = $this->generateIdentifierName(
                array_merge([$this->getName()], $columnNames), "idx", $this->getMaxIdentifierLength()
            );
        }

        return $this->addIndexCandidate($this->createIndex($columnNames, $indexName, false, false, $flags, $options));
    }

    /**
     * Drops the primary key from this table.
     *
     * @return void
     */
    public function dropPrimaryKey()
    {
        $this->dropIndex($this->primaryKeyName);
        $this->primaryKeyName = false;
    }

    /**
     * Drops an index from this table.
     *
     * @param string $indexName The index name.
     *
     * @return void
     *
     * @throws SchemaException If the index does not exist.
     */
    public function dropIndex($indexName)
    {
        $indexName = $this->normalizeIdentifier($indexName);

        if ( ! $this->hasIndex($indexName)) {
            throw SchemaException::indexDoesNotExist($indexName, $this->name);
        }

        unset($this->indexes[$indexName]);
    }

    /**
     * @param array       $columnNames
     * @param string|null $indexName
     * @param array       $options
     *
     * @return self
     */
    public function addUniqueIndex(array $columnNames, $indexName = null, array $options = [])
    {
        if ($indexName === null) {
            $indexName = $this->generateIdentifierName(
                array_merge([$this->getName()], $columnNames), "uniq", $this->getMaxIdentifierLength()
            );
        }

        return $this->addIndexCandidate($this->createIndex($columnNames, $indexName, true, false, [], $options));
    }

    /**
     * Renames an index.
     *
     * @param string      $oldIndexName The name of the index to rename from.
     * @param string|null $newIndexName The name of the index to rename to.
     *                                  If null is given, the index name will be auto-generated.
     *
     * @return self This table instance.
     *
     * @throws SchemaException if no index exists for the given current name
     *                         or if an index with the given new name already exists on this table.
     */
    public function renameIndex($oldIndexName, $newIndexName = null)
    {
        $oldIndexName           = $this->normalizeIdentifier($oldIndexName);
        $normalizedNewIndexName = $this->normalizeIdentifier($newIndexName);

        if ($oldIndexName === $normalizedNewIndexName) {
            return $this;
        }

        if ( ! $this->hasIndex($oldIndexName)) {
            throw SchemaException::indexDoesNotExist($oldIndexName, $this->name);
        }

        if ($this->hasIndex($normalizedNewIndexName)) {
            throw SchemaException::indexAlreadyExists($normalizedNewIndexName, $this->name);
        }

        $oldIndex = $this->indexes[$oldIndexName];

        if ($oldIndex->isPrimary()) {
            $this->dropPrimaryKey();

            return $this->setPrimaryKey($oldIndex->getColumns(), $newIndexName);
        }

        unset($this->indexes[$oldIndexName]);

        if ($oldIndex->isUnique()) {
            return $this->addUniqueIndex($oldIndex->getColumns(), $newIndexName, $oldIndex->getOptions());
        }

        return $this->addIndex($oldIndex->getColumns(), $newIndexName, $oldIndex->getFlags(), $oldIndex->getOptions());
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
     * @param string $columnName
     * @param string $typeName
     * @param array  $options
     *
     * @return Column
     */
    public function addColumn($columnName, $typeName, array $options=[])
    {
        $column = new Column($columnName, Type::getType($typeName), $options);

        $this->addColumnCandidate($column);

        return $column;
    }

    /**
     * Renames a Column.
     *
     * @param string $oldColumnName
     * @param string $newColumnName
     *
     * @deprecated
     *
     * @throws DBALException
     */
    public function renameColumn($oldColumnName, $newColumnName)
    {
        throw new DBALException(
            "Table#renameColumn() was removed, because it drops and recreates the column instead. " .
            "There is no fix available, because a schema diff cannot reliably detect if a column " .
            "was renamed or one column was created and another one dropped."
        );
    }

    /**
     * Change Column Details.
     *
     * @param string $columnName
     * @param array  $options
     *
     * @return self
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
     * @return self
     */
    public function dropColumn($columnName)
    {
        $columnName = $this->normalizeIdentifier($columnName);

        unset($this->columns[$columnName]);

        return $this;
    }

    /**
     * Adds a foreign key constraint.
     *
     * Name is inferred from the local columns.
     *
     * @param Table|string $foreignTable Table schema instance or table name
     * @param array        $localColumnNames
     * @param array        $foreignColumnNames
     * @param array        $options
     * @param string|null  $constraintName
     *
     * @return self
     */
    public function addForeignKeyConstraint($foreignTable, array $localColumnNames, array $foreignColumnNames, array $options=[], $constraintName = null)
    {
        if ( ! $constraintName) {
            $constraintName = $this->generateIdentifierName(
                array_merge((array) $this->getName(), $localColumnNames), "fk", $this->getMaxIdentifierLength()
            );
        }

        return $this->addNamedForeignKeyConstraint($constraintName, $foreignTable, $localColumnNames, $foreignColumnNames, $options);
    }

    /**
     * Adds a foreign key constraint.
     *
     * Name is to be generated by the database itself.
     *
     * @deprecated Use {@link addForeignKeyConstraint}
     *
     * @param Table|string $foreignTable Table schema instance or table name
     * @param array        $localColumnNames
     * @param array        $foreignColumnNames
     * @param array        $options
     *
     * @return self
     */
    public function addUnnamedForeignKeyConstraint($foreignTable, array $localColumnNames, array $foreignColumnNames, array $options=[])
    {
        return $this->addForeignKeyConstraint($foreignTable, $localColumnNames, $foreignColumnNames, $options);
    }

    /**
     * Adds a foreign key constraint with a given name.
     *
     * @deprecated Use {@link addForeignKeyConstraint}
     *
     * @param string       $name
     * @param Table|string $foreignTable Table schema instance or table name
     * @param array        $localColumnNames
     * @param array        $foreignColumnNames
     * @param array        $options
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function addNamedForeignKeyConstraint($name, $foreignTable, array $localColumnNames, array $foreignColumnNames, array $options=[])
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
                throw SchemaException::columnDoesNotExist($columnName, $this->name);
            }
        }

        $constraint = new ForeignKeyConstraint(
            $localColumnNames, $foreignTable, $foreignColumnNames, $name, $options
        );

        return $this->addForeignKeyConstraintCandidate($constraint);
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return self
     */
    public function addOption($name, $value)
    {
        $this->options[$name] = $value;

        return $this;
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
        $constraintName = $this->normalizeIdentifier($constraintName);

        return isset($this->fkConstraints[$constraintName]);
    }

    /**
     * Returns the foreign key constraint with the given name.
     *
     * @param string $constraintName The constraint name.
     *
     * @return ForeignKeyConstraint
     *
     * @throws SchemaException If the foreign key does not exist.
     */
    public function getForeignKey($constraintName)
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        if ( ! $this->hasForeignKey($constraintName)) {
            throw SchemaException::foreignKeyDoesNotExist($constraintName, $this->name);
        }

        return $this->fkConstraints[$constraintName];
    }

    /**
     * Removes the foreign key constraint with the given name.
     *
     * @param string $constraintName The constraint name.
     *
     * @return void
     *
     * @throws SchemaException
     */
    public function removeForeignKey($constraintName)
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        if ( ! $this->hasForeignKey($constraintName)) {
            throw SchemaException::foreignKeyDoesNotExist($constraintName, $this->name);
        }

        unset($this->fkConstraints[$constraintName]);
    }

    /**
     * Returns whether this table has a unique constraint with the given name.
     *
     * @param string $constraintName
     *
     * @return boolean
     */
    public function hasUniqueConstraint($constraintName)
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        return isset($this->uniqueConstraints[$constraintName]);
    }

    /**
     * Returns the unique constraint with the given name.
     *
     * @param string $constraintName The constraint name.
     *
     * @return UniqueConstraint
     *
     * @throws SchemaException If the foreign key does not exist.
     */
    public function getUniqueConstraint($constraintName)
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        if ( ! $this->hasUniqueConstraint($constraintName)) {
            throw SchemaException::uniqueConstraintDoesNotExist($constraintName, $this->name);
        }

        return $this->uniqueConstraints[$constraintName];
    }

    /**
     * Removes the unique constraint with the given name.
     *
     * @param string $constraintName The constraint name.
     *
     * @return void
     *
     * @throws SchemaException
     */
    public function removeUniqueConstraint($constraintName)
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        if ( ! $this->hasUniqueConstraint($constraintName)) {
            throw SchemaException::uniqueConstraintDoesNotExist($constraintName, $this->name);
        }

        unset($this->uniqueConstraints[$constraintName]);
    }

    /**
     * Returns ordered list of columns (primary keys are first, then foreign keys, then the rest)
     * @return Column[]
     */
    public function getColumns()
    {
        $columns = $this->columns;
        $pkCols  = [];
        $fkCols  = [];

        if ($this->hasPrimaryKey()) {
            $pkCols = $this->getPrimaryKey()->getColumns();
        }

        foreach ($this->getForeignKeys() as $fk) {
            /* @var $fk ForeignKeyConstraint */
            $fkCols = array_merge($fkCols, $fk->getColumns());
        }

        $colNames = array_unique(array_merge($pkCols, $fkCols, array_keys($columns)));

        uksort($columns, function ($a, $b) use ($colNames) {
            return (array_search($a, $colNames) >= array_search($b, $colNames));
        });

        return $columns;
    }

    /**
     * Returns foreign key columns
     * @return Column[]
     */
    private function getForeignKeyColumns()
    {
        $foreignKeyColumns = [];

        foreach ($this->getForeignKeys() as $foreignKey) {
            /* @var $foreignKey ForeignKeyConstraint */
            $foreignKeyColumns = array_merge($foreignKeyColumns, $foreignKey->getColumns());
        }

        return $this->filterColumns($foreignKeyColumns);
    }

    /**
     * Returns only columns that have specified names
     * @param array $columnNames
     * @return Column[]
     */
    private function filterColumns(array $columnNames)
    {
        return array_filter($this->columns, function ($columnName) use ($columnNames) {
            return in_array($columnName, $columnNames, true);
        }, ARRAY_FILTER_USE_KEY);
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
        $columnName = $this->normalizeIdentifier($columnName);

        return isset($this->columns[$columnName]);
    }

    /**
     * Returns the Column with the given name.
     *
     * @param string $columnName The column name.
     *
     * @return Column
     *
     * @throws SchemaException If the column does not exist.
     */
    public function getColumn($columnName)
    {
        $columnName = $this->normalizeIdentifier($columnName);

        if ( ! $this->hasColumn($columnName)) {
            throw SchemaException::columnDoesNotExist($columnName, $this->name);
        }

        return $this->columns[$columnName];
    }

    /**
     * Returns the primary key.
     *
     * @return Index|null The primary key, or null if this Table has no primary key.
     */
    public function getPrimaryKey()
    {
        return $this->hasPrimaryKey()
            ? $this->getIndex($this->primaryKeyName)
            : null
        ;
    }

    /**
     * Returns the primary key columns.
     *
     * @return array
     *
     * @throws DBALException
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
        return ($this->primaryKeyName && $this->hasIndex($this->primaryKeyName));
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
        $indexName = $this->normalizeIdentifier($indexName);

        return (isset($this->indexes[$indexName]));
    }

    /**
     * Returns the Index with the given name.
     *
     * @param string $indexName The index name.
     *
     * @return Index
     *
     * @throws SchemaException If the index does not exist.
     */
    public function getIndex($indexName)
    {
        $indexName = $this->normalizeIdentifier($indexName);

        if ( ! $this->hasIndex($indexName)) {
            throw SchemaException::indexDoesNotExist($indexName, $this->name);
        }

        return $this->indexes[$indexName];
    }

    /**
     * @return Index[]
     */
    public function getIndexes()
    {
        return $this->indexes;
    }

    /**
     * Returns the unique constraints.
     *
     * @return UniqueConstraint[]
     */
    public function getUniqueConstraints()
    {
        return $this->uniqueConstraints;
    }

    /**
     * Returns the foreign key constraints.
     *
     * @return ForeignKeyConstraint[]
     */
    public function getForeignKeys()
    {
        return $this->fkConstraints;
    }

    /**
     * @param string $name
     *
     * @return boolean
     */
    public function hasOption($name)
    {
        return isset($this->options[$name]);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getOption($name)
    {
        return $this->options[$name];
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param Visitor $visitor
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
        foreach ($this->columns as $k => $column) {
            $this->columns[$k] = clone $column;
        }

        foreach ($this->indexes as $k => $index) {
            $this->indexes[$k] = clone $index;
        }

        foreach ($this->fkConstraints as $k => $fk) {
            $this->fkConstraints[$k] = clone $fk;
            $this->fkConstraints[$k]->setLocalTable($this);
        }
    }

    /**
     * @return integer
     */
    protected function getMaxIdentifierLength()
    {
        return ($this->schemaConfig instanceof SchemaConfig)
            ? $this->schemaConfig->getMaxIdentifierLength()
            : 63
        ;
    }

    /**
     * @param Column $column
     *
     * @return void
     *
     * @throws SchemaException
     */
    protected function addColumnCandidate(Column $column)
    {
        $columnName = $column->getName();
        $columnName = $this->normalizeIdentifier($columnName);

        if (isset($this->columns[$columnName])) {
            throw SchemaException::columnAlreadyExists($this->getName(), $columnName);
        }

        $this->columns[$columnName] = $column;
    }

    /**
     * Adds an index to the table.
     *
     * @param Index $indexCandidate
     *
     * @return self
     *
     * @throws SchemaException
     */
    protected function addIndexCandidate(Index $indexCandidate)
    {
        $indexName = $indexCandidate->getName();
        $indexName = $this->normalizeIdentifier($indexName);
        $replacedImplicitIndexes = [];

        foreach ($this->implicitIndexes as $name => $implicitIndex) {
            if ($implicitIndex->isFullfilledBy($indexCandidate) && isset($this->indexes[$name])) {
                $replacedImplicitIndexes[] = $name;
            }
        }

        if ((isset($this->indexes[$indexName]) && ! in_array($indexName, $replacedImplicitIndexes, true)) ||
            ($this->primaryKeyName != false && $indexCandidate->isPrimary())
        ) {
            throw SchemaException::indexAlreadyExists($indexName, $this->name);
        }

        foreach ($replacedImplicitIndexes as $name) {
            unset($this->indexes[$name], $this->implicitIndexes[$name]);
        }

        if ($indexCandidate->isPrimary()) {
            $this->primaryKeyName = $indexName;
        }

        $this->indexes[$indexName] = $indexCandidate;

        return $this;
    }

    /**
     * @param UniqueConstraint $constraint
     *
     * @return self
     */
    protected function addUniqueConstraintCandidate(UniqueConstraint $constraint)
    {
        $name = strlen($constraint->getName())
            ? $constraint->getName()
            : $this->generateIdentifierName(
                array_merge((array) $this->getName(), $constraint->getLocalColumns()), "fk", $this->getMaxIdentifierLength()
            )
        ;

        $name = $this->normalizeIdentifier($name);

        $this->uniqueConstraints[$name] = $constraint;

        // If there is already an index that fulfills this requirements drop the request. In the case of __construct
        // calling this method during hydration from schema-details all the explicitly added indexes lead to duplicates.
        // This creates computation overhead in this case, however no duplicate indexes are ever added (column based).
        $indexName = $this->generateIdentifierName(
            array_merge(array($this->getName()), $constraint->getColumns()), "idx", $this->getMaxIdentifierLength()
        );

        $indexCandidate = $this->createIndex($constraint->getColumns(), $indexName, true, false);

        foreach ($this->indexes as $existingIndex) {
            if ($indexCandidate->isFullfilledBy($existingIndex)) {
                return $this;
            }
        }

        $this->implicitIndexes[$this->normalizeIdentifier($indexName)] = $indexCandidate;

        return $this;
    }

    /**
     * @param ForeignKeyConstraint $constraint
     *
     * @return self
     */
    protected function addForeignKeyConstraintCandidate(ForeignKeyConstraint $constraint)
    {
        $constraint->setLocalTable($this);

        $name = strlen($constraint->getName())
            ? $constraint->getName()
            : $this->generateIdentifierName(
                array_merge((array) $this->getName(), $constraint->getLocalColumns()), "fk", $this->getMaxIdentifierLength()
            )
        ;

        $name = $this->normalizeIdentifier($name);

        $this->fkConstraints[$name] = $constraint;

        // add an explicit index on the foreign key columns.
        // If there is already an index that fulfills this requirements drop the request. In the case of __construct
        // calling this method during hydration from schema-details all the explicitly added indexes lead to duplicates.
        // This creates computation overhead in this case, however no duplicate indexes are ever added (column based).
        $indexName = $this->generateIdentifierName(
            array_merge(array($this->getName()), $constraint->getColumns()), "idx", $this->getMaxIdentifierLength()
        );

        $indexCandidate = $this->createIndex($constraint->getColumns(), $indexName, false, false);

        foreach ($this->indexes as $existingIndex) {
            if ($indexCandidate->isFullfilledBy($existingIndex)) {
                return $this;
            }
        }

        $this->addIndexCandidate($indexCandidate);
        $this->implicitIndexes[$this->normalizeIdentifier($indexName)] = $indexCandidate;

        return $this;
    }

    /**
     * Normalizes a given identifier.
     *
     * Trims quotes and lowercases the given identifier.
     *
     * @param string $identifier The identifier to normalize.
     *
     * @return string The normalized identifier.
     */
    private function normalizeIdentifier($identifier)
    {
        return $this->trimQuotes(strtolower($identifier));
    }

    /**
     * @param array  $columnNames
     * @param string $indexName
     * @param array  $flags
     * @param array  $options
     *
     * @return UniqueConstraint
     *
     * @throws SchemaException
     */
    private function createUniqueConstraint(array $columnNames, $indexName, array $flags = array(), array $options = [])
    {
        if (preg_match('(([^a-zA-Z0-9_]+))', $this->normalizeIdentifier($indexName))) {
            throw SchemaException::indexNameInvalid($indexName);
        }

        foreach ($columnNames as $columnName => $indexColOptions) {
            if (is_numeric($columnName) && is_string($indexColOptions)) {
                $columnName = $indexColOptions;
            }

            if ( ! $this->hasColumn($columnName)) {
                throw SchemaException::columnDoesNotExist($columnName, $this->name);
            }
        }

        return new UniqueConstraint($indexName, $columnNames, $flags, $options);
    }

    /**
     * @param array   $columnNames
     * @param string  $indexName
     * @param boolean $isUnique
     * @param boolean $isPrimary
     * @param array   $flags
     * @param array   $options
     *
     * @return Index
     *
     * @throws SchemaException
     */
    private function createIndex(array $columnNames, $indexName, $isUnique, $isPrimary, array $flags = [], array $options = [])
    {
        if (preg_match('(([^a-zA-Z0-9_]+))', $this->normalizeIdentifier($indexName))) {
            throw SchemaException::indexNameInvalid($indexName);
        }

        foreach ($columnNames as $columnName => $indexColOptions) {
            if (is_numeric($columnName) && is_string($indexColOptions)) {
                $columnName = $indexColOptions;
            }

            if ( ! $this->hasColumn($columnName)) {
                throw SchemaException::columnDoesNotExist($columnName, $this->name);
            }
        }

        return new Index($indexName, $columnNames, $isUnique, $isPrimary, $flags, $options);
    }
}
