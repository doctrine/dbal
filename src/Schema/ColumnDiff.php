<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use function in_array;

/**
 * Represents the change of a column.
 */
class ColumnDiff
{
    /** @var string */
    public $oldColumnName;

    /** @var Column */
    public $column;

    /** @var array<int, string> */
    public $changedProperties = [];

    /** @var Column|null */
    public $fromColumn;

    /**
     * @param array<string> $changedProperties
     */
    public function __construct(string $oldColumnName, Column $column, array $changedProperties = [], ?Column $fromColumn = null)
    {
        $this->oldColumnName     = $oldColumnName;
        $this->column            = $column;
        $this->changedProperties = $changedProperties;
        $this->fromColumn        = $fromColumn;
    }

    public function hasChanged(string $propertyName): bool
    {
        return in_array($propertyName, $this->changedProperties, true);
    }

    public function getOldColumnName(): Identifier
    {
        $quote = $this->fromColumn !== null && $this->fromColumn->isQuoted();

        return new Identifier($this->oldColumnName, $quote);
    }
}
