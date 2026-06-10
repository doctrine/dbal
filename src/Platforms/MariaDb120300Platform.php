<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\Deprecations\Deprecation;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 12.3 database platform.
 */
class MariaDb120300Platform extends MariaDb110700Platform
{
    /** @deprecated Implement {@see createReservedKeywordsList()} instead. */
    protected function getReservedKeywordsClass(): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4510',
            'MariaDb120300Platform::getReservedKeywordsClass() is deprecated,'
                . ' use MariaDb120300Platform::createReservedKeywordsList() instead.',
        );

        return Keywords\MariaDb123Keywords::class;
    }
}
