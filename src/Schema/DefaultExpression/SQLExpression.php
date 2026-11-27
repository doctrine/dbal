<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\DefaultExpression;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\DefaultExpression;

/**
 * Represents an arbitrary SQL expression, such as a function call, used as a column default.
 *
 * The expression is declared as is. Schema introspection returns the expression as normalized by the database, so in
 * order for the declared and the introspected column to match, the expression must be specified in the normalized form.
 */
final readonly class SQLExpression implements DefaultExpression
{
    public function __construct(private string $sql)
    {
    }

    public function toSQL(AbstractPlatform $platform): string
    {
        return $this->sql;
    }
}
