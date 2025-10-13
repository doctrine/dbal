<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\DefaultExpression;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\DefaultExpression;

/**
 * Represents the "current timestamp" default expression.
 */
final readonly class CurrentTimestamp implements DefaultExpression
{
    public function toSQL(AbstractPlatform $platform): string
    {
        return $platform->getCurrentTimestampSQL();
    }
}
