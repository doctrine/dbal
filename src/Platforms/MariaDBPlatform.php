<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MariaDBKeywords;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\Types\JsonType;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB database platform of the oldest supported version.
 */
class MariaDBPlatform extends AbstractMySQLPlatform
{
    /**
     * {@inheritDoc}
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, $tableName): array
    {
        return ['ALTER TABLE ' . $tableName . ' RENAME INDEX ' . $oldIndexName . ' TO ' . $index->getQuotedName($this)];
    }

    /**
     * Generate SQL snippets to reverse the aliasing of JSON to LONGTEXT.
     *
     * MariaDb aliases columns specified as JSON to LONGTEXT and sets a CHECK constraint to ensure the column
     * is valid json. This function generates the SQL snippets which reverse this aliasing i.e. report a column
     * as JSON where it was originally specified as such instead of LONGTEXT.
     *
     * The CHECK constraints are stored in information_schema.CHECK_CONSTRAINTS so query that table.
     */
    public function getColumnTypeSQLSnippet(string $tableAlias, string $databaseName): string
    {
        $subQueryAlias = 'i_' . $tableAlias;

        $databaseName = $this->quoteStringLiteral($databaseName);

        // The check for `CONSTRAINT_SCHEMA = $databaseName` is mandatory here to prevent performance issues
        return <<<SQL
            IF(
                $tableAlias.COLUMN_TYPE = 'longtext'
                AND EXISTS(
                    SELECT * FROM information_schema.CHECK_CONSTRAINTS $subQueryAlias
                    WHERE $subQueryAlias.CONSTRAINT_SCHEMA = $databaseName
                    AND $subQueryAlias.TABLE_NAME = $tableAlias.TABLE_NAME
                    AND $subQueryAlias.CHECK_CLAUSE = CONCAT(
                        'json_valid(`',
                            $tableAlias.COLUMN_NAME,
                        '`)'
                    )
                ),
                'json',
                $tableAlias.COLUMN_TYPE
            )
        SQL;
    }

    /** {@inheritDoc} */
    public function getColumnDeclarationSQL(string $name, array $column): string
    {
        // MariaDb forces column collation to utf8mb4_bin where the column was declared as JSON so ignore
        // collation and character set for json columns as attempting to set them can cause an error.
        if ($this->getJsonTypeDeclarationSQL([]) === 'JSON' && ($column['type'] ?? null) instanceof JsonType) {
            unset($column['collation']);
            unset($column['charset']);
        }

        return parent::getColumnDeclarationSQL($name, $column);
    }

    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return new DefaultSelectSQLBuilder($this, 'FOR UPDATE', null);
    }

    protected function createReservedKeywordsList(): KeywordList
    {
        return new MariaDBKeywords();
    }
}
