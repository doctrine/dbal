<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

/**
 * Provides the behavior, features and SQL dialect of the Microsoft SQL Server 2017 database platform.
 */
class SQLServer2017Platform extends SQLServer2012Platform
{
    public function getAggregateConcatExpression(string $value, string $separator, ?string $orderBy = null): string
    {
        $orderByClause = $orderBy !== null ? ' WITHIN GROUP(ORDER BY ' . $orderBy . ')' : '';

        return 'STRING_AGG(' . $value . ', ' . $separator . ')' . $orderByClause;
    }
}
