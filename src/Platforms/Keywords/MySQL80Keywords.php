<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Keywords;

use function array_merge;

/**
 * MySQL 8.0 reserved keywords list.
 *
 * @deprecated This class will be removed once support for MySQL 5.7 is dropped.
 */
class MySQL80Keywords extends MySQLKeywords
{
    /**
     * {@inheritDoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.0/en/keywords.html
     */
    protected function getKeywords(): array
    {
        $keywords = parent::getKeywords();

        $keywords = array_merge($keywords, [
            'ADMIN',
            'ARRAY',
            'CUBE',
            'CUME_DIST',
            'DENSE_RANK',
            'EMPTY',
            'EXCEPT',
            'FIRST_VALUE',
            'FUNCTION',
            'GROUPING',
            'GROUPS',
            'JSON_TABLE',
            'LAG',
            'LAST_VALUE',
            'LATERAL',
            'LEAD',
            'MEMBER',
            'NTH_VALUE',
            'NTILE',
            'OF',
            'OVER',
            'PERCENT_RANK',
            'PERSIST',
            'PERSIST_ONLY',
            'RANK',
            'RECURSIVE',
            'ROW',
            'ROWS',
            'ROW_NUMBER',
            'SYSTEM',
            'WINDOW',
        ]);

        return $keywords;
    }
}
