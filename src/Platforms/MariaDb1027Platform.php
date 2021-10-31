<?php

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\Deprecation;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.2 (10.2.7 GA) database platform.
 *
 * Note: Should not be used with versions prior to 10.2.7.
 */
class MariaDb1027Platform extends MySQLPlatform
{
    /**
     * {@inheritdoc}
     *
     * @link https://mariadb.com/kb/en/library/json-data-type/
     */
    public function getJsonTypeDeclarationSQL(array $column): string
    {
        return 'LONGTEXT';
    }

    /**
     * @deprecated Implement {@link createReservedKeywordsList()} instead.
     */
    protected function getReservedKeywordsClass(): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4510',
            'MariaDb1027Platform::getReservedKeywordsClass() is deprecated,'
                . ' use MariaDb1027Platform::createReservedKeywordsList() instead.'
        );

        return Keywords\MariaDb102Keywords::class;
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        parent::initializeDoctrineTypeMappings();

        $this->doctrineTypeMapping['json'] = Types::JSON;
    }
}
