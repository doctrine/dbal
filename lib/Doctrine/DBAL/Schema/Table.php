<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Visitor\Visitor;
use Doctrine\DBAL\Types\Type;
use function array_keys;
use function array_merge;
use function array_search;
use function array_unique;
use function in_array;
use function is_string;
use function preg_match;
use function strlen;
use function strtolower;
use function uksort;

/**
 * Object Representation of a table.
 */
class Table extends AbstractAsset
{
    /** @var Column[] */
    protected $_columns = [];

    /** @var Index[] */
    private $implicitIndexes = [];

    /** @var Index[] */
    protected $_indexes = [];

    /** @var string */
    protected $_primaryKeyName = false;

    /** @var UniqueConstraint[] */
    protected $_uniqueConstraints = [];

    /** @var ForeignKeyConstraint[] */
    protected $_fkConstraints = [];

    /** @var mixed[] */
    protected $_options = [];

    /** @var SchemaConfig|null */
    protected $_schemaConfig = null;

    /**
     * @param string                 $tableName
     * @param Column[]               $columns
     * @param Index[]                $indexes
     * @param UniqueConstraint[]     $uniqueConstraints
     * @param ForeignKeyConstraint[] $fkConstraints
     * @param mixed[]                $options
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
    ) {
        if (strlen($tableName) === 0) {
            throw DBALException::invalidTableName($tableName);
        }

        $this->_setName($tableName);

        foreach ($columns as $column) {
            $this->_addColumn($column);
        }

        foreach ($indexes as $idx) {
            $this->_addIndex($idx);
        }

        foreach ($uniqueConstraints as $uniqueConstraint) {
            $this->_addUniqueConstraint($uniqueConstraint);
        }

        foreach ($fkConstraints as $fkConstraint) {
            $this->_addForeignKeyConstraint($fkConstraint);
        }

        $this->_options = $options;
    }

    /**
     * @return void
     */
    public function setSchemaConfig(SchemaConfig $schemaConfig)
    {
        $this->_schemaConfig = $schemaConfig;
    }

    /**
     * Sets the Primary Key.
     *
     * @param string[]     $columnNames
     * @param string|false $indexName
     *
     * @return self
     */
    public function setPrimaryKey(array $columnNames, $indexName = false)
    {
        $this->_addIndex($this->_createIndex($columnNames, $indexName ?: 'primary', true, true));

        foreach ($columnNames as $columnName) {
            $column = $this->getColumn($columnName);
            $column->setNotnull(true);
        }

        return $this;
    }

    /**
     * @param mixed[]     $columnNames
     * @param string|null $indexName
     * @param string[]    $flags
     * @param mixed[]     $options
     *
     * @return self
     */
    public function addUniqueConstraint(array $columnNames, $indexName = null, array $flags = [], array $options = [])
    {
        if ($indexName === null) {
            $indexName = $this->_generateIdentifierName(
                array_merge([$this->getName()], $columnNames),
                'uniq',
                $this->_getMaxIdentifierLength()
            );
        }

        return $this->_addUniqueConstraint($this->_createUniqueConstraint($columnNames, $indexName, $flags, $options));
    }

