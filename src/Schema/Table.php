<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
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

use function array_filter;
use function array_merge;
use function array_values;
use function in_array;
use function is_string;
use function preg_match;
use function sprintf;
use function strtolower;

use const ARRAY_FILTER_USE_KEY;

/**
 * Object Representation of a table.
 */
class Table extends AbstractAsset
{
    /** @var Column[] */
    protected array $_columns = [];

    /** @var Index[] */
    private array $implicitIndexes = [];

    /** @var Index[] */
    protected array $_indexes = [];

    protected ?string $_primaryKeyName = null;

    /** @var UniqueConstraint[] */
    protected array $uniqueConstraints = [];

    /** @var ForeignKeyConstraint[] */
    protected array $_fkConstraints = [];

    /** @var mixed[] */
    protected array $_options = [
        'create_options' => [],
    ];

    protected ?SchemaConfig $_schemaConfig = null;

    /**
     * @param array<Column>               $columns
     * @param array<Index>                $indexes
     * @param array<UniqueConstraint>     $uniqueConstraints
     * @param array<ForeignKeyConstraint> $fkConstraints
     * @param array<string, mixed>        $options
     *
     * @throws SchemaException
     * @throws Exception
     */
    public function __construct(
        string $name,
        array $columns = [],
        array $indexes = [],
        array $uniqueConstraints = [],
        array $fkConstraints = [],
        array $options = []
    ) {
        if ($name === '') {
            throw InvalidTableName::new($name);
        }

        $this->_setName($name);

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
     *
     * @throws SchemaException
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
     *
     * @throws SchemaException
     */
    public function addUniqueConstraint(
        array $columnNames,
        ?string $indexName = null,
        array $flags = [],
        array $options = []
    ): self {
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
    public function addIndex(
        array $columnNames,
        ?string $indexName = null,
        array $flags = [],
        array $options = []
    ): self {
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
     * @throws SchemaException
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
    public function dropIndex(string $name): void
    {
        $name = $this->normalizeIdentifier($name);

        if (! $this->hasIndex($name)) {
            throw IndexDoesNotExist::new($name, $this->_name);
        }

        unset($this->_indexes[$name]);
    }

    /**
     * @param array<int, string>   $columnNames
     * @param array<string, mixed> $options
     *
     * @throws SchemaException
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
     * @param string      $oldName The name of the index to rename from.
     * @param string|null $newName The name of the index to rename to.
     *                                  If null is given, the index name will be auto-generated.
     *
     * @throws SchemaException If no index exists for the given current name
     *                         or if an index with the given new name already exists on this table.
     */
    public function renameIndex(string $oldName, ?string $newName = null): self
    {
        $oldName           = $this->normalizeIdentifier($oldName);
        $normalizedNewName = $this->normalizeIdentifier($newName);

        if ($oldName === $normalizedNewName) {
            return $this;
        }

        if (! $this->hasIndex($oldName)) {
            throw IndexDoesNotExist::new($oldName, $this->_name);
        }

        if ($this->hasIndex($normalizedNewName)) {
            throw IndexAlreadyExists::new($normalizedNewName, $this->_name);
        }

        $oldIndex = $this->_indexes[$oldName];

        if ($oldIndex->isPrimary()) {
            $this->dropPrimaryKey();

            return $this->setPrimaryKey($oldIndex->getColumns(), $newName ?? null);
        }

        unset($this->_indexes[$oldName]);

        if ($oldIndex->isUnique()) {
            return $this->addUniqueIndex($oldIndex->getColumns(), $newName, $oldIndex->getOptions());
        }

        return $this->addIndex($oldIndex->getColumns(), $newName, $oldIndex->getFlags(), $oldIndex->getOptions());
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
     *
     * @throws SchemaException
     */
    public function addColumn(string $name, string $typeName, array $options = []): Column
    {
        $column = new Column($name, Type::getType($typeName), $options);

        $this->_addColumn($column);

        return $column;
    }

    /**
     * Change Column Details.
     *
     * @param array<string, mixed> $options
     *
     * @throws SchemaException
     */
    public function changeColumn(string $name, array $options): self
    {
        $column = $this->getColumn($name);
        $column->setOptions($options);

        return $this;
    }

    /**
     * Drops a Column from the Table.
     */
    public function dropColumn(string $name): self
    {
        $name = $this->normalizeIdentifier($name);

        unset($this->_columns[$name]);

        return $this;
    }

    /**
     * Adds a foreign key constraint.
     *
     * Name is inferred from the local columns.
     *
     * @param array<int, string>   $localColumnNames
     * @param array<int, string>   $foreignColumnNames
     * @param array<string, mixed> $options
     *
     * @throws SchemaException
     */
    public function addForeignKeyConstraint(
        string $foreignTableName,
        array $localColumnNames,
        array $foreignColumnNames,
        array $options = [],
        ?string $name = null
    ): self {
        if ($name === null) {
            $name = $this->_generateIdentifierName(
                array_merge((array) $this->getName(), $localColumnNames),
                'fk',
                $this->_getMaxIdentifierLength()
            );
        }

        foreach ($localColumnNames as $columnName) {
            if (! $this->hasColumn($columnName)) {
                throw ColumnDoesNotExist::new($columnName, $this->_name);
            }
        }

        $constraint = new ForeignKeyConstraint(
            $localColumnNames,
            $foreignTableName,
            $foreignColumnNames,
            $name,
            $options
        );

        return $this->_addForeignKeyConstraint($constraint);
    }

    public function addOption(string $name, mixed $value): self
    {
        $this->_options[$name] = $value;

        return $this;
    }

    /**
     * Returns whether this table has a foreign key constraint with the given name.
     */
    public function hasForeignKey(string $name): bool
    {
        $name = $this->normalizeIdentifier($name);

        return isset($this->_fkConstraints[$name]);
    }

    /**
     * Returns the foreign key constraint with the given name.
     *
     * @throws SchemaException If the foreign key does not exist.
     */
    public function getForeignKey(string $name): ForeignKeyConstraint
    {
        $name = $this->normalizeIdentifier($name);

        if (! $this->hasForeignKey($name)) {
            throw ForeignKeyDoesNotExist::new($name, $this->_name);
        }

        return $this->_fkConstraints[$name];
    }

    /**
     * Removes the foreign key constraint with the given name.
     *
     * @throws SchemaException
     */
    public function removeForeignKey(string $name): void
    {
        $name = $this->normalizeIdentifier($name);

        if (! $this->hasForeignKey($name)) {
            throw ForeignKeyDoesNotExist::new($name, $this->_name);
        }

        unset($this->_fkConstraints[$name]);
    }

    /**
     * Returns whether this table has a unique constraint with the given name.
     */
    public function hasUniqueConstraint(string $name): bool
    {
        $name = $this->normalizeIdentifier($name);

        return isset($this->uniqueConstraints[$name]);
    }

    /**
     * Returns the unique constraint with the given name.
     *
     * @throws SchemaException If the unique constraint does not exist.
     */
    public function getUniqueConstraint(string $name): UniqueConstraint
    {
        $name = $this->normalizeIdentifier($name);

        if (! $this->hasUniqueConstraint($name)) {
            throw UniqueConstraintDoesNotExist::new($name, $this->_name);
        }

        return $this->uniqueConstraints[$name];
    }

    /**
     * Removes the unique constraint with the given name.
     *
     * @throws SchemaException If the unique constraint does not exist.
     */
    public function removeUniqueConstraint(string $name): void
    {
        $name = $this->normalizeIdentifier($name);

        if (! $this->hasUniqueConstraint($name)) {
            throw UniqueConstraintDoesNotExist::new($name, $this->_name);
        }

        unset($this->uniqueConstraints[$name]);
    }

    /**
     * Returns the list of table columns.
     *
     * @return list<Column>
     */
    public function getColumns(): array
    {
        return array_values($this->_columns);
    }

    /**
     * Returns only columns that have specified names
     *
     * @param string[] $columnNames
     *
     * @return Column[]
     */
    private function filterColumns(array $columnNames, bool $reverse = false): array
    {
        return array_filter($this->_columns, static function (string $columnName) use ($columnNames, $reverse): bool {
            return in_array($columnName, $columnNames, true) !== $reverse;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Returns whether this table has a Column with the given name.
     */
    public function hasColumn(string $name): bool
    {
        $name = $this->normalizeIdentifier($name);

        return isset($this->_columns[$name]);
    }

    /**
     * Returns the Column with the given name.
     *
     * @throws SchemaException If the column does not exist.
     */
    public function getColumn(string $name): Column
    {
        $name = $this->normalizeIdentifier($name);

        if (! $this->hasColumn($name)) {
            throw ColumnDoesNotExist::new($name, $this->_name);
        }

        return $this->_columns[$name];
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
     * @return array<string, Column>
     *
     * @throws Exception
     */
    public function getPrimaryKeyColumns(): array
    {
        $primaryKey = $this->getPrimaryKey();

        if ($primaryKey === null) {
            throw new Exception(sprintf('Table "%s" has no primary key.', $this->getName()));
        }

        return $this->filterColumns($primaryKey->getColumns());
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
    public function hasIndex(string $name): bool
    {
        $name = $this->normalizeIdentifier($name);

        return isset($this->_indexes[$name]);
    }

    /**
     * Returns the Index with the given name.
     *
     * @throws SchemaException If the index does not exist.
     */
    public function getIndex(string $name): Index
    {
        $name = $this->normalizeIdentifier($name);

        if (! $this->hasIndex($name)) {
            throw IndexDoesNotExist::new($name, $this->_name);
        }

        return $this->_indexes[$name];
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
        return $this->uniqueConstraints;
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

    public function getOption(string $name): mixed
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

    /**
     * @throws SchemaException
     */
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

        $this->uniqueConstraints[$name] = $constraint;

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
            array_merge([$this->getName()], $constraint->getLocalColumns()),
            'idx',
            $this->_getMaxIdentifierLength()
        );

        $indexCandidate = $this->_createIndex($constraint->getLocalColumns(), $indexName, false, false);

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
    private function _createUniqueConstraint(
        array $columns,
        string $indexName,
        array $flags = [],
        array $options = []
    ): UniqueConstraint {
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
    private function _createIndex(
        array $columns,
        string $indexName,
        bool $isUnique,
        bool $isPrimary,
        array $flags = [],
        array $options = []
    ): Index {
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
