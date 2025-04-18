<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use function strcasecmp;

/**
 * Represents the change of a column.
 *
 * @final
 */
class ColumnDiff
{
    /** @internal The diff can be only instantiated by a {@see Comparator}. */
    public function __construct(private readonly Column $oldColumn, private readonly Column $newColumn)
    {
    }

    public function countChangedProperties(): int
    {
        return (int) $this->hasUnsignedChanged()
            + (int) $this->hasAutoIncrementChanged()
            + (int) $this->hasDefaultChanged()
            + (int) $this->hasFixedChanged()
            + (int) $this->hasPrecisionChanged()
            + (int) $this->hasScaleChanged()
            + (int) $this->hasLengthChanged()
            + (int) $this->hasNotNullChanged()
            + (int) $this->hasNameChanged()
            + (int) $this->hasTypeChanged()
            + (int) $this->hasPlatformOptionsChanged()
            + (int) $this->hasCommentChanged();
    }

    public function getOldColumn(): Column
    {
        return $this->oldColumn;
    }

    public function getNewColumn(): Column
    {
        return $this->newColumn;
    }

    public function hasNameChanged(): bool
    {
        $oldColumn = $this->getOldColumn();

        // Column names are case insensitive
        return strcasecmp($oldColumn->getName(), $this->getNewColumn()->getName()) !== 0;
    }

    public function hasTypeChanged(): bool
    {
        return $this->newColumn->getType()::class !== $this->oldColumn->getType()::class;
    }

    public function hasLengthChanged(): bool
    {
        return $this->hasPropertyChanged(static function (Column $column): ?int {
            return $column->getLength();
        });
    }

    public function hasPrecisionChanged(): bool
    {
        return $this->hasPropertyChanged(static function (Column $column): ?int {
            return $column->getPrecision();
        });
    }

    public function hasScaleChanged(): bool
    {
        return $this->hasPropertyChanged(static function (Column $column): int {
            return $column->getScale();
        });
    }

    public function hasUnsignedChanged(): bool
    {
        return $this->hasPropertyChanged(static function (Column $column): bool {
            return $column->getUnsigned();
        });
    }

    public function hasFixedChanged(): bool
    {
        return $this->hasPropertyChanged(static function (Column $column): bool {
            return $column->getFixed();
        });
    }

    public function hasNotNullChanged(): bool
    {
        return $this->hasPropertyChanged(static function (Column $column): bool {
            return $column->getNotnull();
        });
    }

    public function hasDefaultChanged(): bool
    {
        $oldDefault = $this->oldColumn->getDefault();
        $newDefault = $this->newColumn->getDefault();

        // Null values need to be checked additionally as they tell whether to create or drop a default value.
        // null != 0, null != false, null != '' etc. This affects platform's table alteration SQL generation.
        if (($newDefault === null) xor ($oldDefault === null)) {
            return true;
        }

        return $newDefault != $oldDefault;
    }

    public function hasAutoIncrementChanged(): bool
    {
        return $this->hasPropertyChanged(static function (Column $column): bool {
            return $column->getAutoincrement();
        });
    }

    public function hasCommentChanged(): bool
    {
        return $this->hasPropertyChanged(static function (Column $column): string {
            return $column->getComment();
        });
    }

    public function hasPlatformOptionsChanged(): bool
    {
        return $this->hasPropertyChanged(static function (Column $column): array {
            return $column->getPlatformOptions();
        });
    }

    private function hasPropertyChanged(callable $property): bool
    {
        return $property($this->newColumn) !== $property($this->oldColumn);
    }
}
