<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Types\JsonType;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.4 (10.4.6 GA) database platform.
 *
 * Extend deprecated MariaDb1027Platform to ensure correct functions used in MySQLSchemaManager which
 * tests for MariaDb1027Platform not MariaDBPlatform.
 *
 * @deprecated This class will be merged with {@see MariaDBPlatform} in 4.0 because support for MariaDB
 *             releases prior to 10.4.3 will be dropped.
 */
class MariaDB1043Platform extends MariaDBPlatform
{
    /**
     * Use JSON rather than LONGTEXT for json columns. Since it is not a true native type, do not override
     * hasNativeJsonType() so the DC2Type comment will still be set.
     *
     * {@inheritDoc}
     */
    public function getJsonTypeDeclarationSQL(array $column): string
    {
        return 'JSON';
    }

    /**
     * Generate SQL snippets to reverse the aliasing of JSON to LONGTEXT.
     *
     * MariaDb aliases columns specified as JSON to LONGTEXT and sets a CHECK constraint to ensure the column
     * is valid json. This function generates the SQL snippets which reverse this aliasing i.e. report a column
     * as JSON where it was originally specified as such instead of LONGTEXT.
     *
     * The CHECK constraints are stored in information_schema.CHECK_CONSTRAINTS so JOIN that table.
     *
     * @return array{string, string}
     */
    public function getColumnTypeSQLSnippets(string $tableAlias = 'c'): array
    {
        if ($this->getJsonTypeDeclarationSQL([]) !== 'JSON') {
            return parent::getColumnTypeSQLSnippets($tableAlias);
        }

        $columnTypeSQL = <<<SQL
            IF(
                x.CHECK_CLAUSE IS NOT NULL AND $tableAlias.COLUMN_TYPE = 'longtext',
                'json',
                $tableAlias.COLUMN_TYPE
            )
        SQL;

        $joinCheckConstraintSQL = <<<SQL
        LEFT JOIN information_schema.CHECK_CONSTRAINTS x
            ON (
                $tableAlias.TABLE_SCHEMA = x.CONSTRAINT_SCHEMA
                AND $tableAlias.TABLE_NAME = x.TABLE_NAME
                AND x.CHECK_CLAUSE = CONCAT('json_valid(`', $tableAlias.COLUMN_NAME , '`)')
            )
        SQL;

        return [$columnTypeSQL, $joinCheckConstraintSQL];
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
}
