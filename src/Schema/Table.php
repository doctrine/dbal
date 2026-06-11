<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Collections\OptionallyUnqualifiedNamedObjectSet;
use Doctrine\DBAL\Schema\Collections\ReadableObjectSet;
use Doctrine\DBAL\Schema\Collections\UnqualifiedNamedObjectSet;
use Doctrine\DBAL\Schema\Collections\UnqualifiedNameSet;
use Doctrine\DBAL\Schema\Exception\ColumnDoesNotExist;
use Doctrine\DBAL\Schema\Exception\ForeignKeyDoesNotExist;
use Doctrine\DBAL\Schema\Exception\IndexDoesNotExist;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\InvalidTableDefinition;
use Doctrine\DBAL\Schema\Exception\UniqueConstraintDoesNotExist;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Override;

use function array_map;
use function array_merge;

/**
 * Object Representation of a table.
 *
 * @implements NamedObject<OptionallyQualifiedName>
 */
final readonly class Table implements NamedObject
{
    /** @var ReadableObjectSet<Column> */
    private ReadableObjectSet $columns;

    /** @var ReadableObjectSet<Index> */
    private ReadableObjectSet $indexes;

    /**
     * The names of the indexes that were implicitly created as backing for foreign key constraints.
     */
    private UnqualifiedNameSet $implicitIndexNames;

    /** @var ReadableObjectSet<UniqueConstraint> */
    private ReadableObjectSet $uniqueConstraints;

    /** @var ReadableObjectSet<ForeignKeyConstraint> */
    private ReadableObjectSet $foreignKeyConstraints;

    /** @var mixed[] */
    private array $options;

    /**
     * @internal Use {@link Table::editor()} to instantiate an editor and {@link TableEditor::create()} to create a
     *           table.
     *
     * @param non-empty-list<Column>     $columns
     * @param list<Index>                $indexes
     * @param list<UnqualifiedName>      $implicitIndexNames
     * @param list<UniqueConstraint>     $uniqueConstraints
     * @param list<ForeignKeyConstraint> $foreignKeyConstraints
     * @param array<string, mixed>       $options
     * @param array<string, string>      $renamedColumns
     */
    public function __construct(
        private OptionallyQualifiedName $name,
        array $columns,
        array $indexes,
        array $implicitIndexNames,
        array $uniqueConstraints,
        array $foreignKeyConstraints,
        array $options,
        private TableConfiguration $configuration,
        private ?PrimaryKeyConstraint $primaryKeyConstraint,
        private array $renamedColumns,
    ) {
        if ($columns === []) {
            throw InvalidTableDefinition::columnsNotSet($name);
        }

        $this->columns               = new UnqualifiedNamedObjectSet(...$columns);
        $this->indexes               = new UnqualifiedNamedObjectSet(...$indexes);
        $this->implicitIndexNames    = new UnqualifiedNameSet(...$implicitIndexNames);
        $this->uniqueConstraints     = new OptionallyUnqualifiedNamedObjectSet(...$uniqueConstraints);
        $this->foreignKeyConstraints = new OptionallyUnqualifiedNamedObjectSet(...$foreignKeyConstraints);

        foreach ($indexes as $index) {
            $this->ensureColumnsExist(...array_map(
                static fn (IndexedColumn $column): UnqualifiedName => $column->getColumnName(),
                $index->getIndexedColumns(),
            ));
        }

        if ($primaryKeyConstraint !== null) {
            $this->ensureColumnsExist(...$primaryKeyConstraint->getColumnNames());
        }

        foreach ($uniqueConstraints as $uniqueConstraint) {
            $this->ensureColumnsExist(...$uniqueConstraint->getColumnNames());
        }

        foreach ($foreignKeyConstraints as $foreignKeyConstraint) {
            $this->ensureColumnsExist(...$foreignKeyConstraint->getReferencingColumnNames());
        }

        $this->options = array_merge(['create_options' => []], $options);
    }

    #[Override]
    public function getObjectName(): OptionallyQualifiedName
    {
        return $this->name;
    }

    /**
     * Asserts that each of the given columns exists in this table.
     *
     * @throws ColumnDoesNotExist if any of the columns is not defined in this table.
     */
    private function ensureColumnsExist(UnqualifiedName ...$columnNames): void
    {
        foreach ($columnNames as $columnName) {
            if ($this->columns->get($columnName) === null) {
                throw ColumnDoesNotExist::new($this->name, $columnName);
            }
        }
    }

    /** @return array<string, string> */
    public function getRenamedColumns(): array
    {
        return $this->renamedColumns;
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
            ->setConfiguration($this->configuration);
    }
}
