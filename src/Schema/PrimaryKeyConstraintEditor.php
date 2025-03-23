<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidPrimaryKeyConstraintDefinition;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

use function array_merge;
use function array_values;
use function count;

final class PrimaryKeyConstraintEditor
{
    private ?UnqualifiedName $name = null;

    /** @var list<UnqualifiedName> */
    private array $columnNames = [];

    private bool $isClustered = true;

    /**
     * @internal Use {@link PrimaryKeyConstraint::editor()} or {@link PrimaryKeyConstraint::edit()} to create
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

    public function create(): PrimaryKeyConstraint
    {
        if (count($this->columnNames) < 1) {
            throw InvalidPrimaryKeyConstraintDefinition::columnNamesNotSet();
        }

        return new PrimaryKeyConstraint($this->name, $this->columnNames, $this->isClustered);
    }
}
