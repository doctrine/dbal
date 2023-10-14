<?php

namespace Doctrine\DBAL\Platforms;

/**
 * Provides features of the MySQL since 8.0.13 database platform.
 *
 * Note: Should not be used with versions prior to 8.0.13.
 */
class MySQL8013Platform extends MySQL80Platform
{
    public function getColumnNameForIndexFetch(): string
    {
        return "COALESCE(COLUMN_NAME, CONCAT('(', REPLACE(EXPRESSION, '\\''', ''''), ')'))";
    }

    protected function supportsFunctionalIndex(): bool
    {
        return true;
    }
}
