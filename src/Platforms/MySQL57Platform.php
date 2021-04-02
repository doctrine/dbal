<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\Deprecation;

/**
 * Provides the behavior, features and SQL dialect of the MySQL 5.7 (5.7.9 GA) database platform.
 */
class MySQL57Platform extends MySQLPlatform
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

    public function createSQLParser(): Parser
    {
        return new Parser(true);
    }

    /**
     * {@inheritdoc}
     */
    protected function getPreAlterTableRenameIndexForeignKeySQL(TableDiff $diff): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function getPostAlterTableRenameIndexForeignKeySQL(TableDiff $diff): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, string $tableName): array
    {
        return ['ALTER TABLE ' . $tableName . ' RENAME INDEX ' . $oldIndexName . ' TO ' . $index->getQuotedName($this)];
    }

    /**
     * @deprecated Implement {@link createReservedKeywordsList()} instead.
     */
    protected function getReservedKeywordsClass(): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4510',
            'MySQL57Platform::getReservedKeywordsClass() is deprecated,'
                . ' use MySQL57Platform::createReservedKeywordsList() instead.'
        );

        return Keywords\MySQL57Keywords::class;
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        parent::initializeDoctrineTypeMappings();

        $this->doctrineTypeMapping['json'] = Types::JSON;
    }
}
