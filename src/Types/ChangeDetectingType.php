<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Contract for types that can detect changes on the object values they map.
 *
 * A type implements this interface to let its consumers compare mapped values
 * by value instead of by identity. Consumers that track changes, such as an
 * object mapper, can then treat a value replaced with an equal one as
 * unchanged.
 *
 * Only object values are compared. Scalars are already compared by value with
 * a strict comparison, so a type that maps a scalar does not need this
 * interface.
 */
interface ChangeDetectingType
{
    /**
     * Tells whether the value changed between the old and the new value.
     *
     * The method returns true when a change is detected, and false when the
     * values are equal.
     *
     * Both values must be supported by the type. If one of them is not, an
     * implementation must throw a {@see ConversionException}.
     *
     * An object is always equal to itself. A caller may therefore skip this
     * call when the two values are the same instance.
     *
     * The comparison is commutative and must not depend on the order of the
     * values: `hasValueChanged($oldValue, $newValue)` and
     * `hasValueChanged($newValue, $oldValue)` must return the same result.
     *
     * The platform is provided so that a type can compare the database
     * representations of the values, which is the most reliable definition of
     * a change for the data that is actually persisted.
     *
     * @throws ConversionException If one of the values is not supported by the type.
     */
    public function hasValueChanged(object $oldValue, object $newValue, AbstractPlatform $platform): bool;
}
