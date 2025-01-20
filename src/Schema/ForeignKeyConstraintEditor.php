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
use function array_merge;
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

    public function setReferencingColumnNames(
        UnqualifiedName $firstColumName,
        UnqualifiedName ...$otherColumnNames,
    ): self {
        $this->referencingColumnNames = array_merge([$firstColumName], array_values($otherColumnNames));

        return $this;
    }

    public function setReferencedTableName(OptionallyQualifiedName $referencedTableName): self
    {
        $this->referencedTableName = $referencedTableName;

        return $this;
    }

    public function setReferencedColumnNames(
        UnqualifiedName $firstColumName,
        UnqualifiedName ...$otherColumnNames,
    ): self {
        $this->referencedColumnNames = array_merge([$firstColumName], array_values($otherColumnNames));

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
            array_map(
                static fn (UnqualifiedName $columnName) => $columnName->toString(),
                $this->referencingColumnNames,
            ),
            $this->referencedTableName->toString(),
            array_map(
                static fn (UnqualifiedName $columnName) => $columnName->toString(),
                $this->referencedColumnNames,
            ),
            $this->name?->toString() ?? '',
            array_merge([
                'match' => $this->matchType->value,
                'onUpdate' => $this->onUpdateAction->value,
                'onDelete' => $this->onDeleteAction->value,
            ], match ($this->deferrability) {
                Deferrability::NOT_DEFERRABLE => [],
                Deferrability::DEFERRABLE => ['deferrable' => true],
                Deferrability::DEFERRED => ['deferrable' => true, 'deferred' => true],
            }),
        );
    }
}
