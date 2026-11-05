<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Types\JsonType;
use Override;

use function implode;
use function sprintf;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB database platform of the oldest supported version.
 */
class MariaDBPlatform extends AbstractMySQLPlatform
{
    /**
     * Generate SQL snippets to reverse the aliasing of JSON to LONGTEXT.
     *
     * MariaDb aliases columns specified as JSON to LONGTEXT and sets a CHECK constraint to ensure the column
     * is valid json. This function generates the SQL snippets which reverse this aliasing i.e. report a column
     * as JSON where it was originally specified as such instead of LONGTEXT.
     *
     * The CHECK constraints are stored in information_schema.CHECK_CONSTRAINTS so query that table.
     *
     * @internal The method should be only used from within the {@see MySQLSchemaManager} class hierarchy.
     */
    #[Override]
    public function getColumnTypeSQLSnippet(string $tableAlias, string $databaseName): string
    {
        $subQueryAlias = 'i_' . $tableAlias;

        $databaseName = $this->quoteStringLiteral($databaseName);

        // The check for `CONSTRAINT_SCHEMA = $databaseName` is mandatory here to prevent performance issues
        return <<<SQL
            IF(
                $tableAlias.DATA_TYPE = 'longtext'
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
                $tableAlias.DATA_TYPE
            )
        SQL;
    }

    /**
     * Unlike other platforms, the default referential action on MariaDB is <code>RESTRICT</code>, so we cannot use the
     * default implementation which assumes <code>NO_ACTION</code> as the default.
     *
     * @link https://mariadb.com/kb/en/foreign-keys/#constraints
     */
    #[Override]
    protected function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        return sprintf(
            ' ON UPDATE %s ON DELETE %s',
            $foreignKey->getOnUpdateAction()->toSQL(),
            $foreignKey->getOnDeleteAction()->toSQL(),
        );
    }

    #[Override]
    protected function getColumnDeclarationSQL(array $column): string
    {
        // MariaDb forces column collation to utf8mb4_bin where the column was declared as JSON so ignore
        // collation and character set for json columns as attempting to set them can cause an error.
        if (
            $this->getJsonTypeDeclarationSQL([]) === 'JSON'
            && $this->getColumnTypeOrNull($column) instanceof JsonType
        ) {
            unset($column['collation']);
            unset($column['charset']);
        }

        return parent::getColumnDeclarationSQL($column);
    }

    /** @internal The method should be only used from within the {@see MySQLSchemaManager} class hierarchy. */
    #[Override]
    public function fetchTableOptionsByTable(bool $includeTableName): string
    {
        // MariaDB-10.10.1 added FULL_COLLATION_NAME to the information_schema.COLLATION_CHARACTER_SET_APPLICABILITY.
        // A base collation like uca1400_ai_ci can refer to multiple character sets. The value in the
        // information_schema.TABLES.TABLE_COLLATION corresponds to the full collation name.
        $sql = <<<'SQL'
    SELECT t.TABLE_NAME,
           t.ENGINE,
           t.AUTO_INCREMENT,
           t.TABLE_COMMENT,
           t.CREATE_OPTIONS,
           t.TABLE_COLLATION,
           ccsa.CHARACTER_SET_NAME
      FROM information_schema.TABLES t
        INNER JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY ccsa
          ON ccsa.FULL_COLLATION_NAME = t.TABLE_COLLATION
SQL;

        $conditions = ['t.TABLE_SCHEMA = ?'];

        if ($includeTableName) {
            $conditions[] = 't.TABLE_NAME = ?';
        }

        $conditions[] = "t.TABLE_TYPE = 'BASE TABLE'";

        return $sql . ' WHERE ' . implode(' AND ', $conditions);
    }
}
