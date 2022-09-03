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
     * @internal The diff can be only instantiated by a {@see Comparator}.
     *
     * @param array<string> $changedProperties
     */
    public function __construct(
        public Column $column,
        /**
         * @deprecated Use {@see hasTypeChanged()}, {@see hasLengthChanged()}, {@see hasPrecisionChanged()},
         * {@see hasScaleChanged()}, {@see hasUnsignedChanged()}, {@see hasFixedChanged()}, {@see hasNotNullChanged()},
         * {@see hasDefaultChanged()}, {@see hasAutoIncrementChanged()} or {@see hasCommentChanged()} instead.
         */
        public array $changedProperties,
        public Column $fromColumn,
    ) {
    }

    public function hasTypeChanged(): bool
    {
        return $this->hasChanged('type');
    }

    public function hasLengthChanged(): bool
    {
        return $this->hasChanged('length');
    }

    public function hasPrecisionChanged(): bool
    {
        return $this->hasChanged('precision');
    }

    public function hasScaleChanged(): bool
    {
        return $this->hasChanged('scale');
    }

    public function hasUnsignedChanged(): bool
    {
        return $this->hasChanged('unsigned');
    }

    public function hasFixedChanged(): bool
    {
        return $this->hasChanged('fixed');
    }

    public function hasNotNullChanged(): bool
    {
        return $this->hasChanged('notnull');
    }

    public function hasDefaultChanged(): bool
    {
        return $this->hasChanged('default');
    }

    public function hasAutoIncrementChanged(): bool
    {
        return $this->hasChanged('autoincrement');
    }

    public function hasCommentChanged(): bool
    {
        return $this->hasChanged('comment');
    }

    /**
     * @deprecated Use {@see hasTypeChanged()}, {@see hasLengthChanged()}, {@see hasPrecisionChanged()},
     * {@see hasScaleChanged()}, {@see hasUnsignedChanged()}, {@see hasFixedChanged()}, {@see hasNotNullChanged()},
     * {@see hasDefaultChanged()}, {@see hasAutoIncrementChanged()} or {@see hasCommentChanged()} instead.
     */
    public function hasChanged(string $propertyName): bool
    {
        return in_array($propertyName, $this->changedProperties, true);
    }
}
