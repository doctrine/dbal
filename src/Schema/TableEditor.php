<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidTableDefinition;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;

use function count;

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

    /**
     * @param non-empty-string  $unqualifiedName
     * @param ?non-empty-string $qualifier
     */
    public function setUnquotedName(string $unqualifiedName, ?string $qualifier = null): self
    {
        $this->name = OptionallyQualifiedName::unquoted($unqualifiedName, $qualifier);

        return $this;
    }

    /**
     * @param non-empty-string  $unqualifiedName
     * @param ?non-empty-string $qualifier
     */
    public function setQuotedName(string $unqualifiedName, ?string $qualifier = null): self
    {
        $this->name = OptionallyQualifiedName::quoted($unqualifiedName, $qualifier);

        return $this;
    }

    public function setColumns(Column $firstColumn, Column ...$otherColumns): self
    {
        $this->columns = [$firstColumn, ...$otherColumns];

        return $this;
    }

    public function setIndexes(Index ...$indexes): self
    {
        $this->indexes = $indexes;

        return $this;
    }

    public function setPrimaryKeyConstraint(?PrimaryKeyConstraint $primaryKeyConstraint): self
    {
        $this->primaryKeyConstraint = $primaryKeyConstraint;

        return $this;
    }

    public function setUniqueConstraints(UniqueConstraint ...$uniqueConstraints): self
    {
        $this->uniqueConstraints = $uniqueConstraints;

        return $this;
    }

    public function setForeignKeyConstraints(ForeignKeyConstraint ...$foreignKeyConstraints): self
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

        if (count($this->columns) === 0) {
            throw InvalidTableDefinition::columnsNotSet($this->name);
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
