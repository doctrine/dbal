<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\ColumnAlreadyExists;
use Doctrine\DBAL\Schema\Exception\ColumnDoesNotExist;
use Doctrine\DBAL\Schema\Exception\ForeignKeyDoesNotExist;
use Doctrine\DBAL\Schema\Exception\IndexAlreadyExists;
use Doctrine\DBAL\Schema\Exception\IndexDoesNotExist;
use Doctrine\DBAL\Schema\Exception\IndexNameInvalid;
use Doctrine\DBAL\Schema\Exception\InvalidState;
use Doctrine\DBAL\Schema\Exception\InvalidTableName;
use Doctrine\DBAL\Schema\Exception\PrimaryKeyAlreadyExists;
use Doctrine\DBAL\Schema\Exception\UniqueConstraintDoesNotExist;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser\OptionallyQualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeProvider;
use Doctrine\Deprecations\Deprecation;
use LogicException;

use function array_diff_key;
use function array_map;
use function array_merge;
use function array_values;
use function assert;
use function count;
use function implode;
use function in_array;
use function preg_match;
use function sprintf;
use function strtolower;

/**
 * Object Representation of a table.
 *
 * @final
 * @extends AbstractNamedObject<OptionallyQualifiedName>
 */
class Table extends AbstractNamedObject
{
    /** @var Column[] */
    protected array $_columns = [];

    /** @var array<string, string> keys are new names, values are old names */
    protected array $renamedColumns;

    /** @var Index[] */
    protected array $_indexes = [];

    /**
     * The keys of this array are the names of the indexes that were implicitly created as backing for foreign key
     * constraints. The values are not used but must be non-null for {@link isset()} to work correctly.
     *
     * @var array<string,true>
     */
    private array $implicitIndexNames = [];

    /** @deprecated Use {@see $primaryKeyConstraint} instead. */
    protected ?string $_primaryKeyName = null;

    /** @var UniqueConstraint[] */
    protected array $uniqueConstraints = [];

    /** @var ForeignKeyConstraint[] */
    protected array $_fkConstraints = [];

    /** @var mixed[] */
    protected array $_options = [
        'create_options' => [],
    ];

    /** @deprecated Pass a {@link TableConfiguration} instance to the constructor instead. */
    protected ?SchemaConfig $_schemaConfig = null;

    /** @var positive-int */
    private int $maxIdentifierLength;

    private ?PrimaryKeyConstraint $primaryKeyConstraint = null;

    private bool $failedToParsePrimaryKeyConstraint = false;

    private ?TypeProvider $typeRegistry = null;

    /**
     * @internal since doctrine/dbal 4.5. Use {@link Table::editor()} to instantiate an editor and
     *           {@link TableEditor::create()} to create a table.
     *
     * @param array<Column>               $columns
     * @param array<Index>                $indexes
     * @param array<UniqueConstraint>     $uniqueConstraints
     * @param array<ForeignKeyConstraint> $fkConstraints
     * @param array<string, mixed>        $options
     * @param array<string, string>       $renamedColumns
     */
    public function __construct(
        string $name,
        array $columns = [],
        array $indexes = [],
        array $uniqueConstraints = [],
        array $fkConstraints = [],
        array $options = [],
        ?TableConfiguration $configuration = null,
        ?PrimaryKeyConstraint $primaryKeyConstraint = null,
        array $renamedColumns = [],
    ) {
        if ($name === '') {
            throw InvalidTableName::new($name);
        }

        parent::__construct($name);

        $configuration ??= (new SchemaConfig())->toTableConfiguration();

        $this->maxIdentifierLength = $configuration->getMaxIdentifierLength();

        foreach ($columns as $column) {
            $this->_addColumn($column);
        }

        foreach ($indexes as $idx) {
            $this->_addIndex($idx);
        }

        if ($primaryKeyConstraint !== null) {
            $this->addPrimaryKeyConstraint($primaryKeyConstraint);
        }

        foreach ($uniqueConstraints as $uniqueConstraint) {
            $this->_addUniqueConstraint($uniqueConstraint);
        }

        foreach ($fkConstraints as $fkConstraint) {
            $this->_addForeignKeyConstraint($fkConstraint);
        }

        $this->_options = array_merge($this->_options, $options);

        $this->renamedColumns = $renamedColumns;
    }

