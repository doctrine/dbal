<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

/**
 * Provides features of the MySQL since 8.0.13 database platform.
 *
 * Note: Should not be used with versions prior to 8.0.13.
 */
class MySQL8013Platform extends MySQLPlatform
{
    public function getColumnOrExpressionNameForIndexFetching(): string
    {
        return <<<'SQL'
            COALESCE(
                COLUMN_NAME,
                (CASE WHEN SUBSTR(EXPRESSION, 1, 1) != '('
                    THEN CONCAT('(', REPLACE(EXPRESSION, '\'', ''''), ')')
                    ELSE REPLACE(EXPRESSION, '\'', '''')
                END)
            )
        SQL;
    }
}
