<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Represents the default expression of a column.
 */
interface DefaultExpression
{
    /**
     * Returns the SQL representation of the default expression for the given platform.
     */
    public function toSQL(AbstractPlatform $platform): string;
}
