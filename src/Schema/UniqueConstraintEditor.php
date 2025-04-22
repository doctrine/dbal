<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidUniqueConstraintDefinition;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

use function array_map;
use function array_merge;
use function array_values;
use function count;

final class UniqueConstraintEditor
{
    private ?UnqualifiedName $name = null;

    /** @var list<UnqualifiedName> */
    private array $columnNames = [];

    private bool $isClustered = false;

    /** @internal Use {@link UniqueConstraint::editor()} or {@link UniqueConstraint::edit()} to create an instance */
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

    public function setColumnNames(UnqualifiedName $firstColumnName, UnqualifiedName ...$otherColumnNames): self
    {
        $this->columnNames = array_merge([$firstColumnName], array_values($otherColumnNames));

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
        $this->columnNames = array_map(
            static fn (string $name): UnqualifiedName => UnqualifiedName::unquoted($name),
            array_merge([$firstColumnName], array_values($otherColumnNames)),
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
        $this->columnNames = array_map(
            static fn (string $name): UnqualifiedName => UnqualifiedName::quoted($name),
            array_merge([$firstColumnName], array_values($otherColumnNames)),
        );

        return $this;
    }

    public function setIsClustered(bool $isClustered): self
    {
        $this->isClustered = $isClustered;

        return $this;
    }

    public function create(): UniqueConstraint
    {
        if (count($this->columnNames) < 1) {
            throw InvalidUniqueConstraintDefinition::columnNamesAreNotSet();
        }

        return new UniqueConstraint($this->name, $this->columnNames, $this->isClustered);
    }
}
