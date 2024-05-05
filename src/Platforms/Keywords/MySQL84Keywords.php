<?php

namespace Doctrine\DBAL\Platforms\Keywords;

use Doctrine\Deprecations\Deprecation;

use function array_merge;

/**
 * MySQL 8.4 reserved keywords list.
 */
class MySQL84Keywords extends MySQL80Keywords
{
    /**
     * {@inheritDoc}
     *
     * @deprecated
     */
    public function getName()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5433',
            'MySQL84Keywords::getName() is deprecated.',
        );

        return 'MySQL84';
    }

    /**
     * {@inheritDoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/keywords.html#keywords-new-in-current-series
     */
    protected function getKeywords()
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
