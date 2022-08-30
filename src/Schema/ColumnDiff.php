<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\Deprecations\Deprecation;

use function in_array;

/**
 * Represents the change of a column.
 */
class ColumnDiff
{
    /** @param array<string> $changedProperties */
    public function __construct(
        public string $oldColumnName,
        public Column $column,
        public array $changedProperties,
        public Column $fromColumn,
    ) {
    }

    public function hasChanged(string $propertyName): bool
    {
        return in_array($propertyName, $this->changedProperties, true);
    }

    /** @deprecated Use {@see $fromColumn} instead. */
    public function getOldColumnName(): Identifier
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5622',
            '%s is deprecated. Use $fromColumn instead.',
            __METHOD__,
        );

        return new Identifier($this->oldColumnName, $this->fromColumn->isQuoted());
    }
}
