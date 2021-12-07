<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MariaDb102Keywords;
use Doctrine\DBAL\Types\Types;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.2 (10.2.7 GA) database platform.
 *
 * Note: Should not be used with versions prior to 10.2.7.
 */
class MariaDb1027Platform extends MariaDBPlatform
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

    protected function createReservedKeywordsList(): KeywordList
    {
        return new MariaDb102Keywords();
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        parent::initializeDoctrineTypeMappings();

        $this->doctrineTypeMapping['json'] = Types::JSON;
    }
}
