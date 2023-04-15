<?php

namespace Doctrine\DBAL\Platforms;

use Doctrine\Deprecations\Deprecation;

/**
 * Provides the behavior, features and SQL dialect of the MySQL 8.0 (8.0 GA) database platform.
 */
class MySQL80Platform extends MySQL57Platform
{
    /**
     * {@inheritdoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.0/en/date-and-time-literals.html#date-and-time-string-numeric-literals
     */
    public function getDateTimeTzFormatString()
    {
        return 'Y-m-d H:i:sP';
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Implement {@see createReservedKeywordsList()} instead.
     */
    protected function getReservedKeywordsClass()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4510',
            'MySQL80Platform::getReservedKeywordsClass() is deprecated,'
                . ' use MySQL80Platform::createReservedKeywordsList() instead.',
        );

        return Keywords\MySQL80Keywords::class;
    }
}
