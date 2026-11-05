<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Provides change detection by comparing the database representations of the values.
 *
 * Use this trait in a type that implements {@see ChangeDetectingType} and
 * extends {@see Type}.
 *
 * Comparing the converted values reflects what is actually persisted: types
 * that store only part of a PHP value, such as the date of a DateTime, do not
 * report a change for a difference the database does not store.
 *
 * @phpstan-require-extends Type
 * @phpstan-require-implements ChangeDetectingType
 */
trait DatabaseValueEquality
{
    /** @throws ConversionException */
    public function hasValueChanged(object $oldValue, object $newValue, AbstractPlatform $platform): bool
    {
        return $this->convertToDatabaseValue($oldValue, $platform)
            !== $this->convertToDatabaseValue($newValue, $platform);
    }
}
