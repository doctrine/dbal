<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

use function array_map;
use function array_merge;
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

    /** @internal Use {@link Index::editor()} or {@link Index::edit()} to create an instance */
    public function __construct()
    {
    }

    public function setName(?UnqualifiedName $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setType(IndexType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setColumns(IndexedColumn $firstColumn, IndexedColumn ...$otherColumns): self
    {
        $this->columns = array_merge([$firstColumn], array_values($otherColumns));

        return $this;
    }

    public function setColumnNames(UnqualifiedName $firstColumnName, UnqualifiedName ...$otherColumnNames): self
    {
        $this->columns = array_map(
            static fn (UnqualifiedName $name) => new IndexedColumn($name, null),
            array_merge([$firstColumnName], array_values($otherColumnNames)),
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

    public function create(): Index
    {
        if ($this->name === null) {
            throw InvalidIndexDefinition::nameNotSet();
        }

        if (count($this->columns) < 1) {
            throw InvalidIndexDefinition::columnsNotSet();
        }

        $columnNames = $lengths = $flags = [];
        foreach ($this->columns as $column) {
            $columnNames[] = $column->getColumnName()->toString();
            $lengths[]     = $column->getLength();
        }

        $options = ['lengths' => $lengths];

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

        return new Index(
            $this->name->toString(),
            $columnNames,
            $this->type === IndexType::UNIQUE,
            false,
            $flags,
            $options,
        );
    }
}
