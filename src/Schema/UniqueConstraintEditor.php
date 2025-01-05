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

    public function setColumnNames(UnqualifiedName $firstColumName, UnqualifiedName ...$otherColumnNames): self
    {
        $this->columnNames = array_merge([$firstColumName], array_values($otherColumnNames));

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

        return new UniqueConstraint(
            $this->name?->toString() ?? '',
            array_map(static fn (UnqualifiedName $columnName) => $columnName->toString(), $this->columnNames),
            $this->isClustered ? ['clustered'] : [],
        );
    }
}
