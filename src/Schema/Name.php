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
     * The representation is intended for display, such as in error messages, not for parsing back into a name:
     * parsing does not always reproduce the original. If an unquoted part of the name contains whitespace, a dot,
     * or a quote character (", `, [, ]), parsing it will return a different name or fail.
     */
    public function toString(): string;
}
