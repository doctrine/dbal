<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use function strcasecmp;

/**
 * Represents the change of a column.
 */
final readonly class ColumnDiff
{
    /** @internal The diff can be only instantiated by a {@see Comparator}. */
    public function __construct(private Column $oldColumn, private Column $newColumn)
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
        $oldColumnName = $this->getOldColumn()
            ->getObjectName()
            ->getIdentifier()
            ->getValue();

        $newColumnName = $this->getNewColumn()
            ->getObjectName()
            ->getIdentifier()
            ->getValue();

        // Column name comparison is currently performed in a case-insensitive way
        return strcasecmp($oldColumnName, $newColumnName) !== 0;
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
        foreach (
            [
                static fn (Column $column): ?string => $column->getCharset(),
                static fn (Column $column): ?string => $column->getCollation(),
                static fn (Column $column): mixed => $column->getMinimumValue(),
                static fn (Column $column): mixed => $column->getMaximumValue(),
            ] as $property
        ) {
            if ($this->hasPropertyChanged($property)) {
                return true;
            }
        }

        return false;
    }

    private function hasPropertyChanged(callable $property): bool
    {
        return $property($this->newColumn) !== $property($this->oldColumn);
    }
}
