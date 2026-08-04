<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MySQLKeywords;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\SQL\Builder\WithSQLBuilder;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;

use function is_string;

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
        if (isset($column['typeName']) && is_string($column['typeName'])) {
            $type = $this->getType($column['typeName']);
        } elseif (isset($column['type']) && $column['type'] instanceof Type) {
            $type = $column['type'];
        } else {
            $type = null;
        }

        if ($type instanceof TextType || $type instanceof BlobType) {
            $column['default'] = null;
        }

        return parent::getDefaultValueDeclarationSQL($column);
    }

    public function createWithSQLBuilder(): WithSQLBuilder
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, string $tableName): array
    {
        return ['ALTER TABLE ' . $tableName . ' RENAME INDEX ' . $oldIndexName . ' TO ' . $index->getQuotedName($this)];
    }

    /** @deprecated */
    protected function createReservedKeywordsList(): KeywordList
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6607',
            '%s is deprecated.',
            __METHOD__,
        );

        return new MySQLKeywords();
    }
}
