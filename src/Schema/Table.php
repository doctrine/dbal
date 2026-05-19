<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Collections\Exception\ObjectAlreadyExists;
use Doctrine\DBAL\Schema\Collections\Exception\ObjectDoesNotExist;
use Doctrine\DBAL\Schema\Collections\OptionallyUnqualifiedNamedObjectSet;
use Doctrine\DBAL\Schema\Collections\UnqualifiedNamedObjectSet;
use Doctrine\DBAL\Schema\Collections\UnqualifiedNameSet;
use Doctrine\DBAL\Schema\Exception\ColumnDoesNotExist;
use Doctrine\DBAL\Schema\Exception\ForeignKeyDoesNotExist;
use Doctrine\DBAL\Schema\Exception\IndexAlreadyExists;
use Doctrine\DBAL\Schema\Exception\IndexDoesNotExist;
use Doctrine\DBAL\Schema\Exception\InvalidForeignKeyConstraintDefinition;
use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\InvalidTableModification;
use Doctrine\DBAL\Schema\Exception\PrimaryKeyAlreadyExists;
use Doctrine\DBAL\Schema\Exception\UniqueConstraintDoesNotExist;
use Doctrine\DBAL\Schema\Exception\UnknownColumnOption;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\Deprecations\Deprecation;
use LogicException;

use function array_keys;
use function array_map;
use function array_merge;
use function array_shift;
use function count;
use function crc32;
use function dechex;
use function implode;
use function in_array;
use function is_int;
use function sprintf;
use function strtolower;
use function strtoupper;
use function substr;

/**
 * Object Representation of a table.
 *
 * @extends AbstractNamedObject<OptionallyQualifiedName>
 */
final class Table extends AbstractNamedObject
{
    /** @var UnqualifiedNamedObjectSet<Column> */
    private UnqualifiedNamedObjectSet $columns;

    /** @var array<string, string> keys are new names, values are old names */
    private array $renamedColumns = [];

    /** @var UnqualifiedNamedObjectSet<Index> */
    private UnqualifiedNamedObjectSet $indexes;

    /**
     * The names of the indexes that were implicitly created as backing for foreign key constraints.
     */
    private readonly UnqualifiedNameSet $implicitIndexNames;

    /** @var OptionallyUnqualifiedNamedObjectSet<UniqueConstraint> */
    private OptionallyUnqualifiedNamedObjectSet $uniqueConstraints;

    /** @var OptionallyUnqualifiedNamedObjectSet<ForeignKeyConstraint> */
    private OptionallyUnqualifiedNamedObjectSet $foreignKeyConstraints;

    /** @var mixed[] */
    private array $options = [
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
        $parser = Parsers::getOptionallyQualifiedNameParser();

        try {
            $parsedName = $parser->parse($name);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($name, $e);
        }

        parent::__construct($parsedName);

        $configuration ??= (new SchemaConfig())->toTableConfiguration();

        $this->maxIdentifierLength = $configuration->getMaxIdentifierLength();

        /** @var UnqualifiedNamedObjectSet<Column> $columnsSet */
        $columnsSet    = new UnqualifiedNamedObjectSet(...$columns);
        $this->columns = $columnsSet;

        /** @var UnqualifiedNamedObjectSet<Index> $indexSet */
        $indexSet      = new UnqualifiedNamedObjectSet();
        $this->indexes = $indexSet;

        /** @var OptionallyUnqualifiedNamedObjectSet<UniqueConstraint> $uniqueConstraintSet */
        $uniqueConstraintSet     = new OptionallyUnqualifiedNamedObjectSet();
        $this->uniqueConstraints = $uniqueConstraintSet;

        /** @var OptionallyUnqualifiedNamedObjectSet<ForeignKeyConstraint> $foreignKeyConstraints */
        $foreignKeyConstraints       = new OptionallyUnqualifiedNamedObjectSet();
        $this->foreignKeyConstraints = $foreignKeyConstraints;

        $this->implicitIndexNames = new UnqualifiedNameSet();

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

        $this->options = array_merge($this->options, $options);
    }

