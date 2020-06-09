<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Exception\ColumnAlreadyExists;
use Doctrine\DBAL\Schema\Exception\ColumnDoesNotExist;
use Doctrine\DBAL\Schema\Exception\ForeignKeyDoesNotExist;
use Doctrine\DBAL\Schema\Exception\IndexAlreadyExists;
use Doctrine\DBAL\Schema\Exception\IndexDoesNotExist;
use Doctrine\DBAL\Schema\Exception\IndexNameInvalid;
use Doctrine\DBAL\Schema\Exception\InvalidTableName;
use Doctrine\DBAL\Schema\Exception\UniqueConstraintDoesNotExist;
use Doctrine\DBAL\Schema\Visitor\Visitor;
use Doctrine\DBAL\Types\Type;

use function array_keys;
use function array_merge;
use function array_search;
use function array_unique;
use function in_array;
use function is_string;
use function preg_match;
use function sprintf;
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

    /** @var string|null */
    protected $_primaryKeyName;

    /** @var UniqueConstraint[] */
    protected $_uniqueConstraints = [];

    /** @var ForeignKeyConstraint[] */
    protected $_fkConstraints = [];

    /** @var mixed[] */
    protected $_options = [
        'create_options' => [],
    ];

    /** @var SchemaConfig|null */
    protected $_schemaConfig = null;

    /**
     * @param array<Column>               $columns
     * @param array<Index>                $indexes
     * @param array<UniqueConstraint>     $uniqueConstraints
     * @param array<ForeignKeyConstraint> $fkConstraints
     * @param array<string, mixed>        $options
     *
     * @throws DBALException
     */
    public function __construct(
        string $tableName,
        array $columns = [],
        array $indexes = [],
        array $uniqueConstraints = [],
        array $fkConstraints = [],
        array $options = []
    ) {
        if (strlen($tableName) === 0) {
            throw InvalidTableName::new($tableName);
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

        $this->_options = array_merge($this->_options, $options);
    }

    public function setSchemaConfig(SchemaConfig $schemaConfig): void
    {
        $this->_schemaConfig = $schemaConfig;
    }

    /**
     * Sets the Primary Key.
     *
     * @param array<int, string> $columnNames
     */
    public function setPrimaryKey(array $columnNames, ?string $indexName = null): self
    {
        if ($indexName === null) {
            $indexName = 'primary';
        }

        $this->_addIndex($this->_createIndex($columnNames, $indexName, true, true));

        foreach ($columnNames as $columnName) {
            $column = $this->getColumn($columnName);
            $column->setNotnull(true);
        }

        return $this;
    }

    /**
     * @param array<int, string>   $columnNames
     * @param array<int, string>   $flags
     * @param array<string, mixed> $options
     */
    public function addUniqueConstraint(array $columnNames, ?string $indexName = null, array $flags = [], array $options = []): self
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
     * @param array<int, string>   $columnNames
     * @param array<int, string>   $flags
     * @param array<string, mixed> $options
     */
    public function addIndex(array $columnNames, ?string $indexName = null, array $flags = [], array $options = []): self
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
     */
    public function dropPrimaryKey(): void
    {
        if ($this->_primaryKeyName === null) {
            return;
        }

        $this->dropIndex($this->_primaryKeyName);
        $this->_primaryKeyName = null;
    }

    /**
     * Drops an index from this table.
     *
     * @throws SchemaException If the index does not exist.
     */
    public function dropIndex(string $indexName): void
    {
        $indexName = $this->normalizeIdentifier($indexName);

        if (! $this->hasIndex($indexName)) {
            throw IndexDoesNotExist::new($indexName, $this->_name);
        }

        unset($this->_indexes[$indexName]);
    }

    /**
     * @param array<int, string>   $columnNames
     * @param array<string, mixed> $options
     */
    public function addUniqueIndex(array $columnNames, ?string $indexName = null, array $options = []): self
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
     * @throws SchemaException If no index exists for the given current name
     *                         or if an index with the given new name already exists on this table.
     */
    public function renameIndex(string $oldIndexName, ?string $newIndexName = null): self
    {
        $oldIndexName           = $this->normalizeIdentifier($oldIndexName);
        $normalizedNewIndexName = $this->normalizeIdentifier($newIndexName);

        if ($oldIndexName === $normalizedNewIndexName) {
            return $this;
        }

        if (! $this->hasIndex($oldIndexName)) {
            throw IndexDoesNotExist::new($oldIndexName, $this->_name);
        }

        if ($this->hasIndex($normalizedNewIndexName)) {
            throw IndexAlreadyExists::new($normalizedNewIndexName, $this->_name);
        }

        $oldIndex = $this->_indexes[$oldIndexName];

        if ($oldIndex->isPrimary()) {
            $this->dropPrimaryKey();

            return $this->setPrimaryKey($oldIndex->getColumns(), $newIndexName ?? null);
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
     * @param array<int, string> $columnNames
     */
    public function columnsAreIndexed(array $columnNames): bool
    {
        foreach ($this->getIndexes() as $index) {
            if ($index->spansColumns($columnNames)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function addColumn(string $columnName, string $typeName, array $options = []): Column
    {
        $column = new Column($columnName, Type::getType($typeName), $options);

        $this->_addColumn($column);

        return $column;
    }

    /**
     * Change Column Details.
     *
     * @param array<string, mixed> $options
     */
    public function changeColumn(string $columnName, array $options): self
    {
        $column = $this->getColumn($columnName);

        $column->setOptions($options);

        return $this;
    }

    /**
     * Drops a Column from the Table.
     */
    public function dropColumn(string $columnName): self
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
     * @param Table|string         $foreignTable       Table schema instance or table name
     * @param array<int, string>   $localColumnNames
     * @param array<int, string>   $foreignColumnNames
     * @param array<string, mixed> $options
     */
    public function addForeignKeyConstraint($foreignTable, array $localColumnNames, array $foreignColumnNames, array $options = [], ?string $name = null): self
    {
        if ($name === null) {
            $name = $this->_generateIdentifierName(
                array_merge((array) $this->getName(), $localColumnNames),
                'fk',
                $this->_getMaxIdentifierLength()
            );
        }

        if ($foreignTable instanceof Table) {
            foreach ($foreignColumnNames as $columnName) {
                if (! $foreignTable->hasColumn($columnName)) {
                    throw ColumnDoesNotExist::new($columnName, $foreignTable->getName());
                }
            }
        }

        foreach ($localColumnNames as $columnName) {
            if (! $this->hasColumn($columnName)) {
                throw ColumnDoesNotExist::new($columnName, $this->_name);
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
     * @param mixed $value
     */
    public function addOption(string $name, $value): self
    {
        $this->_options[$name] = $value;

        return $this;
    }

    /**
     * Returns whether this table has a foreign key constraint with the given name.
     */
    public function hasForeignKey(string $constraintName): bool
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        return isset($this->_fkConstraints[$constraintName]);
    }

    /**
     * Returns the foreign key constraint with the given name.
     *
     * @throws SchemaException If the foreign key does not exist.
     */
    public function getForeignKey(string $constraintName): ForeignKeyConstraint
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        if (! $this->hasForeignKey($constraintName)) {
            throw ForeignKeyDoesNotExist::new($constraintName, $this->_name);
        }

        return $this->_fkConstraints[$constraintName];
    }

    /**
     * Removes the foreign key constraint with the given name.
     *
     * @throws SchemaException
     */
    public function removeForeignKey(string $constraintName): void
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        if (! $this->hasForeignKey($constraintName)) {
            throw ForeignKeyDoesNotExist::new($constraintName, $this->_name);
        }

        unset($this->_fkConstraints[$constraintName]);
    }

    /**
     * Returns whether this table has a unique constraint with the given name.
     */
    public function hasUniqueConstraint(string $constraintName): bool
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        return isset($this->_uniqueConstraints[$constraintName]);
    }

    /**
     * Returns the unique constraint with the given name.
     *
     * @throws SchemaException If the foreign key does not exist.
     */
    public function getUniqueConstraint(string $constraintName): UniqueConstraint
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        if (! $this->hasUniqueConstraint($constraintName)) {
            throw UniqueConstraintDoesNotExist::new($constraintName, $this->_name);
        }

        return $this->_uniqueConstraints[$constraintName];
    }

    /**
     * Removes the unique constraint with the given name.
     *
     * @throws SchemaException
     */
    public function removeUniqueConstraint(string $constraintName): void
    {
        $constraintName = $this->normalizeIdentifier($constraintName);

        if (! $this->hasUniqueConstraint($constraintName)) {
            throw UniqueConstraintDoesNotExist::new($constraintName, $this->_name);
        }

        unset($this->_uniqueConstraints[$constraintName]);
    }

    /**
     * Returns ordered list of columns (primary keys are first, then foreign keys, then the rest)
     *
     * @return array<string, Column>
     */
    public function getColumns(): array
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

        uksort($columns, static function ($a, $b) use ($colNames): int {
            return array_search($a, $colNames, true) <=> array_search($b, $colNames, true);
        });

        return $columns;
    }

    /**
     * Returns whether this table has a Column with the given name.
     */
    public function hasColumn(string $columnName): bool
    {
        $columnName = $this->normalizeIdentifier($columnName);

        return isset($this->_columns[$columnName]);
    }

    /**
     * Returns the Column with the given name.
     *
     * @throws SchemaException If the column does not exist.
     */
    public function getColumn(string $columnName): Column
    {
        $columnName = $this->normalizeIdentifier($columnName);

        if (! $this->hasColumn($columnName)) {
            throw ColumnDoesNotExist::new($columnName, $this->_name);
        }

        return $this->_columns[$columnName];
    }

    /**
     * Returns the primary key.
     */
    public function getPrimaryKey(): ?Index
    {
        if ($this->_primaryKeyName !== null) {
            return $this->getIndex($this->_primaryKeyName);
        }

        return null;
    }

    /**
     * Returns the primary key columns.
     *
     * @return array<int, string>
     *
     * @throws DBALException
     */
    public function getPrimaryKeyColumns(): array
    {
        $primaryKey = $this->getPrimaryKey();

        if ($primaryKey === null) {
            throw new DBALException(sprintf('Table "%s" has no primary key.', $this->getName()));
        }

        return $primaryKey->getColumns();
    }

    /**
     * Returns whether this table has a primary key.
     */
    public function hasPrimaryKey(): bool
    {
        return $this->_primaryKeyName !== null && $this->hasIndex($this->_primaryKeyName);
    }

    /**
     * Returns whether this table has an Index with the given name.
     */
    public function hasIndex(string $indexName): bool
    {
        $indexName = $this->normalizeIdentifier($indexName);

        return isset($this->_indexes[$indexName]);
    }

    /**
     * Returns the Index with the given name.
     *
     * @throws SchemaException If the index does not exist.
     */
    public function getIndex(string $indexName): Index
    {
        $indexName = $this->normalizeIdentifier($indexName);

        if (! $this->hasIndex($indexName)) {
            throw IndexDoesNotExist::new($indexName, $this->_name);
        }

        return $this->_indexes[$indexName];
    }

    /**
     * @return array<string, Index>
     */
    public function getIndexes(): array
    {
        return $this->_indexes;
    }

    /**
     * Returns the unique constraints.
     *
     * @return array<string, UniqueConstraint>
     */
    public function getUniqueConstraints(): array
    {
        return $this->_uniqueConstraints;
    }

    /**
     * Returns the foreign key constraints.
     *
     * @return array<string, ForeignKeyConstraint>
     */
    public function getForeignKeys(): array
    {
        return $this->_fkConstraints;
    }

    public function hasOption(string $name): bool
    {
        return isset($this->_options[$name]);
    }

    /**
     * @return mixed
     */
    public function getOption(string $name)
    {
        return $this->_options[$name];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->_options;
    }

    public function visit(Visitor $visitor): void
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

    protected function _getMaxIdentifierLength(): int
    {
        return $this->_schemaConfig instanceof SchemaConfig
            ? $this->_schemaConfig->getMaxIdentifierLength()
            : 63;
    }

    /**
     * @throws SchemaException
     */
    protected function _addColumn(Column $column): void
    {
        $columnName = $column->getName();
        $columnName = $this->normalizeIdentifier($columnName);

        if (isset($this->_columns[$columnName])) {
            throw ColumnAlreadyExists::new($this->getName(), $columnName);
        }

        $this->_columns[$columnName] = $column;
    }

    /**
     * Adds an index to the table.
     *
     * @throws SchemaException
     */
    protected function _addIndex(Index $indexCandidate): self
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

        if (
            (isset($this->_indexes[$indexName]) && ! in_array($indexName, $replacedImplicitIndexes, true)) ||
            ($this->_primaryKeyName !== null && $indexCandidate->isPrimary())
        ) {
            throw IndexAlreadyExists::new($indexName, $this->_name);
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

    protected function _addUniqueConstraint(UniqueConstraint $constraint): self
    {
        $name = $constraint->getName() !== ''
            ? $constraint->getName()
            : $this->_generateIdentifierName(
                array_merge((array) $this->getName(), $constraint->getColumns()),
                'fk',
                $this->_getMaxIdentifierLength()
            );

        $name = $this->normalizeIdentifier($name);

        $this->_uniqueConstraints[$name] = $constraint;

        // If there is already an index that fulfills this requirements drop the request. In the case of __construct
        // calling this method during hydration from schema-details all the explicitly added indexes lead to duplicates.
        // This creates computation overhead in this case, however no duplicate indexes are ever added (column based).
        $indexName = $this->_generateIdentifierName(
            array_merge([$this->getName()], $constraint->getColumns()),
            'idx',
            $this->_getMaxIdentifierLength()
        );

        $indexCandidate = $this->_createIndex($constraint->getColumns(), $indexName, true, false);

        foreach ($this->_indexes as $existingIndex) {
            if ($indexCandidate->isFullfilledBy($existingIndex)) {
                return $this;
            }
        }

        $this->implicitIndexes[$this->normalizeIdentifier($indexName)] = $indexCandidate;

        return $this;
    }

    protected function _addForeignKeyConstraint(ForeignKeyConstraint $constraint): self
    {
        $constraint->setLocalTable($this);

        $name = $constraint->getName() !== ''
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
     */
    private function normalizeIdentifier(?string $identifier): string
    {
        if ($identifier === null) {
            return '';
        }

        return $this->trimQuotes(strtolower($identifier));
    }

    public function setComment(string $comment): self
    {
        // For keeping backward compatibility with MySQL in previous releases, table comments are stored as options.
        $this->addOption('comment', $comment);

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->_options['comment'] ?? null;
    }

    /**
     * @param array<string|int, string> $columns
     * @param array<int, string>        $flags
     * @param array<string, mixed>      $options
     *
     * @throws SchemaException
     */
    private function _createUniqueConstraint(array $columns, string $indexName, array $flags = [], array $options = []): UniqueConstraint
    {
        if (preg_match('(([^a-zA-Z0-9_]+))', $this->normalizeIdentifier($indexName)) === 1) {
            throw IndexNameInvalid::new($indexName);
        }

        foreach ($columns as $index => $value) {
            if (is_string($index)) {
                $columnName = $index;
            } else {
                $columnName = $value;
            }

            if (! $this->hasColumn($columnName)) {
                throw ColumnDoesNotExist::new($columnName, $this->_name);
            }
        }

        return new UniqueConstraint($indexName, $columns, $flags, $options);
    }

    /**
     * @param array<int, string>   $columns
     * @param array<int, string>   $flags
     * @param array<string, mixed> $options
     *
     * @throws SchemaException
     */
    private function _createIndex(array $columns, string $indexName, bool $isUnique, bool $isPrimary, array $flags = [], array $options = []): Index
    {
        if (preg_match('(([^a-zA-Z0-9_]+))', $this->normalizeIdentifier($indexName)) === 1) {
            throw IndexNameInvalid::new($indexName);
        }

        foreach ($columns as $columnName) {
            if (! $this->hasColumn($columnName)) {
                throw ColumnDoesNotExist::new($columnName, $this->_name);
            }
        }

        return new Index($indexName, $columns, $isUnique, $isPrimary, $flags, $options);
    }
}
