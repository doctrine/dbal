<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Provides value equality by comparing the database representations of the values.
 *
 * Use this trait in a type that implements {@see EquatableType}. Comparing the
 * converted values reflects what is actually persisted: types that store only
 * part of a PHP value, such as the date of a DateTime, do not report a change
 * for a difference the database does not store.
 */
trait DatabaseValueEquality
{
    /** @throws ConversionException */
    public function valuesAreEqual(object $a, object $b, AbstractPlatform $platform): bool
    {
        return $this->convertToDatabaseValue($a, $platform)
            === $this->convertToDatabaseValue($b, $platform);
    }
}