    /**
     * @param string[]    $columnNames
     * @param string|null $indexName
     * @param string[]    $flags
     * @param mixed[]     $options
     *
     * @return self
     */
    public function addIndex(array $columnNames, $indexName = null, array $flags = [], array $options = [])
    {
        if ($indexName === null) {
            $indexName = $this->_generateIdentifierName(
                array_merge([$this->getName()], $columnNames),
                'idx',
                $this->_getMaxIdentifierLength()
            );
        }

        return $this->_addIndex($this->_createIndex($columnNames, $indexName, false, false, $flags, $options));
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
     * @throws SchemaException If the index does not exist.
     */
    public function dropIndex($indexName)
    {
        $indexName = $this->normalizeIdentifier($indexName);

        if (! $this->hasIndex($indexName)) {
            throw SchemaException::indexDoesNotExist($indexName, $this->_name);
        }

        unset($this->_indexes[$indexName]);
    }

    /**
     * @param string[]    $columnNames
     * @param string|null $indexName
     * @param mixed[]     $options
     *
     * @return self
     */
    public function addUniqueIndex(array $columnNames, $indexName = null, array $options = [])
    {
        if ($indexName === null) {
            $indexName = $this->_generateIdentifierName(
                array_merge([$this->getName()], $columnNames),
                'uniq',
                $this->_getMaxIdentifierLength()
            );
        }

        return $this->_addIndex($this->_createIndex($columnNames, $indexName, true, false, [], $options));
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
     * @throws SchemaException If no index exists for the given current name
     *                         or if an index with the given new name already exists on this table.
     */
    public function renameIndex($oldIndexName, $newIndexName = null)
    {
        $oldIndexName           = $this->normalizeIdentifier($oldIndexName);
        $normalizedNewIndexName = $this->normalizeIdentifier($newIndexName);

        if ($oldIndexName === $normalizedNewIndexName) {
            return $this;
        }

        if (! $this->hasIndex($oldIndexName)) {
            throw SchemaException::indexDoesNotExist($oldIndexName, $this->_name);
        }

        if ($this->hasIndex($normalizedNewIndexName)) {
            throw SchemaException::indexAlreadyExists($normalizedNewIndexName, $this->_name);
        }

        $oldIndex = $this->_indexes[$oldIndexName];

        if ($oldIndex->isPrimary()) {
            $this->dropPrimaryKey();

            return $this->setPrimaryKey($oldIndex->getColumns(), $newIndexName ?? false);
        }

        unset($this->_indexes[$oldIndexName]);

        if ($oldIndex->isUnique()) {
            return $this->addUniqueIndex($oldIndex->getColumns(), $newIndexName, $oldIndex->getOptions());
        }

        return $this->addIndex($oldIndex->getColumns(), $newIndexName, $oldIndex->getFlags(), $oldIndex->getOptions());
    }

    /**
     * Checks if an index begins in the order of the given columns.
     *
     * @param string[] $columnNames
     *
     * @return bool
     */
    public function columnsAreIndexed(array $columnNames)
    {
        foreach ($this->getIndexes() as $index) {
            /** @var $index Index */
            if ($index->spansColumns($columnNames)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string  $columnName
     * @param string  $typeName
     * @param mixed[] $options
     *
     * @return Column
     */
    public function addColumn($columnName, $typeName, array $options = [])
    {
        $column = new Column($columnName, Type::getType($typeName), $options);

        $this->_addColumn($column);

        return $column;
    }

    /**
     * Renames a Column.
     *
     * @deprecated
     *
     * @param string $oldColumnName
     * @param string $newColumnName
     *
     * @throws DBALException
     */
    public function renameColumn($oldColumnName, $newColumnName)
    {
        throw new DBALException(
            'Table#renameColumn() was removed, because it drops and recreates the column instead. ' .
            'There is no fix available, because a schema diff cannot reliably detect if a column ' .
            'was renamed or one column was created and another one dropped.'
        );
    }

    /**
     * Change Column Details.
     *
     * @param string  $columnName
     * @param mixed[] $options
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

        unset($this->_columns[$columnName]);

        return $this;
    }

    /**
     * Adds a foreign key constraint.
     *
     * Name is inferred from the local columns.
     *
     * @param Table|string $foreignTable       Table schema instance or table name
     * @param string[]     $localColumnNames
     * @param string[]     $foreignColumnNames
     * @param mixed[]      $options
     * @param string|null  $constraintName
     *
     * @return self
     */
    public function addForeignKeyConstraint($foreignTable, array $localColumnNames, array $foreignColumnNames, array $options = [], $constraintName = null)
    {
        if (! $constraintName) {
            $constraintName = $this->_generateIdentifierName(
                array_merge((array) $this->getName(), $localColumnNames),
                'fk',
                $this->_getMaxIdentifierLength()
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
     * @param Table|string $foreignTable       Table schema instance or table name
     * @param string[]     $localColumnNames
     * @param string[]     $foreignColumnNames
     * @param mixed[]      $options
     *
     * @return self
     */
    public function addUnnamedForeignKeyConstraint($foreignTable, array $localColumnNames, array $foreignColumnNames, array $options = [])
    {
        return $this->addForeignKeyConstraint($foreignTable, $localColumnNames, $foreignColumnNames, $options);
    }

    /**
     * Adds a foreign key constraint with a given name.
     *
     * @deprecated Use {@link addForeignKeyConstraint}
     *
     * @param string       $name
     * @param Table|string $foreignTable       Table schema instance or table name
     * @param string[]     $localColumnNames
     * @param string[]     $foreignColumnNames
     * @param mixed[]      $options
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function addNamedForeignKeyConstraint($name, $foreignTable, array $localColumnNames, array $foreignColumnNames, array $options = [])
    {
        if ($foreignTable instanceof Table) {
            foreach ($foreignColumnNames as $columnName) {
                if (! $foreignTable->hasColumn($columnName)) {
                    throw SchemaException::columnDoesNotExist($columnName, $foreignTable->getName());
                }
            }
        }

        foreach ($localColumnNames as $columnName) {
            if (! $this->hasColumn($columnName)) {
                throw SchemaException::columnDoesNotExist($columnName, $this->_name);
            }
        }

        $constraint = new ForeignKeyConstraint(
            $localColumnNames,
            $foreignTable,
            $foreignColumnNames,
            $name,
            $options
        );

        return $this->_addForeignKeyConstraint($constraint);
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return self
     */
    public function addOption($name, $value)
    {
        $this->_options[$name] = $value;

        return $this;
    }

    /**
     * Returns whether this table has a foreign key constraint with the given name.
     *
     * @param string $constraintName
     *
     * @return bool
     */
    public function hasForeignKey($constraintName)
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        return isset($this->_fkConstraints[$constraintName]);
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

        if (! $this->hasForeignKey($constraintName)) {
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
     * @throws SchemaException
     */
    public function removeForeignKey($constraintName)
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        if (! $this->hasForeignKey($constraintName)) {
            throw SchemaException::foreignKeyDoesNotExist($constraintName, $this->_name);
        }

        unset($this->_fkConstraints[$constraintName]);
    }

    /**
     * Returns whether this table has a unique constraint with the given name.
     *
     * @param string $constraintName
     *
     * @return bool
     */
    public function hasUniqueConstraint($constraintName)
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        return isset($this->_uniqueConstraints[$constraintName]);
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

        if (! $this->hasUniqueConstraint($constraintName)) {
            throw SchemaException::uniqueConstraintDoesNotExist($constraintName, $this->_name);
        }

        return $this->_uniqueConstraints[$constraintName];
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

        if (! $this->hasUniqueConstraint($constraintName)) {
            throw SchemaException::uniqueConstraintDoesNotExist($constraintName, $this->_name);
        }

        unset($this->_uniqueConstraints[$constraintName]);
    }

    /**
     * Returns ordered list of columns (primary keys are first, then foreign keys, then the rest)
     *
     * @return Column[]
     */
    public function getColumns()
    {
        $columns = $this->_columns;
        $pkCols  = [];
        $fkCols  = [];

        $primaryKey = $this->getPrimaryKey();

        if ($primaryKey !== null) {
            $pkCols = $primaryKey->getColumns();
        }

        foreach ($this->getForeignKeys() as $fk) {
            /** @var ForeignKeyConstraint $fk */
            $fkCols = array_merge($fkCols, $fk->getColumns());
        }

        $colNames = array_unique(array_merge($pkCols, $fkCols, array_keys($columns)));

        uksort($columns, static function ($a, $b) use ($colNames) {
            return array_search($a, $colNames) >= array_search($b, $colNames);
        });

        return $columns;
    }

    /**
     * Returns whether this table has a Column with the given name.
     *
     * @param string $columnName The column name.
     *
     * @return bool
     */
    public function hasColumn($columnName)
    {
        $columnName = $this->normalizeIdentifier($columnName);

        return isset($this->_columns[$columnName]);
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

        if (! $this->hasColumn($columnName)) {
            throw SchemaException::columnDoesNotExist($columnName, $this->_name);
        }

        return $this->_columns[$columnName];
    }

    /**
     * Returns the primary key.
     *
     * @return Index|null The primary key, or null if this Table has no primary key.
     */
    public function getPrimaryKey()
    {
        return $this->hasPrimaryKey()
            ? $this->getIndex($this->_primaryKeyName)
            : null;
    }

    /**
     * Returns the primary key columns.
     *
     * @return string[]
     *
     * @throws DBALException
     */
    public function getPrimaryKeyColumns()
    {
        $primaryKey = $this->getPrimaryKey();

        if ($primaryKey === null) {
            throw new DBALException('Table ' . $this->getName() . ' has no primary key.');
        }

        return $primaryKey->getColumns();
    }

    /**
     * Returns whether this table has a primary key.
     *
     * @return bool
     */
    public function hasPrimaryKey()
    {
        return $this->_primaryKeyName && $this->hasIndex($this->_primaryKeyName);
    }

    /**
     * Returns whether this table has an Index with the given name.
     *
     * @param string $indexName The index name.
     *
     * @return bool
     */
    public function hasIndex($indexName)
    {
        $indexName = $this->normalizeIdentifier($indexName);

        return isset($this->_indexes[$indexName]);
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

        if (! $this->hasIndex($indexName)) {
            throw SchemaException::indexDoesNotExist($indexName, $this->_name);
        }

        return $this->_indexes[$indexName];
    }

    /**
     * @return Index[]
     */
    public function getIndexes()
    {
        return $this->_indexes;
    }

    /**
     * Returns the unique constraints.
     *
     * @return UniqueConstraint[]
     */
    public function getUniqueConstraints()
    {
        return $this->_uniqueConstraints;
    }

    /**
     * Returns the foreign key constraints.
     *
     * @return ForeignKeyConstraint[]
     */
    public function getForeignKeys()
    {
        return $this->_fkConstraints;
    }

    /**
     * @param string $name
     *
     * @return bool
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
     * @return mixed[]
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
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

    /**
     * @return int
     */
    protected function _getMaxIdentifierLength()
    {
        return $this->_schemaConfig instanceof SchemaConfig
            ? $this->_schemaConfig->getMaxIdentifierLength()
            : 63;
    }

    /**
     * @return void
     *
     * @throws SchemaException
     */
    protected function _addColumn(Column $column)
    {
        $columnName = $column->getName();
        $columnName = $this->normalizeIdentifier($columnName);

        if (isset($this->_columns[$columnName])) {
            throw SchemaException::columnAlreadyExists($this->getName(), $columnName);
        }

        $this->_columns[$columnName] = $column;
    }

    /**
     * Adds an index to the table.
     *
     * @return self
     *
     * @throws SchemaException
     */
    protected function _addIndex(Index $indexCandidate)
    {
        $indexName               = $indexCandidate->getName();
        $indexName               = $this->normalizeIdentifier($indexName);
        $replacedImplicitIndexes = [];

        foreach ($this->implicitIndexes as $name => $implicitIndex) {
            if (! $implicitIndex->isFullfilledBy($indexCandidate) || ! isset($this->_indexes[$name])) {
                continue;
            }

            $replacedImplicitIndexes[] = $name;
        }

        if ((isset($this->_indexes[$indexName]) && ! in_array($indexName, $replacedImplicitIndexes, true)) ||
            ($this->_primaryKeyName !== false && $indexCandidate->isPrimary())
        ) {
            throw SchemaException::indexAlreadyExists($indexName, $this->_name);
        }

        foreach ($replacedImplicitIndexes as $name) {
            unset($this->_indexes[$name], $this->implicitIndexes[$name]);
        }

        if ($indexCandidate->isPrimary()) {
            $this->_primaryKeyName = $indexName;
        }

        $this->_indexes[$indexName] = $indexCandidate;

        return $this;
    }

    /**
     * @return self
     */
    protected function _addUniqueConstraint(UniqueConstraint $constraint)
    {
        $name = strlen($uniqueConstraint->getName())
            ? $uniqueConstraint->getName()
            : $this->_generateIdentifierName(
                array_merge((array) $this->getName(), $uniqueConstraint->getLocalColumns()),
                'fk',
                $this->_getMaxIdentifierLength()
            );

        $name = $this->normalizeIdentifier($name);

        $this->_uniqueConstraints[$name] = $constraint;

        // If there is already an index that fulfills this requirements drop the request. In the case of __construct
        // calling this method during hydration from schema-details all the explicitly added indexes lead to duplicates.
        // This creates computation overhead in this case, however no duplicate indexes are ever added (column based).
        $indexName = $this->_generateIdentifierName(
            array_merge([$this->getName()], $uniqueConstraint->getColumns()),
            'idx',
            $this->_getMaxIdentifierLength()
        );

        $indexCandidate = $this->_createIndex($uniqueConstraint->getColumns(), $indexName, true, false);

        foreach ($this->_indexes as $existingIndex) {
            if ($indexCandidate->isFullfilledBy($existingIndex)) {
                return $this;
            }
        }

        $this->implicitIndexes[$this->normalizeIdentifier($indexName)] = $indexCandidate;

        return $this;
    }

    /**
     * @return self
     */
    protected function _addForeignKeyConstraint(ForeignKeyConstraint $constraint)
    {
        $constraint->setLocalTable($this);

        $name = strlen($constraint->getName())
            ? $constraint->getName()
            : $this->_generateIdentifierName(
                array_merge((array) $this->getName(), $constraint->getLocalColumns()),
                'fk',
                $this->_getMaxIdentifierLength()
            );

        $name = $this->normalizeIdentifier($name);

        $this->_fkConstraints[$name] = $constraint;

        // add an explicit index on the foreign key columns.
        // If there is already an index that fulfills this requirements drop the request. In the case of __construct
        // calling this method during hydration from schema-details all the explicitly added indexes lead to duplicates.
        // This creates computation overhead in this case, however no duplicate indexes are ever added (column based).
        $indexName = $this->_generateIdentifierName(
            array_merge([$this->getName()], $constraint->getColumns()),
            'idx',
            $this->_getMaxIdentifierLength()
        );

        $indexCandidate = $this->_createIndex($constraint->getColumns(), $indexName, false, false);

        foreach ($this->_indexes as $existingIndex) {
            if ($indexCandidate->isFullfilledBy($existingIndex)) {
                return $this;
            }
        }

        $this->_addIndex($indexCandidate);
        $this->implicitIndexes[$this->normalizeIdentifier($indexName)] = $indexCandidate;

        return $this;
    }

    /**
     * Normalizes a given identifier.
     *
     * Trims quotes and lowercases the given identifier.
     *
     * @param string|null $identifier The identifier to normalize.
     *
     * @return string The normalized identifier.
     */
    private function normalizeIdentifier($identifier)
    {
        if ($identifier === null) {
            return '';
        }

        return $this->trimQuotes(strtolower($identifier));
    }

    /**
     * @param mixed[] $columns
     * @param string  $indexName
     * @param mixed[] $flags
     * @param mixed[] $options
     *
     * @return UniqueConstraint
     *
     * @throws SchemaException
     */
    private function _createUniqueConstraint(array $columns, $indexName, array $flags = [], array $options = [])
    {
        if (preg_match('(([^a-zA-Z0-9_]+))', $this->normalizeIdentifier($indexName))) {
            throw SchemaException::indexNameInvalid($indexName);
        }

        foreach ($columns as $index => $value) {
            if (is_string($index)) {
                $columnName = $index;
            } else {
                $columnName = $value;
            }

            if (! $this->hasColumn($columnName)) {
                throw SchemaException::columnDoesNotExist($columnName, $this->_name);
            }
        }

        return new UniqueConstraint($indexName, $columns, $flags, $options);
    }

    /**
     * @param mixed[]  $columns
     * @param string   $indexName
     * @param bool     $isUnique
     * @param bool     $isPrimary
     * @param string[] $flags
     * @param mixed[]  $options
     *
     * @return Index
     *
     * @throws SchemaException
     */
    private function _createIndex(array $columns, $indexName, $isUnique, $isPrimary, array $flags = [], array $options = [])
    {
        if (preg_match('(([^a-zA-Z0-9_]+))', $this->normalizeIdentifier($indexName))) {
            throw SchemaException::indexNameInvalid($indexName);
        }

        foreach ($columns as $index => $value) {
            if (is_string($index)) {
                $columnName = $index;
            } else {
                $columnName = $value;
            }

            if (! $this->hasColumn($columnName)) {
                throw SchemaException::columnDoesNotExist($columnName, $this->_name);
            }
        }

        return new Index($indexName, $columns, $isUnique, $isPrimary, $flags, $options);
    }
}
