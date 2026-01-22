<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\DefaultExpression;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\DefaultExpression;
use Override;

/**
 * Represents the "current time" default expression.
 */
final readonly class CurrentTime implements DefaultExpression
{
    #[Override]
    public function toSQL(AbstractPlatform $platform): string
    {
        return $platform->getCurrentTimeSQL();
    }
}
