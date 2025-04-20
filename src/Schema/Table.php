<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\ColumnAlreadyExists;
use Doctrine\DBAL\Schema\Exception\ColumnDoesNotExist;
use Doctrine\DBAL\Schema\Exception\ForeignKeyDoesNotExist;
use Doctrine\DBAL\Schema\Exception\IndexAlreadyExists;
use Doctrine\DBAL\Schema\Exception\IndexDoesNotExist;
use Doctrine\DBAL\Schema\Exception\InvalidForeignKeyConstraintDefinition;
use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\InvalidTableName;
use Doctrine\DBAL\Schema\Exception\PrimaryKeyAlreadyExists;
use Doctrine\DBAL\Schema\Exception\UniqueConstraintDoesNotExist;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parser\OptionallyQualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
use LogicException;

use function array_keys;
use function array_map;
use function array_merge;
use function array_shift;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_int;
use function sprintf;
use function strtolower;
use function strtoupper;

/**
 * Object Representation of a table.
 *
 * @extends AbstractNamedObject<OptionallyQualifiedName>
 */
class Table extends AbstractNamedObject
{
    /** @var Column[] */
    protected array $_columns = [];

    /** @var array<string, string> keys are new names, values are old names */
    protected array $renamedColumns = [];

    /** @var Index[] */
    protected array $_indexes = [];

    /**
     * The keys of this array are the names of the indexes that were implicitly created as backing for foreign key
     * constraints. The values are not used but must be non-null for {@link isset()} to work correctly.
     *
     * @var array<string,true>
     */
    private array $implicitIndexNames = [];

    /** @var UniqueConstraint[] */
    protected array $uniqueConstraints = [];

    /** @var ForeignKeyConstraint[] */
    protected array $_fkConstraints = [];

    /** @var mixed[] */
    protected array $_options = [
        'create_options' => [],
    ];

    /** @var positive-int */
    private readonly int $maxIdentifierLength;

    private ?PrimaryKeyConstraint $primaryKeyConstraint = null;

    /**
     * @param array<Column>               $columns
     * @param array<Index>                $indexes
     * @param array<UniqueConstraint>     $uniqueConstraints
     * @param array<ForeignKeyConstraint> $fkConstraints
     * @param array<string, mixed>        $options
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
    }

    protected function getNameParser(): OptionallyQualifiedNameParser
    {
        return Parsers::getOptionallyQualifiedNameParser();
    }

    public function addPrimaryKeyConstraint(PrimaryKeyConstraint $primaryKeyConstraint): self
    {
        if ($this->primaryKeyConstraint !== null) {
            throw PrimaryKeyAlreadyExists::new($this->_name);
        }

        $this->primaryKeyConstraint = $primaryKeyConstraint;

        return $this;
    }

    /**
     * @param non-empty-list<string> $columnNames
     * @param array<int, string>     $flags
     */
    public function addUniqueConstraint(
        array $columnNames,
        ?string $indexName = null,
        array $flags = [],
    ): self {
        $indexName ??= $this->_generateIdentifierName(
            array_merge([$this->getName()], $columnNames),
            'uniq',
            $this->maxIdentifierLength,
        );

        $isClustered = in_array('clustered', $flags, true);

        return $this->_addUniqueConstraint($this->_createUniqueConstraint($columnNames, $indexName, $isClustered));
    }

    /**
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
        $indexName ??= $this->_generateIdentifierName(
            array_merge([$this->getName()], $columnNames),
            'idx',
            $this->maxIdentifierLength,
        );

        return $this->_addIndex($this->_createIndex($columnNames, $indexName, false, $flags, $options));
    }

    /**
     * Drops the primary key from this table.
     */
    public function dropPrimaryKey(): void
    {
        $this->primaryKeyConstraint = null;
    }

    /**
     * Drops an index from this table.
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
     * @param non-empty-list<string> $columnNames
     * @param array<string, mixed>   $options
     */
    public function addUniqueIndex(array $columnNames, ?string $indexName = null, array $options = []): self
    {
        $indexName ??= $this->_generateIdentifierName(
            array_merge([$this->getName()], $columnNames),
            'uniq',
            $this->maxIdentifierLength,
        );

        return $this->_addIndex($this->_createIndex($columnNames, $indexName, true, [], $options));
    }

