<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Keywords;

use function array_merge;

/**
 * MySQL 8.4 reserved keywords list.
 */
class MySQL84Keywords extends MySQLKeywords
{
    /**
     * {@inheritDoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/keywords.html#keywords-new-in-current-series
     */
    protected function getKeywords(): array
    {
        $keywords = parent::getKeywords();

        $keywords = array_merge($keywords, [
            'AUTO',
            'BERNOULLI',
            'GTIDS',
            'LOG',
            'MANUAL',
            'PARALLEL',
            'PARSE_TREE',
            'QUALIFY',
            'S3',
            'TABLESAMPLE',
        ]);

        return $keywords;
    }
}
