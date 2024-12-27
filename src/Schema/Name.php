<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Represents a database object name.
 */
interface Name
{
    /**
     * Returns the SQL representation of the name for the given platform.
     */
    public function toSQL(AbstractPlatform $platform): string;

    /**
     * Returns the string representation of the name.
     *
     * If passed to the corresponding parser, the name should be parsed back to an equivalent object.
     */
    public function toString(): string;
}