    /**
     * Renames an index.
     *
     * @param string      $oldName The name of the index to rename from.
     * @param string|null $newName The name of the index to rename to. If null is given, the index name
     *                             will be auto-generated.
     */
    public function renameIndex(string $oldName, ?string $newName = null): self
    {
        if (! $this->hasIndex($oldName)) {
            throw IndexDoesNotExist::new($oldName, $this->_name);
        }

        $normalizedOldName = $this->normalizeIdentifier($oldName);

        $index = $this->getIndex($oldName);

        if ($newName !== null) {
            $normalizedNewName = $this->normalizeIdentifier($newName);

            if ($normalizedOldName === $normalizedNewName) {
                return $this;
            }

            if ($this->hasIndex($newName)) {
                throw IndexAlreadyExists::new($newName, $this->_name);
            }

            $name = $this->parseUnqualifiedName($newName);
        } else {
            $name = UnqualifiedName::unquoted(
                $this->generateName(
                    $index->getType() === IndexType::UNIQUE ? 'uniq' : 'idx',
                    array_map(
                        static fn (IndexedColumn $indexedColumn): UnqualifiedName => $indexedColumn->getColumnName(),
                        $index->getIndexedColumns(),
                    ),
                ),
            );
        }

        $index = $index->edit()
            ->setName($name)
            ->create();

        unset($this->_indexes[$normalizedOldName]);

        return $this->_addIndex($index);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws TypesException
     */
    public function addColumn(string $name, string $typeName, array $options = []): Column
    {
        $column = new Column($name, Type::getType($typeName), $options);

        $this->_addColumn($column);

        return $column;
    }

    /** @return array<string, string> */
    final public function getRenamedColumns(): array
    {
        return $this->renamedColumns;
    }

    /**
     * @param non-empty-string $oldName
     * @param non-empty-string $newName
     *
     * @throws LogicException
     */
    final public function renameColumn(string $oldName, string $newName): Column
    {
        $oldName = $this->normalizeIdentifier($oldName);
        $newName = $this->normalizeIdentifier($newName);

        if ($oldName === $newName) {
            throw new LogicException(sprintf(
                'Attempt to rename column "%s.%s" to the same name.',
                $this->getName(),
                $oldName,
            ));
        }

        $oldColumn = $this->getColumn($oldName);
        $options   = $oldColumn->toArray();

        unset($options['name'], $options['type']);

        $newColumn = new Column($newName, $oldColumn->getType(), $options);

        unset($this->_columns[$oldName]);
        $this->_addColumn($newColumn);

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

        return $newColumn;
    }

    /** @param array<string, mixed> $options */
    public function modifyColumn(string $name, array $options): self
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
     * Name is inferred from the referencing columns.
     *
     * @param non-empty-list<string> $referencingColumnNames
     * @param non-empty-list<string> $referencedColumnNames
     * @param array<string, mixed>   $options
     */
    public function addForeignKeyConstraint(
        string $referencedTableName,
        array $referencingColumnNames,
        array $referencedColumnNames,
        array $options = [],
        ?string $name = null,
    ): self {
        foreach ($referencingColumnNames as $columnName) {
            if (! $this->hasColumn($columnName)) {
                throw ColumnDoesNotExist::new($columnName, $this->_name);
            }
        }

        $referencingColumnNames = $this->parseUnqualifiedNames($referencingColumnNames);
        $referencedTableName    = $this->parseOptionallyQualifiedName($referencedTableName);
        $referencedColumnNames  = $this->parseUnqualifiedNames($referencedColumnNames);

        $matchType      = $this->parseMatchType($options);
        $onUpdateAction = $this->parseReferentialAction($options, 'onUpdate');
        $onDeleteAction = $this->parseReferentialAction($options, 'onDelete');

        $deferrability = $this->parseDeferrability($options);

        $editor = ForeignKeyConstraint::editor();

        if ($name !== null) {
            $editor->setName(
                $this->parseUnqualifiedName($name),
            );
        } else {
            $editor->setUnquotedName(
                $this->generateName('fk', $referencingColumnNames),
            );
        }

        $constraint = $editor
            ->setReferencingColumnNames(...$referencingColumnNames)
            ->setReferencedTableName($referencedTableName)
            ->setReferencedColumnNames(...$referencedColumnNames)
            ->setMatchType($matchType)
            ->setOnUpdateAction($onUpdateAction)
            ->setOnDeleteAction($onDeleteAction)
            ->setDeferrability($deferrability)
            ->create();

        return $this->_addForeignKeyConstraint($constraint);
    }

    private function parseUnqualifiedName(string $name): UnqualifiedName
    {
        $parser = Parsers::getUnqualifiedNameParser();

        try {
            return $parser->parse($name);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($name, $e);
        }
    }

    /**
     * @param non-empty-list<string> $names
     *
     * @return non-empty-list<UnqualifiedName>
     */
    private function parseUnqualifiedNames(array $names): array
    {
        $parser = Parsers::getUnqualifiedNameParser();

        return array_map(
            static function (string $name) use ($parser): UnqualifiedName {
                try {
                    return $parser->parse($name);
                } catch (Parser\Exception $e) {
                    throw InvalidName::fromParserException($name, $e);
                }
            },
            $names,
        );
    }

    private function parseOptionallyQualifiedName(string $name): OptionallyQualifiedName
    {
        $parser = Parsers::getOptionallyQualifiedNameParser();

        try {
            return $parser->parse($name);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($name, $e);
        }
    }

    /** @param array<string, mixed> $options */
    private function parseMatchType(array $options): MatchType
    {
        if (isset($options['match'])) {
            return MatchType::from(strtoupper($options['match']));
        }

        return MatchType::SIMPLE;
    }

    /** @param array<string, mixed> $options */
    private function parseReferentialAction(array $options, string $option): ReferentialAction
    {
        if (isset($options[$option])) {
            return ReferentialAction::from(strtoupper($options[$option]));
        }

        return ReferentialAction::NO_ACTION;
    }

    /** @param array<string, mixed> $options */
    private function parseDeferrability(array $options): Deferrability
    {
        // a constraint is INITIALLY IMMEDIATE unless explicitly declared as INITIALLY DEFERRED
        $isDeferred = isset($options['deferred']) && $options['deferred'] !== false;

        // a constraint is NOT DEFERRABLE unless explicitly declared as DEFERRABLE or is explicitly or implicitly
        // INITIALLY DEFERRED
        $isDeferrable = isset($options['deferrable'])
            ? $options['deferrable'] !== false
            : $isDeferred;

        if ($isDeferred) {
            if (! $isDeferrable) {
                throw InvalidForeignKeyConstraintDefinition::nonDeferrableInitiallyDeferred();
            }

            return Deferrability::DEFERRED;
        }

        return $isDeferrable ? Deferrability::DEFERRABLE : Deferrability::NOT_DEFERRABLE;
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
     * Drops the foreign key constraint with the given name.
     */
    public function dropForeignKey(string $name): void
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
     * Drops the unique constraint with the given name.
     */
    public function dropUniqueConstraint(string $name): void
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

    public function getPrimaryKeyConstraint(): ?PrimaryKeyConstraint
    {
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

            if (! $this->_indexes[$implicitIndexName]->isFulfilledBy($index)) {
                continue;
            }

            $replacedImplicitIndexNames[$implicitIndexName] = true;
        }

        if (isset($this->_indexes[$indexName]) && ! isset($replacedImplicitIndexNames[$indexName])) {
            throw IndexAlreadyExists::new($indexName, $this->_name);
        }

        foreach ($replacedImplicitIndexNames as $name => $_) {
            unset($this->_indexes[$name], $this->implicitIndexNames[$name]);
        }

        $this->_indexes[$indexName] = $index;

        return $this;
    }

    protected function _addUniqueConstraint(UniqueConstraint $constraint): self
    {
        $columnNames = $constraint->getColumnNames();

        $name = $constraint->getName() !== ''
            ? $constraint->getName()
            : $this->generateName('fk', $columnNames);

        $name = $this->normalizeIdentifier($name);

        $this->uniqueConstraints[$name] = $constraint;

        // If there is already an index that fulfills this requirements drop the request. In the case of __construct
        // calling this method during hydration from schema-details all the explicitly added indexes lead to duplicates.
        // This creates computation overhead in this case, however no duplicate indexes are ever added (column based).
        $indexName = $this->generateName('idx', $columnNames);

        $indexCandidate = Index::editor()
            ->setName(UnqualifiedName::unquoted($indexName))
            ->setType(IndexType::UNIQUE)
            ->setColumnNames(...$columnNames)
            ->create();

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
            : $this->generateName('fk', $constraint->getReferencingColumnNames());

        $name = $this->normalizeIdentifier($name);

        $this->_fkConstraints[$name] = $constraint;

        // add an explicit index on the foreign key columns.
        // If there is already an index that fulfills this requirements drop the request. In the case of __construct
        // calling this method during hydration from schema-details all the explicitly added indexes lead to duplicates.
        // This creates computation overhead in this case, however no duplicate indexes are ever added (column based).
        $indexName = $this->generateName('idx', $constraint->getReferencingColumnNames());

        $indexCandidate = Index::editor()
            ->setName(UnqualifiedName::unquoted($indexName))
            ->setColumnNames(...$constraint->getReferencingColumnNames())
            ->create();

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
        return self::editor()
            ->setName($this->getObjectName())
            ->setColumns($this->_columns)
            ->setIndexes($this->_indexes)
            ->setUniqueConstraints($this->uniqueConstraints)
            ->setForeignKeyConstraints($this->_fkConstraints)
            ->setOptions($this->_options)
            ->setConfiguration(
                new TableConfiguration($this->maxIdentifierLength),
            );
    }

    /** @param non-empty-list<string> $columns */
    private function _createUniqueConstraint(
        array $columns,
        string $indexName,
        bool $isClustered,
    ): UniqueConstraint {
        $constraintName = $this->parseUnqualifiedName($indexName);

        foreach ($columns as $columnName) {
            if (! $this->hasColumn($columnName)) {
                throw ColumnDoesNotExist::new($columnName, $this->_name);
            }
        }

        $columnNames = $this->parseUnqualifiedNames($columns);

        return UniqueConstraint::editor()
            ->setName($constraintName)
            ->setColumnNames(...$columnNames)
            ->setIsClustered($isClustered)
            ->create();
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
        array $flags = [],
        array $options = [],
    ): Index {
        $parsedName = $this->parseUnqualifiedName($indexName);

        $flagIndex = [];
        foreach ($flags as $flag) {
            $flagIndex[strtolower($flag)] = true;
        }

        $invalidFlags = $flagIndex;
        unset(
            $invalidFlags['fulltext'],
            $invalidFlags['spatial'],
            $invalidFlags['clustered'],
            $invalidFlags['nonclustered'],
        );

        if (count($invalidFlags) > 0) {
            throw InvalidIndexDefinition::fromInvalidFlags($parsedName, array_keys($invalidFlags));
        }

        $invalidOptions = $options;
        unset(
            $invalidOptions['lengths'],
            $invalidOptions['where'],
        );

        if (count($invalidOptions) > 0) {
            throw InvalidIndexDefinition::fromInvalidOptions($parsedName, array_keys($invalidOptions));
        }

        if (isset($flagIndex['clustered']) && isset($flagIndex['nonclustered'])) {
            throw InvalidIndexDefinition::fromNonClusteredClustered($parsedName);
        }

        $editor = Index::editor()
            ->setName($parsedName);

        if ($isUnique) {
            $editor->setType(IndexType::UNIQUE);
        }

        $matches = [];

        if (isset($flagIndex['fulltext'])) {
            $editor->setType(IndexType::FULLTEXT);
            $matches[] = 'fulltext';
        }

        if (isset($flagIndex['spatial'])) {
            $editor->setType(IndexType::SPATIAL);
            $matches[] = 'spatial';
        }

        if (count($matches) > 1) {
            throw InvalidIndexDefinition::fromMutuallyExclusiveFlags($parsedName, $matches);
        }

        if (isset($flagIndex['clustered'])) {
            $editor->setIsClustered(true);
        }

        if (isset($options['where'])) {
            $editor->setPredicate($options['where']);
        }

        foreach ($columns as $columnName) {
            if (! $this->hasColumn($columnName)) {
                throw ColumnDoesNotExist::new($columnName, $this->_name);
            }
        }

        return $editor->setColumns(
            ...$this->parseIndexColumns($columns, $options['lengths'] ?? []),
        )
            ->create();
    }

    /**
     * @param non-empty-array<int, string> $columnNames
     * @param array<int>                   $lengths
     *
     * @return non-empty-list<IndexedColumn>
     */
    private function parseIndexColumns(array $columnNames, array $lengths): array
    {
        $columns = [];

        foreach ($columnNames as $columnName) {
            $parsedName = $this->parseUnqualifiedName($columnName);

            $length = array_shift($lengths);

            if ($length !== null) {
                if (! is_int($length)) {
                    throw InvalidIndexDefinition::fromInvalidColumnLengthType($length);
                }

                if ($length < 1) {
                    throw InvalidIndexDefinition::fromNonPositiveColumnLength($length);
                }
            }

            $columns[] = new IndexedColumn($parsedName, $length);
        }

        return $columns;
    }

    /**
     * Generates a name from a prefix and a list of column names obeying the configured maximum identifier length.
     *
     * @param non-empty-list<UnqualifiedName> $columnNames
     *
     * @return non-empty-string
     */
    private function generateName(string $prefix, array $columnNames): string
    {
        return $this->_generateIdentifierName(
            array_merge([$this->getName()], array_map(static function (UnqualifiedName $columnName): string {
                return $columnName->getIdentifier()->getValue();
            }, $columnNames)),
            $prefix,
            $this->maxIdentifierLength,
        );
    }

    /** @param non-empty-string $newName */
    private function renameColumnInIndexes(string $oldName, string $newName): void
    {
        foreach ($this->_indexes as $key => $index) {
            $modified    = false;
            $columnNames = [];
            foreach ($index->getIndexedColumns() as $indexedColumn) {
                $columnName = $indexedColumn->getColumnName();
                if ($columnName->getIdentifier()->getValue() === $oldName) {
                    $columnNames[] = UnqualifiedName::unquoted($newName);
                    $modified      = true;
                } else {
                    $columnNames[] = $columnName;
                }
            }

            if (! $modified) {
                continue;
            }

            $this->_indexes[$key] = $index->edit()
                ->setColumnNames(...$columnNames)
                ->create();
        }
    }

    /**
     * @param non-empty-string $oldName
     * @param non-empty-string $newName
     */
    private function renameColumnInForeignKeyConstraints(string $oldName, string $newName): void
    {
        foreach ($this->_fkConstraints as $key => $constraint) {
            $modified    = false;
            $columnNames = [];
            foreach ($constraint->getReferencingColumnNames() as $columnName) {
                if ($columnName->getIdentifier()->getValue() === $oldName) {
                    $columnNames[] = UnqualifiedName::unquoted($newName);
                    $modified      = true;
                } else {
                    $columnNames[] = $columnName;
                }
            }

            if (! $modified) {
                continue;
            }

            $this->_fkConstraints[$key] = $constraint->edit()
                ->setReferencingColumnNames(...$columnNames)
                ->create();
        }
    }

    /**
     * @param non-empty-string $oldName
     * @param non-empty-string $newName
     */
    private function renameColumnInUniqueConstraints(string $oldName, string $newName): void
    {
        foreach ($this->uniqueConstraints as $key => $constraint) {
            $modified    = false;
            $columnNames = [];
            foreach ($constraint->getColumnNames() as $columnName) {
                if ($columnName->getIdentifier()->getValue() === $oldName) {
                    $columnNames[] = UnqualifiedName::unquoted($newName);
                    $modified      = true;
                } else {
                    $columnNames[] = $columnName;
                }
            }

            if (! $modified) {
                continue;
            }

            $this->uniqueConstraints[$key] = $constraint->edit()
                ->setColumnNames(...$columnNames)
                ->create();
        }
    }

    /** @return list<string> */
    private function getForeignKeyConstraintNamesByLocalColumnName(string $columnName): array
    {
        $names = [];

        foreach ($this->_fkConstraints as $name => $constraint) {
            foreach ($constraint->getReferencingColumnNames() as $referencingColumnName) {
                if ($referencingColumnName->getIdentifier()->getValue() === $columnName) {
                    $names[] = $name;
                    break;
                }
            }
        }

        return $names;
    }

    /** @return list<string> */
    private function getUniqueConstraintNamesByColumnName(string $columnName): array
    {
        $constraintNames = [];

        foreach ($this->uniqueConstraints as $constraintName => $constraint) {
            foreach ($constraint->getColumnNames() as $constraintColumnName) {
                if ($constraintColumnName->getIdentifier()->getValue() === $columnName) {
                    $constraintNames[] = $constraintName;
                    break;
                }
            }
        }

        return $constraintNames;
    }
}
