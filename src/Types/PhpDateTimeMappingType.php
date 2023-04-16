<?php

namespace Doctrine\DBAL\Types;

/**
 * Implementations should map a database type to a PHP DateTimeInterface instance.
 *
 * @internal
 */
interface PhpDateTimeMappingType
{
    public const CONVERSION_TARGET_DATABASE = 'database';
    public const CONVERSION_TARGET_PHP      = 'php';
}
