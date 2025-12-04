<?php

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\Deprecations\Deprecation;

/**
 * Provides the behavior, features and SQL dialect of the MySQL 8.0 database platform.
 */
class MySQL80Platform extends MySQL57Platform
{
    /**
     * {@inheritDoc}
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

    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return AbstractPlatform::createSelectSQLBuilder();
    }

    public function getDefaultValueDeclarationSQL(array $column): string
    {
        if (isset($column['default']) && ($column['type'] instanceof TextType || $column['type'] instanceof BlobType || $column['type'] instanceof JsonType)) {
            // mysql requires text type column defaults to be written as expressions
            // https://dev.mysql.com/doc/refman/8.0/en/data-type-defaults.html
            // https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html
            // "The BLOB, TEXT, GEOMETRY, and JSON data types can be assigned a default value only if the value is written as an expression, even if the expression value is a literal"
            return ' DEFAULT (' . $this->quoteStringLiteral($column['default']) . ')';
        }

        return parent::getDefaultValueDeclarationSQL($column);
    }
}
