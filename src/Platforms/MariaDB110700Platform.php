<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Exception\InvalidColumnType\ColumnLengthRequired;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MariaDB117Keywords;
use Doctrine\Deprecations\Deprecation;

use function sprintf;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 11.7 database platform.
 */
class MariaDB110700Platform extends MariaDB1010Platform
{
    /** @deprecated */
    protected function createReservedKeywordsList(): KeywordList
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6607',
            '%s is deprecated.',
            __METHOD__,
        );

        return new MariaDB117Keywords();
    }

    /** @inheritdoc */
    public function getVectorTypeDeclarationSQL(array $column): string
    {
        return AbstractMySQLPlatform::getVectorTypeDeclarationSQL($column);
    }
}
