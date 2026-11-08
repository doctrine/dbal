<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

use function array_map;
use function array_values;
use function count;

final class IndexEditor
{
    private ?UnqualifiedName $name = null;

    private IndexType $type = IndexType::REGULAR;

    /** @var list<IndexedColumn> */
    private array $columns = [];

    private bool $isClustered = false;

    /** @var ?non-empty-string */
    private ?string $predicate = null;

    /** @var ?non-empty-string */
    private ?string $method = null;

    /** @internal Use {@link Index::editor()} or {@link Index::edit()} to create an instance */
    public function __construct()
    {
    }

    public function setName(?UnqualifiedName $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** @param non-empty-string $name */
    public function setUnquotedName(string $name): self
    {
        $this->name = UnqualifiedName::unquoted($name);

        return $this;
    }

    /** @param non-empty-string $name */
    public function setQuotedName(string $name): self
    {
        $this->name = UnqualifiedName::quoted($name);

        return $this;
    }

    public function setType(IndexType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setColumns(IndexedColumn $firstColumn, IndexedColumn ...$otherColumns): self
    {
        $this->columns = [$firstColumn, ...array_values($otherColumns)];

        return $this;
    }

    public function addColumn(IndexedColumn $column): self
    {
        $this->columns[] = $column;

        return $this;
    }

    /**
     * @param non-empty-string     $name
     * @param ?positive-int        $length
     * @param array<string, mixed> $options Platform-specific options of the indexed column
     */
    public function addUnquotedColumnName(string $name, ?int $length = null, array $options = []): self
    {
        return $this->addColumn(new IndexedColumn(UnqualifiedName::unquoted($name), $length, $options));
    }

    /**
     * @param non-empty-string     $name
     * @param ?positive-int        $length
     * @param array<string, mixed> $options Platform-specific options of the indexed column
     */
    public function addQuotedColumnName(string $name, ?int $length = null, array $options = []): self
    {
        return $this->addColumn(new IndexedColumn(UnqualifiedName::quoted($name), $length, $options));
    }

    public function setColumnNames(UnqualifiedName $firstColumnName, UnqualifiedName ...$otherColumnNames): self
    {
        $this->columns = array_map(
            static fn (UnqualifiedName $name) => new IndexedColumn($name, null),
            [$firstColumnName, ...array_values($otherColumnNames)],
        );

        return $this;
    }

    /**
     * @param non-empty-string $firstColumnName
     * @param non-empty-string ...$otherColumnNames
     */
    public function setUnquotedColumnNames(
        string $firstColumnName,
        string ...$otherColumnNames,
    ): self {
        $this->columns = array_map(
            static fn (string $name): IndexedColumn => new IndexedColumn(UnqualifiedName::unquoted($name), null),
            [$firstColumnName, ...array_values($otherColumnNames)],
        );

        return $this;
    }

    /**
     * @param non-empty-string $firstColumnName
     * @param non-empty-string ...$otherColumnNames
     */
    public function setQuotedColumnNames(
        string $firstColumnName,
        string ...$otherColumnNames,
    ): self {
        $this->columns = array_map(
            static fn (string $name): IndexedColumn => new IndexedColumn(UnqualifiedName::quoted($name), null),
            [$firstColumnName, ...array_values($otherColumnNames)],
        );

        return $this;
    }

    public function setIsClustered(bool $isClustered): self
    {
        $this->isClustered = $isClustered;

        return $this;
    }

    /** @param ?non-empty-string $predicate */
    public function setPredicate(?string $predicate): self
    {
        $this->predicate = $predicate;

        return $this;
    }

    /**
     * Sets the index method. The method is passed as is to the platform, which is responsible for validating and
     * rendering it. A null value means that the platform default method will be used.
     *
     * @param ?non-empty-string $method
     */
    public function setMethod(?string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function create(): Index
    {
        if ($this->name === null) {
            throw InvalidIndexDefinition::nameNotSet();
        }

        [$columnNames, $flags, $options] = $this->toIndexParameters($this->name);

        return new Index(
            $this->name->toString(),
            $columnNames,
            $this->type === IndexType::UNIQUE,
            false,
            $flags,
            $options,
        );
    }

    /**
     * Adds the index described by this editor to the given table, generating its name from the table when no name is
     * set.
     *
     * @internal Used by {@link TableEditor} to add an editor-described index to a table.
     */
    public function addToTable(Table $table): void
    {
        [$columnNames, $flags, $options] = $this->toIndexParameters($this->name ?? UnqualifiedName::unquoted('index'));

        $indexName = $this->name?->toString();

        if ($this->type === IndexType::UNIQUE) {
            $table->addUniqueIndex($columnNames, $indexName, $options);

            return;
        }

        $table->addIndex($columnNames, $indexName, $flags, $options);
    }

    /**
     * Maps the editor's columns, flags, and options to the arguments the {@see Index} constructor and the
     * {@see Table} index methods expect.
     *
     * @return array{non-empty-list<string>, list<string>, array<string, mixed>} the column names, flags, and options
     */
    private function toIndexParameters(UnqualifiedName $name): array
    {
        if (count($this->columns) < 1) {
            throw InvalidIndexDefinition::columnsNotSet($name);
        }

        $columnNames      = $lengths = $columnOptions = $options = $flags = [];
        $hasLength        = false;
        $hasColumnOptions = false;

        foreach ($this->columns as $column) {
            $columnNames[] = $column->getColumnName()->toString();

            $length    = $column->getLength();
            $lengths[] = $length;

            if ($length !== null) {
                $hasLength = true;
            }

            $columnOptions[] = $column->getOptions();

            if (count($column->getOptions()) > 0) {
                $hasColumnOptions = true;
            }
        }

        if ($hasLength) {
            $options['lengths'] = $lengths;
        }

        if ($hasColumnOptions) {
            $options['column_options'] = $columnOptions;
        }

        if ($this->type === IndexType::FULLTEXT) {
            $flags[] = 'fulltext';
        } elseif ($this->type === IndexType::SPATIAL) {
            $flags[] = 'spatial';
        }

        if ($this->isClustered) {
            $flags[] = 'clustered';
        }

        if ($this->predicate !== null) {
            $options['where'] = $this->predicate;
        }

        if ($this->method !== null) {
            $options['method'] = $this->method;
        }

        return [$columnNames, $flags, $options];
    }
}
