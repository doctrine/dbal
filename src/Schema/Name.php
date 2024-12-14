<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

/**
 * Represents a database object name.
 */
interface Name
{
    /**
     * Returns the string representation of the name.
     *
     * The consumers of this method should not rely on a specific return value. It should be used only for diagnostic
     * purposes.
     */
    public function toString(): string;
}
