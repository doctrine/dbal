<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidTableDefinition;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;

use function array_filter;

final class TableEditor
{
    private ?OptionallyQualifiedName $name = null;

    /** @var array<Column> */
    private array $columns = [];

    /** @var array<Index> */
    private array $indexes = [];

    private ?PrimaryKeyConstraint $primaryKeyConstraint = null;

    /** @var array<UniqueConstraint> */
    private array $uniqueConstraints = [];

    /** @var array<ForeignKeyConstraint> */
    private array $foreignKeyConstraints = [];

    /** @var array<string, mixed> */
    private array $options = [];

    private ?TableConfiguration $configuration = null;

    /** @internal Use {@link Table::editor()} or {@link Table::edit()} to create an instance */
    public function __construct()
    {
    }

    public function setName(OptionallyQualifiedName $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** @param array<Column> $columns */
    public function setColumns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /** @param array<Index> $indexes */
    public function setIndexes(array $indexes): self
    {
        $this->indexes = $indexes;

        return $this;
    }

    public function setPrimaryKeyConstraint(?PrimaryKeyConstraint $primaryKeyConstraint): self
    {
        $this->primaryKeyConstraint = $primaryKeyConstraint;

        $this->indexes = array_filter(
            $this->indexes,
            static fn (Index $index): bool => ! $index->isPrimary(),
        );

        return $this;
    }

    /** @param array<UniqueConstraint> $uniqueConstraints */
    public function setUniqueConstraints(array $uniqueConstraints): self
    {
        $this->uniqueConstraints = $uniqueConstraints;

        return $this;
    }

    /** @param array<ForeignKeyConstraint> $foreignKeyConstraints */
    public function setForeignKeyConstraints(array $foreignKeyConstraints): self
    {
        $this->foreignKeyConstraints = $foreignKeyConstraints;

        return $this;
    }

    /** @param array<string, mixed> $options */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function setConfiguration(TableConfiguration $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function create(): Table
    {
        if ($this->name === null) {
            throw InvalidTableDefinition::nameNotSet();
        }

        return new Table(
            $this->name->toString(),
            $this->columns,
            $this->indexes,
            $this->uniqueConstraints,
            $this->foreignKeyConstraints,
            $this->options,
            $this->configuration,
            $this->primaryKeyConstraint,
        );
    }
}
