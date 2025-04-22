<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidForeignKeyConstraintDefinition;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

use function array_map;
use function array_values;
use function count;

final class ForeignKeyConstraintEditor
{
    private ?UnqualifiedName $name = null;

    /** @var list<UnqualifiedName> */
    private array $referencingColumnNames = [];

    private ?OptionallyQualifiedName $referencedTableName = null;

    /** @var list<UnqualifiedName> */
    private array $referencedColumnNames = [];

    private MatchType $matchType = MatchType::SIMPLE;

    private ReferentialAction $onUpdateAction = ReferentialAction::NO_ACTION;

    private ReferentialAction $onDeleteAction = ReferentialAction::NO_ACTION;

    private Deferrability $deferrability = Deferrability::NOT_DEFERRABLE;

    /**
     * @internal Use {@link ForeignKeyConstraint::editor()} or {@link ForeignKeyConstraint::edit()} to create
     *           an instance.
     */
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

    public function setReferencingColumnNames(
        UnqualifiedName $firstColumnName,
        UnqualifiedName ...$otherColumnNames,
    ): self {
        $this->referencingColumnNames = [$firstColumnName, ...array_values($otherColumnNames)];

        return $this;
    }

    /**
     * @param non-empty-string $firstColumnName
     * @param non-empty-string ...$otherColumnNames
     */
    public function setUnquotedReferencingColumnNames(
        string $firstColumnName,
        string ...$otherColumnNames,
    ): self {
        $this->referencingColumnNames = array_map(
            static fn (string $name): UnqualifiedName => UnqualifiedName::unquoted($name),
            [$firstColumnName, ...array_values($otherColumnNames)],
        );

        return $this;
    }

    /**
     * @param non-empty-string $firstColumnName
     * @param non-empty-string ...$otherColumnNames
     */
    public function setQuotedReferencingColumnNames(
        string $firstColumnName,
        string ...$otherColumnNames,
    ): self {
        $this->referencingColumnNames = array_map(
            static fn (string $name): UnqualifiedName => UnqualifiedName::quoted($name),
            [$firstColumnName, ...array_values($otherColumnNames)],
        );

        return $this;
    }

    public function setReferencedTableName(OptionallyQualifiedName $referencedTableName): self
    {
        $this->referencedTableName = $referencedTableName;

        return $this;
    }

    /**
     * @param non-empty-string  $unqualifiedReferencedTableName
     * @param ?non-empty-string $referencedTableNameQualifier
     */
    public function setUnquotedReferencedTableName(
        string $unqualifiedReferencedTableName,
        ?string $referencedTableNameQualifier = null,
    ): self {
        $this->referencedTableName =
            OptionallyQualifiedName::unquoted($unqualifiedReferencedTableName, $referencedTableNameQualifier);

        return $this;
    }

    /**
     * @param non-empty-string  $unqualifiedReferencedTableName
     * @param ?non-empty-string $referencedTableNameQualifier
     */
    public function setQuotedReferencedTableName(
        string $unqualifiedReferencedTableName,
        ?string $referencedTableNameQualifier = null,
    ): self {
        $this->referencedTableName =
            OptionallyQualifiedName::quoted($unqualifiedReferencedTableName, $referencedTableNameQualifier);

        return $this;
    }

    public function setReferencedColumnNames(
        UnqualifiedName $firstColumnName,
        UnqualifiedName ...$otherColumnNames,
    ): self {
        $this->referencedColumnNames = [$firstColumnName, ...array_values($otherColumnNames)];

        return $this;
    }

    /**
     * @param non-empty-string $firstColumnName
     * @param non-empty-string ...$otherColumnNames
     */
    public function setUnquotedReferencedColumnNames(
        string $firstColumnName,
        string ...$otherColumnNames,
    ): self {
        $this->referencedColumnNames = array_map(
            static fn (string $name): UnqualifiedName => UnqualifiedName::unquoted($name),
            [$firstColumnName, ...array_values($otherColumnNames)],
        );

        return $this;
    }

    /**
     * @param non-empty-string $firstColumnName
     * @param non-empty-string ...$otherColumnNames
     */
    public function setQuotedReferencedColumnNames(
        string $firstColumnName,
        string ...$otherColumnNames,
    ): self {
        $this->referencedColumnNames = array_map(
            static fn (string $name): UnqualifiedName => UnqualifiedName::quoted($name),
            [$firstColumnName, ...array_values($otherColumnNames)],
        );

        return $this;
    }

    public function setMatchType(MatchType $matchType): self
    {
        $this->matchType = $matchType;

        return $this;
    }

    public function setOnUpdateAction(ReferentialAction $action): self
    {
        $this->onUpdateAction = $action;

        return $this;
    }

    public function setOnDeleteAction(ReferentialAction $action): self
    {
        $this->onDeleteAction = $action;

        return $this;
    }

    public function setDeferrability(Deferrability $deferrability): self
    {
        $this->deferrability = $deferrability;

        return $this;
    }

    public function create(): ForeignKeyConstraint
    {
        if (count($this->referencingColumnNames) < 1) {
            throw InvalidForeignKeyConstraintDefinition::referencingColumnNamesNotSet();
        }

        if ($this->referencedTableName === null) {
            throw InvalidForeignKeyConstraintDefinition::referencedTableNameNotSet();
        }

        if (count($this->referencedColumnNames) < 1) {
            throw InvalidForeignKeyConstraintDefinition::referencedColumnNamesNotSet();
        }

        return new ForeignKeyConstraint(
            $this->name,
            $this->referencingColumnNames,
            $this->referencedTableName,
            $this->referencedColumnNames,
            $this->matchType,
            $this->onUpdateAction,
            $this->onDeleteAction,
            $this->deferrability,
        );
    }
}
