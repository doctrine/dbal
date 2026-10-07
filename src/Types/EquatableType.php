<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Contract for types that can compare two object values by value.
 *
 * A mapping type can implement this interface to tell the ORM how to detect
 * changes on the values it maps. When the type reports the old and the new
 * value as equal, the ORM leaves the field out of the change set and skips a
 * useless update. This is useful for value objects: replacing a value object
 * with an equal one no longer triggers an update.
 *
 * Only object values are compared. Scalars are already compared by value with
 * the strict comparison the ORM uses, so a type that maps a scalar does not
 * need this interface.
 */
interface EquatableType
{
    /**
     * Tells whether the two given PHP values of this type are equal.
     *
     * The platform is provided so that a type can compare the database
     * representations of the values, which is the most reliable definition of
     * equality for the data that is actually persisted.
     */
    public function valuesAreEqual(object $a, object $b, AbstractPlatform $platform): bool;
}
