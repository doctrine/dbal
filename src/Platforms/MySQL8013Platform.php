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
    public function getColumnNameForIndexFetch(): string
    {
        return "COALESCE(COLUMN_NAME, CONCAT('(', REPLACE(EXPRESSION, '\\\''', ''''), ')'))";
    }

    public function supportsFunctionalIndex(): bool
    {
        return true;
    }
}