    public function addPrimaryKeyConstraint(PrimaryKeyConstraint $primaryKeyConstraint): self
    {
        if ($this->primaryKeyConstraint !== null) {
            throw PrimaryKeyAlreadyExists::new($this->name);
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
        $indexName ??= $this->generateNameFromStringColumnNames('uniq', $columnNames);

        $isClustered = in_array('clustered', $flags, true);

        return $this->_addUniqueConstraint($this->createUniqueConstraint($columnNames, $indexName, $isClustered));
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
        $indexName ??= $this->generateNameFromStringColumnNames('idx', $columnNames);

        return $this->_addIndex($this->createIndex($columnNames, $indexName, false, $flags, $options));
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
        $parsedName = $this->parseUnqualifiedName($name);

        try {
            $this->indexes->remove($parsedName);
        } catch (ObjectDoesNotExist $e) {
            throw InvalidTableModification::indexDoesNotExist($this->name, $e);
        }
    }

    /**
     * @param non-empty-list<string> $columnNames
     * @param array<string, mixed>   $options
     */
    public function addUniqueIndex(array $columnNames, ?string $indexName = null, array $options = []): self
    {
        $indexName ??= $this->generateNameFromStringColumnNames('uniq', $columnNames);

        return $this->_addIndex($this->createIndex($columnNames, $indexName, true, [], $options));
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
        $parsedOldName = $this->parseUnqualifiedName($oldName);

        $index = $this->indexes->get($parsedOldName);

        if ($index === null) {
            throw IndexDoesNotExist::new($this->name, $parsedOldName);
        }

        if ($newName !== null) {
            $parsedNewName = $this->parseUnqualifiedName($newName);

            if ($this->getObjectKey($parsedOldName) === $this->getObjectKey($parsedNewName)) {
                return $this;
            }
        } else {
            $parsedNewName = UnqualifiedName::unquoted(
                $this->generateNameFromObjectColumnNames(
                    $index->getType() === IndexType::UNIQUE ? 'uniq' : 'idx',
                    array_map(
                        static fn (IndexedColumn $indexedColumn): UnqualifiedName => $indexedColumn->getColumnName(),
                        $index->getIndexedColumns(),
                    ),
                ),
            );
        }

        $index = $index->edit()
            ->setName($parsedNewName)
            ->create();

        $this->_addIndex($index);

        $this->indexes->remove($parsedOldName);

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws TypesException
     */
    public function addColumn(string $name, string $typeName, array $options = []): Column
    {
        $parsedName = $this->parseUnqualifiedName($name);

        $editor = Column::editor()
            ->setName($parsedName)
            ->setTypeName($typeName);

        $this->applyColumnOptionsToEditor($editor, $options);

        $column = $editor->create();

        $this->_addColumn($column);

        return $column;
    }

    /** @return array<string, string> */
    public function getRenamedColumns(): array
    {
        return $this->renamedColumns;
    }

    /**
     * @param non-empty-string $oldName
     * @param non-empty-string $newName
     *
     * @throws LogicException
     */
    public function renameColumn(string $oldName, string $newName): Column
    {
        $parsedOldName = $this->parseUnqualifiedName($oldName);
        $parsedNewName = $this->parseUnqualifiedName($newName);

        $oldKey = $this->getObjectKey($parsedOldName);
        $newKey = $this->getObjectKey($parsedNewName);

        if ($newKey === $oldKey) {
            throw new LogicException(sprintf(
                'Attempt to rename column "%s.%s" to the same name.',
                $this->name->toString(),
                $oldName,
            ));
        }

        $oldColumn = $this->getColumn($oldName);
        $newColumn = $oldColumn->edit()
            ->setName($parsedNewName)
            ->create();

        $this->columns->remove($parsedOldName);
        $this->_addColumn($newColumn);

        $this->renameColumnInIndexes($oldKey, $parsedNewName);
        $this->renameColumnInForeignKeyConstraints($oldKey, $parsedNewName);
        $this->renameColumnInUniqueConstraints($oldKey, $parsedNewName);

        // If a column is renamed multiple times, we only want to know the original and last new name
        if (isset($this->renamedColumns[$oldKey])) {
            $keyToRemove = $oldKey;
            $oldKey      = $this->renamedColumns[$oldKey];
            unset($this->renamedColumns[$keyToRemove]);
        }

        if ($newKey !== $oldKey) {
            $this->renamedColumns[$newKey] = $oldKey;
        }

        return $newColumn;
    }

    /** @param array<string, mixed> $options */
    public function modifyColumn(string $name, array $options): self
    {
        $oldColumn = $this->getColumn($name);

        $editor = $oldColumn->edit();
        $this->applyColumnOptionsToEditor($editor, $options);
        $newColumn = $editor->create();

        $this->columns->modify(
            $oldColumn->getObjectName(),
            static fn (): Column => $newColumn,
        );

        return $this;
    }

    /** @param array<string, mixed> $options */
    private function applyColumnOptionsToEditor(ColumnEditor $editor, array $options): void
    {
        foreach ($options as $name => $value) {
            match ($name) {
                'type'             => $editor->setType($value),
                'length'           => $editor->setLength($value),
                'precision'        => $editor->setPrecision($value),
                'scale'            => $editor->setScale($value),
                'unsigned'         => $editor->setUnsigned($value),
                'fixed'            => $editor->setFixed($value),
                'notnull'          => $editor->setNotNull($value),
                'default'          => $editor->setDefaultValue($value),
                'autoincrement'    => $editor->setAutoincrement($value),
                'values'           => $editor->setValues($value),
                'comment'          => $editor->setComment($value),
                'columnDefinition' => $editor->setColumnDefinition($value),
                'platformOptions'  => $this->applyPlatformOptionsToEditor($editor, $value),
                default            => throw UnknownColumnOption::new($name),
            };
        }
    }

    /** @param array<string, mixed> $platformOptions */
    private function applyPlatformOptionsToEditor(ColumnEditor $editor, array $platformOptions): void
    {
        foreach ($platformOptions as $name => $value) {
            match ($name) {
                'charset'   => $editor->setCharset($value),
                'collation' => $editor->setCollation($value),
                'min'       => $editor->setMinimumValue($value),
                'max'       => $editor->setMaximumValue($value),
                'enumType'  => $editor->setEnumType($value),
                SQLServerPlatform::OPTION_DEFAULT_CONSTRAINT_NAME
                            => $editor->setDefaultConstraintName($value),
                default     => throw UnknownColumnOption::new($name),
            };
        }
    }

    /**
     * Drops a Column from the Table.
     */
    public function dropColumn(string $name): self
    {
        $parsedName = $this->parseUnqualifiedName($name);

        $foreignKeyConstraintNames = $this->getForeignKeyConstraintNamesByLocalColumnName($parsedName);
        $uniqueConstraintNames     = $this->getUniqueConstraintNamesByColumnName($parsedName);

        if (count($foreignKeyConstraintNames) > 0 || count($uniqueConstraintNames) > 0) {
            $constraints = [];

            if (count($foreignKeyConstraintNames) > 0) {
                $constraints[] = 'foreign key constraints: '
                    . $this->unqualifiedNameListsToString($foreignKeyConstraintNames);
            }

            if (count($uniqueConstraintNames) > 0) {
                $constraints[] = 'unique constraints: ' . $this->unqualifiedNameListsToString($uniqueConstraintNames);
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

        $this->columns->remove($parsedName);

        return $this;
    }

    /** @param non-empty-list<?UnqualifiedName> $names */
    private function unqualifiedNameListsToString(array $names): string
    {
        return implode(', ', array_map(static function (?UnqualifiedName $name): string {
            if ($name !== null) {
                return $name->toString();
            }

            return '<unnamed>';
        }, $names));
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
        $referencingColumnNames = $this->parseUnqualifiedNames($referencingColumnNames);

        foreach ($referencingColumnNames as $columnName) {
            if (! $this->hasColumn($columnName->toString())) {
                throw ColumnDoesNotExist::new($this->name, $columnName);
            }
        }

        $referencedTableName   = $this->parseOptionallyQualifiedName($referencedTableName);
        $referencedColumnNames = $this->parseUnqualifiedNames($referencedColumnNames);

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
                $this->generateNameFromObjectColumnNames('fk', $referencingColumnNames),
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
        $this->options[$name] = $value;

        return $this;
    }

    /**
     * Returns whether this table has a foreign key constraint with the given name.
     */
    public function hasForeignKey(string $name): bool
    {
        $parsedName = $this->parseUnqualifiedName($name);

        return $this->foreignKeyConstraints->get($parsedName) !== null;
    }

    /**
     * Returns the foreign key constraint with the given name.
     */
    public function getForeignKey(string $name): ForeignKeyConstraint
    {
        $parsedName = $this->parseUnqualifiedName($name);

        $foreignKeyConstraint = $this->foreignKeyConstraints->get($parsedName);

        if ($foreignKeyConstraint === null) {
            throw ForeignKeyDoesNotExist::new($this->name, $parsedName);
        }

        return $foreignKeyConstraint;
    }

    /**
     * Drops the foreign key constraint with the given name.
     */
    public function dropForeignKey(string $name): void
    {
        $parsedName = $this->parseUnqualifiedName($name);

        try {
            $this->foreignKeyConstraints->remove($parsedName);
        } catch (ObjectDoesNotExist $e) {
            throw InvalidTableModification::foreignKeyConstraintDoesNotExist($this->name, $e);
        }
    }

    /**
     * Returns whether this table has a unique constraint with the given name.
     */
    public function hasUniqueConstraint(string $name): bool
    {
        $parsedName = $this->parseUnqualifiedName($name);

        return $this->uniqueConstraints->get($parsedName) !== null;
    }

    /**
     * Returns the unique constraint with the given name.
     */
    public function getUniqueConstraint(string $name): UniqueConstraint
    {
        $parsedName = $this->parseUnqualifiedName($name);

        $uniqueConstraint = $this->uniqueConstraints->get($parsedName);

        if ($uniqueConstraint === null) {
            throw UniqueConstraintDoesNotExist::new($this->name, $parsedName);
        }

        return $uniqueConstraint;
    }

    /**
     * Drops the unique constraint with the given name.
     */
    public function dropUniqueConstraint(string $name): void
    {
        $parsedName = $this->parseUnqualifiedName($name);

        try {
            $this->uniqueConstraints->remove($parsedName);
        } catch (ObjectDoesNotExist $e) {
            throw InvalidTableModification::uniqueConstraintDoesNotExist($this->name, $e);
        }
    }

    /**
     * Returns the list of table columns.
     *
     * @return non-empty-list<Column>
     */
    public function getColumns(): array
    {
        /** @phpstan-ignore return.type */
        return $this->columns->toList();
    }

    /**
     * Returns whether this table has a Column with the given name.
     */
    public function hasColumn(string $name): bool
    {
        $parsedName = $this->parseUnqualifiedName($name);

        return $this->columns->get($parsedName) !== null;
    }

    /**
     * Returns the Column with the given name.
     */
    public function getColumn(string $name): Column
    {
        $parsedName = $this->parseUnqualifiedName($name);

        $column = $this->columns->get($parsedName);

        if ($column === null) {
            throw ColumnDoesNotExist::new($this->name, $parsedName);
        }

        return $column;
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
        $parsedName = $this->parseUnqualifiedName($name);

        return $this->indexes->get($parsedName) !== null;
    }

    /**
     * Returns the Index with the given name.
     */
    public function getIndex(string $name): Index
    {
        $parsedName = $this->parseUnqualifiedName($name);

        $index = $this->indexes->get($parsedName);

        if ($index === null) {
            throw IndexDoesNotExist::new($this->name, $parsedName);
        }

        return $index;
    }

    /** @return list<Index> */
    public function getIndexes(): array
    {
        return $this->indexes->toList();
    }

    /**
     * Returns the unique constraints.
     *
     * @return list<UniqueConstraint>
     */
    public function getUniqueConstraints(): array
    {
        return $this->uniqueConstraints->toList();
    }

    /**
     * Returns the foreign key constraints.
     *
     * @return list<ForeignKeyConstraint>
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeyConstraints->toList();
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /** @return array<string, mixed> */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Clone of a Table triggers a deep clone of all affected assets.
     */
    public function __clone()
    {
        $this->columns               = clone $this->columns;
        $this->indexes               = clone $this->indexes;
        $this->uniqueConstraints     = clone $this->uniqueConstraints;
        $this->foreignKeyConstraints = clone $this->foreignKeyConstraints;
    }

    private function _addColumn(Column $column): void
    {
        try {
            $this->columns->add($column);
        } catch (ObjectAlreadyExists $e) {
            throw InvalidTableModification::columnAlreadyExists($this->name, $e);
        }
    }

    /**
     * Adds an index to the table.
     */
    private function _addIndex(Index $index): self
    {
        $indexName = $index->getObjectName();

        $replacedImplicitIndexNames = new UnqualifiedNameSet();

        foreach ($this->implicitIndexNames as $implicitIndexName) {
            $candidate = $this->indexes->get($implicitIndexName);

            if ($candidate === null) {
                continue;
            }

            if (! $candidate->isFulfilledBy($index)) {
                continue;
            }

            $replacedImplicitIndexNames->add($implicitIndexName);
        }

        if ($this->indexes->get($indexName) !== null && ! $replacedImplicitIndexNames->contains($indexName)) {
            throw IndexAlreadyExists::new($this->name, $indexName);
        }

        foreach ($replacedImplicitIndexNames as $replacedImplicitIndexName) {
            $this->indexes->remove($replacedImplicitIndexName);
            $this->implicitIndexNames->remove($replacedImplicitIndexName);
        }

        $this->indexes->add($index);

        return $this;
    }

    private function _addUniqueConstraint(UniqueConstraint $constraint): self
    {
        try {
            $this->uniqueConstraints->add($constraint);
        } catch (ObjectAlreadyExists $e) {
            throw InvalidTableModification::uniqueConstraintAlreadyExists($this->name, $e);
        }

        $columnNames = $constraint->getColumnNames();

        // If there is already an index that fulfills this requirements drop the request. In the case of __construct
        // calling this method during hydration from schema-details all the explicitly added indexes lead to duplicates.
        // This creates computation overhead in this case, however no duplicate indexes are ever added (column based).
        $indexName = UnqualifiedName::unquoted(
            $this->generateNameFromObjectColumnNames('idx', $columnNames),
        );

        $indexCandidate = Index::editor()
            ->setName($indexName)
            ->setType(IndexType::UNIQUE)
            ->setColumnNames(...$columnNames)
            ->create();

        foreach ($this->indexes as $existingIndex) {
            if ($indexCandidate->isFulfilledBy($existingIndex)) {
                return $this;
            }
        }

        $this->implicitIndexNames->add($indexName);

        return $this;
    }

    private function _addForeignKeyConstraint(ForeignKeyConstraint $constraint): self
    {
        try {
            $this->foreignKeyConstraints->add($constraint);
        } catch (ObjectAlreadyExists $e) {
            throw InvalidTableModification::foreignKeyConstraintAlreadyExists($this->name, $e);
        }

        $columnNames = $constraint->getReferencingColumnNames();

        // add an explicit index on the foreign key columns.
        // If there is already an index that fulfills this requirements drop the request. In the case of __construct
        // calling this method during hydration from schema-details all the explicitly added indexes lead to duplicates.
        // This creates computation overhead in this case, however no duplicate indexes are ever added (column based).
        $indexName = UnqualifiedName::unquoted(
            $this->generateNameFromObjectColumnNames('idx', $columnNames),
        );

        $indexCandidate = Index::editor()
            ->setName($indexName)
            ->setColumnNames(...$columnNames)
            ->create();

        foreach ($this->indexes as $existingIndex) {
            if ($indexCandidate->isFulfilledBy($existingIndex)) {
                return $this;
            }
        }

        $this->_addIndex($indexCandidate);
        $this->implicitIndexNames->add($indexName);

        return $this;
    }

    /**
     * Returns the key to be used to represent an object with the given name in a collection of objects.
     *
     * @return non-empty-string
     */
    private function getObjectKey(UnqualifiedName $name): string
    {
        return strtolower($name->getIdentifier()->getValue());
    }

    public function setComment(string $comment): self
    {
        // For keeping backward compatibility with MySQL in previous releases, table comments are stored as options.
        $this->addOption('comment', $comment);

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->options['comment'] ?? null;
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
        $explicitIndexes = [];

        foreach ($this->indexes as $index) {
            if ($this->implicitIndexNames->contains($index->getObjectName())) {
                continue;
            }

            $explicitIndexes[] = $index;
        }

        $editor = self::editor()
            ->setName($this->getObjectName())
            ->setColumns(...$this->columns->toList())
            ->setIndexes(...$explicitIndexes)
            ->setPrimaryKeyConstraint($this->primaryKeyConstraint)
            ->setUniqueConstraints(...$this->uniqueConstraints->toList())
            ->setForeignKeyConstraints(...$this->foreignKeyConstraints->toList());

        $options = $this->options;

        if (isset($options['comment'])) {
            $editor->setComment($options['comment']);
            unset($options['comment']);
        }

        return $editor
            ->setOptions($options)
            ->setConfiguration(
                new TableConfiguration($this->maxIdentifierLength),
            );
    }

    /** @param non-empty-list<string> $columns */
    private function createUniqueConstraint(
        array $columns,
        string $indexName,
        bool $isClustered,
    ): UniqueConstraint {
        $constraintName = $this->parseUnqualifiedName($indexName);
        $columnNames    = $this->parseUnqualifiedNames($columns);

        foreach ($columnNames as $columnName) {
            if (! $this->hasColumn($columnName->toString())) {
                throw ColumnDoesNotExist::new($this->name, $columnName);
            }
        }

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
    private function createIndex(
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

        $indexedColumns = $this->parseIndexColumns($columns, $options['lengths'] ?? []);

        foreach ($indexedColumns as $indexedColumn) {
            $columnName = $indexedColumn->getColumnName();

            if (! $this->hasColumn($columnName->toString())) {
                throw ColumnDoesNotExist::new($this->name, $columnName);
            }
        }

        return $editor->setColumns(...$indexedColumns)
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
                    throw InvalidIndexDefinition::fromInvalidColumnLengthType($parsedName, $length);
                }

                if ($length < 1) {
                    throw InvalidIndexDefinition::fromNonPositiveColumnLength($parsedName, $length);
                }
            }

            $columns[] = new IndexedColumn($parsedName, $length);
        }

        return $columns;
    }

    /**
     * Generates a name from a prefix and a list of column names represented as objects obeying the configured maximum
     * identifier length.
     *
     * @param non-empty-list<UnqualifiedName> $columnNames
     *
     * @return non-empty-string
     */
    private function generateNameFromObjectColumnNames(string $prefix, array $columnNames): string
    {
        return $this->generateNameFromStringColumnNames(
            $prefix,
            array_map(static function (UnqualifiedName $columnName): string {
                return $columnName->getIdentifier()->getValue();
            }, $columnNames),
        );
    }

    /**
     * Generates a name from a prefix and a list of column names represented as strings obeying the configured maximum
     * identifier length.
     *
     * @param array<int, string> $columnNames
     *
     * @return non-empty-string
     */
    private function generateNameFromStringColumnNames(string $prefix, array $columnNames): string
    {
        $hash = implode('', array_map(static function (string $columnName): string {
            return dechex(crc32($columnName));
        }, array_merge([
            $this->getObjectName()
                ->getUnqualifiedName()
                ->getValue(),
        ], $columnNames)));

        return strtoupper(substr($prefix . '_' . $hash, 0, $this->maxIdentifierLength));
    }

    private function renameColumnInIndexes(string $oldKey, UnqualifiedName $newName): void
    {
        foreach ($this->indexes as $index) {
            $modified    = false;
            $columnNames = [];
            foreach ($index->getIndexedColumns() as $indexedColumn) {
                $columnName = $indexedColumn->getColumnName();
                if ($this->getObjectKey($columnName) === $oldKey) {
                    $columnNames[] = $newName;
                    $modified      = true;
                } else {
                    $columnNames[] = $columnName;
                }
            }

            if (! $modified) {
                continue;
            }

            $this->indexes->modify($index->getObjectName(), static function (Index $index) use ($columnNames): Index {
                return $index->edit()
                    ->setColumnNames(...$columnNames)
                    ->create();
            });
        }
    }

    private function renameColumnInForeignKeyConstraints(string $oldKey, UnqualifiedName $newName): void
    {
        foreach ($this->foreignKeyConstraints as $position => $constraint) {
            $modified    = false;
            $columnNames = [];
            foreach ($constraint->getReferencingColumnNames() as $columnName) {
                if ($this->getObjectKey($columnName) === $oldKey) {
                    $columnNames[] = $newName;
                    $modified      = true;
                } else {
                    $columnNames[] = $columnName;
                }
            }

            if (! $modified) {
                continue;
            }

            $this->foreignKeyConstraints->modifyByPosition(
                $position,
                static fn (ForeignKeyConstraint $constraint): ForeignKeyConstraint => $constraint->edit()
                    ->setReferencingColumnNames(...$columnNames)
                    ->create(),
            );
        }
    }

    private function renameColumnInUniqueConstraints(string $oldKey, UnqualifiedName $newName): void
    {
        foreach ($this->uniqueConstraints->toList() as $position => $constraint) {
            $modified    = false;
            $columnNames = [];
            foreach ($constraint->getColumnNames() as $columnName) {
                if ($this->getObjectKey($columnName) === $oldKey) {
                    $columnNames[] = $newName;
                    $modified      = true;
                } else {
                    $columnNames[] = $columnName;
                }
            }

            if (! $modified) {
                continue;
            }

            $this->uniqueConstraints->modifyByPosition(
                $position,
                static fn (UniqueConstraint $constraint): UniqueConstraint => $constraint->edit()
                    ->setColumnNames(...$columnNames)
                    ->create(),
            );
        }
    }

    /** @return list<?UnqualifiedName> */
    private function getForeignKeyConstraintNamesByLocalColumnName(UnqualifiedName $columnName): array
    {
        $columnKey = $this->getObjectKey($columnName);

        $names = [];

        foreach ($this->foreignKeyConstraints as $constraint) {
            foreach ($constraint->getReferencingColumnNames() as $referencingColumnName) {
                if ($this->getObjectKey($referencingColumnName) === $columnKey) {
                    $names[] = $constraint->getObjectName();
                    break;
                }
            }
        }

        return $names;
    }

    /** @return list<?UnqualifiedName> */
    private function getUniqueConstraintNamesByColumnName(UnqualifiedName $columnName): array
    {
        $columnKey = $this->getObjectKey($columnName);

        $constraintNames = [];

        foreach ($this->uniqueConstraints->toList() as $constraint) {
            foreach ($constraint->getColumnNames() as $constraintColumnName) {
                if ($this->getObjectKey($constraintColumnName) === $columnKey) {
                    $constraintNames[] = $constraint->getObjectName();
                    break;
                }
            }
        }

        return $constraintNames;
    }
}
