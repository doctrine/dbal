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
     * If passed to the corresponding parser, the name should be parsed back to an equivalent object.
     */
    public function toString(): string;
}
