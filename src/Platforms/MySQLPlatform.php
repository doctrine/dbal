<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MySQLKeywords;
use Doctrine\DBAL\Schema\Index;

/**
 * Provides the behavior, features and SQL dialect of the Oracle MySQL database platform
 * of the oldest supported version.
 */
class MySQLPlatform extends AbstractMySQLPlatform
{
    public function hasNativeJsonType(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getJsonTypeDeclarationSQL(array $column): string
    {
        return 'JSON';
    }

    /**
     * {@inheritdoc}
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