    protected function getNameParser(): OptionallyQualifiedNameParser
    {
        return Parsers::getOptionallyQualifiedNameParser();
    }

    /** @deprecated Pass a {@link TableConfiguration} instance to the constructor instead. */
    public function setSchemaConfig(SchemaConfig $schemaConfig): void
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6635',
            '%s is deprecated. Pass TableConfiguration to the constructor instead.',
            __METHOD__,
        );

        $this->_schemaConfig = $schemaConfig;

        $this->maxIdentifierLength = $schemaConfig->getMaxIdentifierLength();
    }

    /**
     * Sets the Primary Key.
     *
     * @deprecated Use {@see addPrimaryKeyConstraint()} instead.
     *
     * @param non-empty-list<string> $columnNames
     */
    public function setPrimaryKey(array $columnNames, ?string $indexName = null): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6867',
            '%s() is deprecated. Use Table::addPrimaryKeyConstraint() instead.',
            __METHOD__,
        );

        if ($indexName === null) {
            $indexName = 'primary';
        }

        $this->_addIndex($this->_createIndex($columnNames, $indexName, true, true));

        foreach ($columnNames as $columnName) {
            $column = $this->getColumn($columnName);

            if (! $column->getNotnull()) {
                Deprecation::trigger(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/6787',
                    'Using nullable columns (%s.%s) in a primary key index is deprecated.',
                    $this->getName(),
                    $columnName,
                );
            }

            $column->setNotnull(true);
        }

        return $this;
    }

    /** @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::addPrimaryKeyConstraint()} instead. */
    public function addPrimaryKeyConstraint(PrimaryKeyConstraint $primaryKeyConstraint): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::addPrimaryKeyConstraint() instead.',
            __METHOD__,
        );

        $this->setPrimaryKey(
            array_map(
                static fn (UnqualifiedName $columnName): string => $columnName->toString(),
                $primaryKeyConstraint->getColumnNames(),
            ),
            $primaryKeyConstraint->getObjectName()?->toString(),
        );

        // there is no way to set a primary index with flags. we have to set it and then add the flag
        if (! $primaryKeyConstraint->isClustered()) {
            $index = $this->getPrimaryKey();
            assert($index !== null);
            $index->addFlag('nonclustered');
        }

        $this->primaryKeyConstraint = $primaryKeyConstraint;

        return $this;
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::addUniqueConstraint()} instead.
     *
     * @param non-empty-list<string> $columnNames
     * @param array<int, string>     $flags
     * @param array<string, mixed>   $options
     */
    public function addUniqueConstraint(
        array $columnNames,
        ?string $indexName = null,
        array $flags = [],
        array $options = [],
    ): self {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::addUniqueConstraint() instead.',
            __METHOD__,
        );

        $indexName ??= $this->_generateIdentifierName(
            array_merge([$this->getName()], $columnNames),
            'uniq',
            $this->_getMaxIdentifierLength(),
        );

        return $this->_addUniqueConstraint($this->_createUniqueConstraint($columnNames, $indexName, $flags, $options));
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::addIndex()} instead.
     *
     * @param non-empty-list<string> $columnNames
     * @param array<int, string>     $flags
     * @param array<string, mixed>   $options
     */
    public function addIndex(
        array $columnNames,
        ?string $indexName = null,
        array $flags = [],
        array $options = [],
    ): self {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::addIndex() instead.',
            __METHOD__,
        );

        $indexName ??= $this->_generateIdentifierName(
            array_merge([$this->getName()], $columnNames),
            'idx',
            $this->_getMaxIdentifierLength(),
        );

        return $this->_addIndex($this->_createIndex($columnNames, $indexName, false, false, $flags, $options));
    }

    /**
     * Drops the primary key from this table.
     *
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::dropPrimaryKeyConstraint()}
     *             instead.
     */
    public function dropPrimaryKey(): void
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::dropPrimaryKeyConstraint() instead.',
            __METHOD__,
        );

        $this->primaryKeyConstraint              = null;
        $this->failedToParsePrimaryKeyConstraint = false;

        if ($this->_primaryKeyName === null) {
            return;
        }

        $this->dropIndex($this->_primaryKeyName);
        $this->_primaryKeyName = null;
    }

    /**
     * Drops an index from this table.
     *
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::dropIndex()} instead.
     */
    public function dropIndex(string $name): void
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::dropIndex() instead.',
            __METHOD__,
        );

        $name = $this->normalizeIdentifier($name);

        if (! $this->hasIndex($name)) {
            throw IndexDoesNotExist::new($name, $this->_name);
        }

        unset($this->_indexes[$name]);
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::addIndex()} instead.
     *
     * @param non-empty-list<string> $columnNames
     * @param array<string, mixed>   $options
     */
    public function addUniqueIndex(array $columnNames, ?string $indexName = null, array $options = []): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::addIndex() instead.',
            __METHOD__,
        );

        $indexName ??= $this->_generateIdentifierName(
            array_merge([$this->getName()], $columnNames),
            'uniq',
            $this->_getMaxIdentifierLength(),
        );

        return $this->_addIndex($this->_createIndex($columnNames, $indexName, true, false, [], $options));
    }

    /**
     * Renames an index.
     *
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::renameIndex()} instead.
     *
     * @param string      $oldName The name of the index to rename from.
     * @param string|null $newName The name of the index to rename to. If null is given, the index name
     *                             will be auto-generated.
     */
    public function renameIndex(string $oldName, ?string $newName = null): self
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::renameIndex() instead.',
            __METHOD__,
        );

        if (! $this->hasIndex($oldName)) {
            throw IndexDoesNotExist::new($oldName, $this->_name);
        }

        $normalizedOldName = $this->normalizeIdentifier($oldName);

        if ($newName !== null) {
            $normalizedNewName = $this->normalizeIdentifier($newName);

            if ($normalizedOldName === $normalizedNewName) {
                return $this;
            }

            if ($this->hasIndex($newName)) {
                throw IndexAlreadyExists::new($newName, $this->_name);
            }
        }

        $oldIndex = $this->_indexes[$normalizedOldName];

        if ($oldIndex->isPrimary()) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6867',
                'Renaming primary key constraint via %s() is deprecated. Use Table::dropPrimaryKey() and '
                    . ' Table::addPrimaryKeyConstraint() instead.',
                __METHOD__,
            );

            $this->dropPrimaryKey();

            return $this->setPrimaryKey($oldIndex->getColumns(), $newName ?? null);
        }

        unset($this->_indexes[$normalizedOldName]);

        if ($oldIndex->isUnique()) {
            return $this->addUniqueIndex($oldIndex->getColumns(), $newName, $oldIndex->getOptions());
        }

        return $this->addIndex($oldIndex->getColumns(), $newName, $oldIndex->getFlags(), $oldIndex->getOptions());
    }

    /**
     * Checks if an index begins in the order of the given columns.
     *
     * @deprecated
     *
     * @param array<int, string> $columnNames
     */
    public function columnsAreIndexed(array $columnNames): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6710',
            '%s is deprecated.',
            __METHOD__,
        );

        foreach ($this->getIndexes() as $index) {
            if ($index->spansColumns($columnNames)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::addColumn()} instead.
     *
     * @param array<string, mixed> $options
     *
     * @throws TypesException
     */
    public function addColumn(string $name, string $typeName, array $options = []): Column
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::addColumn() instead.',
            __METHOD__,
        );

        $column = new Column($name, $typeName, $options);
        $column->setTypeRegistry($this->typeRegistry);

        $this->_addColumn($column);

        return $column;
    }

    /**
     * @internal This method is necessary for ensuring backward compatibility
     * for the deprecated {@see Column::getType()} method.
     */
    public function setTypeRegistry(?TypeProvider $typeRegistry): void
    {
        $this->typeRegistry = $typeRegistry;
    }

    /** @return array<string, string> */
    final public function getRenamedColumns(): array
    {
        return $this->renamedColumns;
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::renameColumn()} instead.
     *
     * @param non-empty-string $oldName
     * @param non-empty-string $newName
     *
     * @throws LogicException
     */
    final public function renameColumn(string $oldName, string $newName): Column
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::renameColumn() instead.',
            __METHOD__,
        );

        $oldName = $this->normalizeIdentifier($oldName);
        $newName = $this->normalizeIdentifier($newName);

        if ($oldName === $newName) {
            throw new LogicException(sprintf(
                'Attempt to rename column "%s.%s" to the same name.',
                $this->name->toString(),
                $oldName,
            ));
        }

        $column = $this->getColumn($oldName);

        $column->_name = $newName;
        unset($this->_columns[$oldName]);
        $this->_addColumn($column);

        $this->renameColumnInIndexes($oldName, $newName);
        $this->renameColumnInForeignKeyConstraints($oldName, $newName);
        $this->renameColumnInUniqueConstraints($oldName, $newName);

        // If a column is renamed multiple times, we only want to know the original and last new name
        if (isset($this->renamedColumns[$oldName])) {
            $toRemove = $oldName;
            $oldName  = $this->renamedColumns[$oldName];
            unset($this->renamedColumns[$toRemove]);
        }

        if ($newName !== $oldName) {
            $this->renamedColumns[$newName] = $oldName;
        }

        return $column;
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::modifyColumn()} instead.
     *
     * @param array<string, mixed> $options
     */
    public function modifyColumn(string $name, array $options): self
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::modifyColumn() instead.',
            __METHOD__,
        );

        $column = $this->getColumn($name);
        $column->setOptions($options);

        return $this;
    }

    /**
     * Drops a Column from the Table.
     *
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::dropColumn()} instead.
     */
    public function dropColumn(string $name): self
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::dropColumn() instead.',
            __METHOD__,
        );

        $name = $this->normalizeIdentifier($name);

        $foreignKeyConstraintNames = $this->getForeignKeyConstraintNamesByLocalColumnName($name);
        $uniqueConstraintNames     = $this->getUniqueConstraintNamesByColumnName($name);

        if (count($foreignKeyConstraintNames) > 0 || count($uniqueConstraintNames) > 0) {
            $constraints = [];

            if (count($foreignKeyConstraintNames) > 0) {
                $constraints[] = 'foreign key constraints: ' . implode(', ', $foreignKeyConstraintNames);
            }

            if (count($uniqueConstraintNames) > 0) {
                $constraints[] = 'unique constraints: ' . implode(', ', $uniqueConstraintNames);
            }

            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6559',
                'Dropping columns referenced by constraints is deprecated.'
                    . ' Column %s is used by the following constraints: %s ',
                $name,
                implode('; ', $constraints),
            );
        }

        unset($this->_columns[$name]);

        return $this;
    }

    /**
     * Adds a foreign key constraint.
     *
     * Name is inferred from the local columns.
     *
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::addForeignKeyConstraint()} instead.
     *
     * @param non-empty-list<string> $localColumnNames
     * @param non-empty-list<string> $foreignColumnNames
     * @param array<string, mixed>   $options
     */
    public function addForeignKeyConstraint(
        string $foreignTableName,
        array $localColumnNames,
        array $foreignColumnNames,
        array $options = [],
        ?string $name = null,
    ): self {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::addForeignKeyConstraint() instead.',
            __METHOD__,
        );

        $name ??= $this->_generateIdentifierName(
            array_merge([$this->getName()], $localColumnNames),
            'fk',
            $this->_getMaxIdentifierLength(),
        );

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
            $options,
        );

        return $this->_addForeignKeyConstraint($constraint);
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::setOptions()} instead.
     *
     * @return $this
     */
    public function addOption(string $name, mixed $value): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::setOptions() instead.',
            __METHOD__,
        );

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
     * @deprecated Use {@link dropForeignKey()} instead.
     */
    public function removeForeignKey(string $name): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6560',
            'Table::removeForeignKey() is deprecated. Use Table::dropForeignKey() instead.',
        );

        $this->dropForeignKey($name);
    }

    /**
     * Drops the foreign key constraint with the given name.
     *
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::dropForeignKeyConstraint()}
     *             instead.
     */
    public function dropForeignKey(string $name): void
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::dropForeignKeyConstraint() instead.',
            __METHOD__,
        );

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
     * @deprecated Use {@link dropUniqueConstraint()} instead.
     */
    public function removeUniqueConstraint(string $name): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6560',
            'Table::removeUniqueConstraint() is deprecated. Use Table::dropUniqueConstraint() instead.',
        );

        $this->dropUniqueConstraint($name);
    }

    /**
     * Drops the unique constraint with the given name.
     *
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::dropUniqueConstraint()} instead.
     */
    public function dropUniqueConstraint(string $name): void
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::dropUniqueConstraint() instead.',
            __METHOD__,
        );

        $name = $this->normalizeIdentifier($name);

        if (! $this->hasUniqueConstraint($name)) {
            throw UniqueConstraintDoesNotExist::new($name, $this->_name);
        }

        unset($this->uniqueConstraints[$name]);
    }

    /**
     * Returns the list of table columns.
     *
     * @return non-empty-list<Column>
     */
    public function getColumns(): array
    {
        /** @phpstan-ignore return.type */
        return array_values($this->_columns);
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
     *
     * @deprecated Use {@see getPrimaryKeyConstraint()} instead.
     */
    public function getPrimaryKey(): ?Index
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6867',
            '%s() is deprecated. Use Table::getPrimaryKeyConstraint() instead.',
            __METHOD__,
        );

        if ($this->_primaryKeyName !== null) {
            return $this->getIndex($this->_primaryKeyName);
        }

        return null;
    }

    public function getPrimaryKeyConstraint(): ?PrimaryKeyConstraint
    {
        if ($this->failedToParsePrimaryKeyConstraint) {
            throw InvalidState::tableHasInvalidPrimaryKeyConstraint($this->getName());
        }

        return $this->primaryKeyConstraint;
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
     */
    public function getIndex(string $name): Index
    {
        $name = $this->normalizeIdentifier($name);

        if (! $this->hasIndex($name)) {
            throw IndexDoesNotExist::new($name, $this->_name);
        }

        return $this->_indexes[$name];
    }

    /** @return array<string, Index> */
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
        return $this->_options[$name] ?? null;
    }

    /** @return array<string, mixed> */
    public function getOptions(): array
    {
        return $this->_options;
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

    /**
     * @deprecated
     *
     * @return positive-int
     */
    protected function _getMaxIdentifierLength(): int
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6635',
            '%s is deprecated.',
            __METHOD__,
        );

        return $this->maxIdentifierLength;
    }

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
     */
    protected function _addIndex(Index $index): self
    {
        $indexName = $this->normalizeIdentifier($index->getName());

        $replacedImplicitIndexNames = [];

        foreach ($this->implicitIndexNames as $implicitIndexName => $_) {
            if (! isset($this->_indexes[$implicitIndexName])) {
                continue;
            }

            if ($this->_indexes[$implicitIndexName]->isFulfilledBy($index)) {
                $replacedImplicitIndexNames[$implicitIndexName] = true;
            }
        }

        if ($this->_primaryKeyName !== null && $index->isPrimary()) {
            throw PrimaryKeyAlreadyExists::new($this->_name);
        }

        if (isset($this->_indexes[$indexName]) && ! isset($replacedImplicitIndexNames[$indexName])) {
            throw IndexAlreadyExists::new($indexName, $this->_name);
        }

        foreach ($replacedImplicitIndexNames as $name => $_) {
            unset($this->_indexes[$name], $this->implicitIndexNames[$name]);
        }

        if ($index->isPrimary()) {
            $this->_primaryKeyName = $indexName;

            try {
                $this->primaryKeyConstraint              = $this->parsePrimaryKeyConstraint($index);
                $this->failedToParsePrimaryKeyConstraint = false;
            } catch (InvalidState) {
                $this->primaryKeyConstraint              = null;
                $this->failedToParsePrimaryKeyConstraint = true;
            }
        }

        $this->_indexes[$indexName] = $index;

        return $this;
    }

    private function parsePrimaryKeyConstraint(Index $index): ?PrimaryKeyConstraint
    {
        $indexedColumns = $index->getIndexedColumns();

        $columnNames = [];
        foreach ($indexedColumns as $indexedColumn) {
            if ($indexedColumn->getLength() !== null) {
                return null;
            }

            $columnNames[] = $indexedColumn->getColumnName();
        }

        // Do not derive the constraint name from the index name in the upgrade path. The primary index name defaults to
        // "PRIMARY", while the default constraint name is null (unspecified, to be generated by the database platform).
        return new PrimaryKeyConstraint(
            null,
            $columnNames,
            ! $index->hasFlag('nonclustered'),
        );
    }

    protected function _addUniqueConstraint(UniqueConstraint $constraint): self
    {
        $name = $constraint->getName() !== ''
            ? $constraint->getName()
            : $this->_generateIdentifierName(
                array_merge((array) $this->getName(), $constraint->getColumns()),
                'fk',
                $this->_getMaxIdentifierLength(),
            );

        $name = $this->normalizeIdentifier($name);

        $this->uniqueConstraints[$name] = $constraint;

        // If there is already an index that fulfills this requirements drop the request. In the case of __construct
        // calling this method during hydration from schema-details all the explicitly added indexes lead to duplicates.
        // This creates computation overhead in this case, however no duplicate indexes are ever added (column based).
        $indexName = $this->_generateIdentifierName(
            array_merge([$this->getName()], $constraint->getColumns()),
            'idx',
            $this->_getMaxIdentifierLength(),
        );

        $indexCandidate = $this->_createIndex($constraint->getColumns(), $indexName, true, false);

        foreach ($this->_indexes as $existingIndex) {
            if ($indexCandidate->isFulfilledBy($existingIndex)) {
                return $this;
            }
        }

        $this->implicitIndexNames[$this->normalizeIdentifier($indexName)] = true;

        return $this;
    }

    protected function _addForeignKeyConstraint(ForeignKeyConstraint $constraint): self
    {
        $name = $constraint->getName() !== ''
            ? $constraint->getName()
            : $this->_generateIdentifierName(
                array_merge((array) $this->getName(), $constraint->getLocalColumns()),
                'fk',
                $this->_getMaxIdentifierLength(),
            );

        $name = $this->normalizeIdentifier($name);
        if (isset($this->_fkConstraints[$name])) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/7125',
                'Overwriting an existing foreign key constraint ("%s") is deprecated.',
                $name,
            );
        }

        $this->_fkConstraints[$name] = $constraint;

        // add an explicit index on the foreign key columns.
        // If there is already an index that fulfills this requirements drop the request. In the case of __construct
        // calling this method during hydration from schema-details all the explicitly added indexes lead to duplicates.
        // This creates computation overhead in this case, however no duplicate indexes are ever added (column based).
        $indexName = $this->_generateIdentifierName(
            array_merge([$this->getName()], $constraint->getLocalColumns()),
            'idx',
            $this->_getMaxIdentifierLength(),
        );

        $indexCandidate = $this->_createIndex($constraint->getLocalColumns(), $indexName, false, false);

        foreach ($this->_indexes as $existingIndex) {
            if ($indexCandidate->isFulfilledBy($existingIndex)) {
                return $this;
            }
        }

        $this->_addIndex($indexCandidate);
        $this->implicitIndexNames[$this->normalizeIdentifier($indexName)] = true;

        return $this;
    }

    /**
     * Normalizes a given identifier.
     *
     * Trims quotes and lowercases the given identifier.
     *
     * @return non-empty-string
     */
    private function normalizeIdentifier(string $identifier): string
    {
        /** @phpstan-ignore return.type */
        return $this->trimQuotes(strtolower($identifier));
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see TableEditor::setComment()} instead.
     *
     * @return $this
     */
    public function setComment(string $comment): self
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7389',
            '%s is deprecated. Use Table::edit() and TableEditor::setComment() instead.',
            __METHOD__,
        );

        // For keeping backward compatibility with MySQL in previous releases, table comments are stored as options.
        $this->addOption('comment', $comment);

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->_options['comment'] ?? null;
    }

    /**
     * Instantiates a new table editor.
     */
    public static function editor(): TableEditor
    {
        return new TableEditor();
    }

    /**
     * Instantiates a new table editor and initializes it with the table's properties.
     */
    public function edit(): TableEditor
    {
        $editor = self::editor()
            ->setName($this->getObjectName())
            ->setColumns(...array_values($this->_columns))
            ->setIndexes(...array_values(array_diff_key($this->_indexes, $this->implicitIndexNames)))
            ->setPrimaryKeyConstraint($this->primaryKeyConstraint)
            ->setUniqueConstraints(...array_values($this->uniqueConstraints))
            ->setForeignKeyConstraints(...array_values($this->_fkConstraints));

        $options = $this->_options;

        if (isset($options['comment'])) {
            $editor->setComment($options['comment']);
            unset($options['comment']);
        }

        return $editor
            ->setOptions($options)
            ->setConfiguration(
                new TableConfiguration($this->maxIdentifierLength),
            )
            ->setTypeRegistry($this->typeRegistry);
    }

    /**
     * @param non-empty-list<string> $columns
     * @param array<int, string>     $flags
     * @param array<string, mixed>   $options
     */
    private function _createUniqueConstraint(
        array $columns,
        string $indexName,
        array $flags = [],
        array $options = [],
    ): UniqueConstraint {
        if (preg_match('(([^a-zA-Z0-9_]+))', $this->normalizeIdentifier($indexName)) === 1) {
            throw IndexNameInvalid::new($indexName);
        }

        foreach ($columns as $columnName) {
            if (! $this->hasColumn($columnName)) {
                throw ColumnDoesNotExist::new($columnName, $this->_name);
            }
        }

        return new UniqueConstraint($indexName, $columns, $flags, $options);
    }

    /**
     * @param non-empty-list<string> $columns
     * @param array<int, string>     $flags
     * @param array<string, mixed>   $options
     */
    private function _createIndex(
        array $columns,
        string $indexName,
        bool $isUnique,
        bool $isPrimary,
        array $flags = [],
        array $options = [],
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

    /** @param non-empty-string $newName */
    private function renameColumnInIndexes(string $oldName, string $newName): void
    {
        foreach ($this->_indexes as $key => $index) {
            $modified = false;
            $columns  = [];
            foreach ($index->getColumns() as $columnName) {
                if ($columnName === $oldName) {
                    $columns[] = $newName;
                    $modified  = true;
                } else {
                    $columns[] = $columnName;
                }
            }

            if ($modified) {
                $this->_indexes[$key] = new Index(
                    $index->getName(),
                    $columns,
                    $index->isUnique(),
                    $index->isPrimary(),
                    $index->getFlags(),
                    $index->getOptions(),
                );
            }
        }
    }

    /**
     * @param non-empty-string $oldName
     * @param non-empty-string $newName
     */
    private function renameColumnInForeignKeyConstraints(string $oldName, string $newName): void
    {
        foreach ($this->_fkConstraints as $key => $constraint) {
            $modified     = false;
            $localColumns = [];
            foreach ($constraint->getLocalColumns() as $columnName) {
                if ($columnName === $oldName) {
                    $localColumns[] = $newName;
                    $modified       = true;
                } else {
                    $localColumns[] = $columnName;
                }
            }

            if ($modified) {
                $this->_fkConstraints[$key] = new ForeignKeyConstraint(
                    $localColumns, // @phpstan-ignore argument.type
                    $constraint->getForeignTableName(),
                    $constraint->getForeignColumns(), // @phpstan-ignore argument.type
                    $constraint->getName(),
                    $constraint->getOptions(),
                );
            }
        }
    }

    /**
     * @param non-empty-string $oldName
     * @param non-empty-string $newName
     */
    private function renameColumnInUniqueConstraints(string $oldName, string $newName): void
    {
        foreach ($this->uniqueConstraints as $key => $constraint) {
            $modified = false;
            $columns  = [];
            foreach ($constraint->getColumns() as $columnName) {
                if ($columnName === $oldName) {
                    $columns[] = $newName;
                    $modified  = true;
                } else {
                    $columns[] = $columnName;
                }
            }

            if ($modified) {
                $this->uniqueConstraints[$key] = new UniqueConstraint(
                    $constraint->getName(),
                    $columns, // @phpstan-ignore argument.type
                    $constraint->getFlags(),
                    $constraint->getOptions(),
                );
            }
        }
    }

    /** @return list<string> */
    private function getForeignKeyConstraintNamesByLocalColumnName(string $columnName): array
    {
        $names = [];

        foreach ($this->_fkConstraints as $name => $constraint) {
            if (in_array($columnName, $constraint->getLocalColumns(), true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** @return list<string> */
    private function getUniqueConstraintNamesByColumnName(string $columnName): array
    {
        $names = [];

        foreach ($this->uniqueConstraints as $name => $constraint) {
            if (in_array($columnName, $constraint->getColumns(), true)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
