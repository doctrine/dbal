<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MySQLKeywords;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\TextType;

/**
 * Provides the behavior, features and SQL dialect of the Oracle MySQL database platform
 * of the oldest supported version.
 */
class MySQLPlatform extends AbstractMySQLPlatform
{
    /**
     * {@inheritDoc}
     *
     * Oracle MySQL does not support default values on TEXT/BLOB columns until 8.0.13.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @link https://dev.mysql.com/doc/relnotes/mysql/8.0/en/news-8-0-13.html#mysqld-8-0-13-data-types
     */
    public function getDefaultValueDeclarationSQL(array $column): string
    {
        if ($column['type'] instanceof TextType || $column['type'] instanceof BlobType) {
            unset($column['default']);
        }

        return parent::getDefaultValueDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, string $tableName): array
    {
        return ['ALTER TABLE ' . $tableName . ' RENAME INDEX ' . $oldIndexName . ' TO ' . $index->getQuotedName($this)];
    }

    protected function createReservedKeywordsList(): KeywordList
    {
        return new MySQLKeywords();
    }
}
