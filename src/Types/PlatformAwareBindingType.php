<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Implemented by types whose database values have to be bound differently depending on the platform.
 *
 * The binding type returned by {@see getBindingTypeForPlatform()} takes precedence over
 * {@see Type::getBindingType()} whenever the platform is known.
 */
interface PlatformAwareBindingType
{
    /**
     * Gets the binding type to use for database values of this type on the given platform.
     */
    public function getBindingTypeForPlatform(AbstractPlatform $platform): ParameterType;
}
