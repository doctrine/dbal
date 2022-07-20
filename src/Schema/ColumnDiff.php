<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use function in_array;

/**
 * Represents the change of a column.
 */
class ColumnDiff
{
    /**
     * @param array<string> $changedProperties
     */
    public function __construct(
        public string $oldColumnName,
        public Column $column,
        public array $changedProperties,
        public Column $fromColumn
    ) {
    }

    public function hasChanged(string $propertyName): bool
    {
        return in_array($propertyName, $this->changedProperties, true);
    }

    public function getOldColumnName(): Identifier
    {
        return new Identifier($this->oldColumnName, $this->fromColumn->isQuoted());
    }
}
