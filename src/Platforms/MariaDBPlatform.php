<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MariaDBKeywords;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\Deprecations\Deprecation;

use function array_diff_key;
use function array_merge;
use function count;
use function in_array;
use function sprintf;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB database platform of the oldest supported version.
 */
class MariaDBPlatform extends AbstractMySQLPlatform
{
    /**
     * MariaDB only accepts strings with a binary collation as geometries in the arguments of spatial functions,
     * while natively prepared parameters are strings with the connection collation.
     */
    public function convertGeometryToDatabaseValueSQL(string $sqlExpr): string
    {
        return 'CAST(' . $sqlExpr . ' AS BINARY)';
    }

    /**
     * ST_IsEmpty() returns 0 for the empty geometry collections, the only empty geometries of MariaDB.
     */
    public function getSpatialIsEmptyExpression(string $geometry): string
    {
        return 'ST_AsText(' . $geometry . ") LIKE '% EMPTY'";
    }

    public function getSpatialIsValidExpression(string $geometry): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * ST_Distance() does not reset its NULL flag, so that a NULL argument makes the distances of the following rows
     * NULL as well. The NULL arguments are replaced by points farther than any geometry, whose distances are mapped
     * back to NULL.
     */
    public function getSpatialDistanceExpression(string $geometry1, string $geometry2): string
    {
        return sprintf(
            "NULLIF(LEAST(ST_Distance(COALESCE(%s, ST_GeomFromText('POINT(1e150 0)')),"
                . " COALESCE(%s, ST_GeomFromText('POINT(-1e150 0)'))), 1e149), 1e149)",
            $geometry1,
            $geometry2,
        );
    }

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
     * {@inheritDoc}
     */
    protected function getPreAlterTableRenameIndexForeignKeySQL(TableDiff $diff): array
    {
        $sql       = [];
        $tableName = $diff->getOldTable()->getQuotedName($this);

        $modifiedForeignKeys = $diff->getModifiedForeignKeys();

        foreach ($this->getRemainingForeignKeyConstraintsRequiringRenamedIndexes($diff) as $foreignKey) {
            if (! in_array($foreignKey, $modifiedForeignKeys, true)) {
                $sql[] = $this->getDropForeignKeySQL($foreignKey->getQuotedName($this), $tableName);
            }
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        return array_merge(
            parent::getPostAlterTableIndexForeignKeySQL($diff),
            $this->getPostAlterTableRenameIndexForeignKeySQL($diff),
        );
    }

    /** @return list<string> */
    private function getPostAlterTableRenameIndexForeignKeySQL(TableDiff $diff): array
    {
        $sql = [];

        $tableName = $diff->getOldTable()->getQuotedName($this);

        $modifiedForeignKeys = $diff->getModifiedForeignKeys();

        foreach ($this->getRemainingForeignKeyConstraintsRequiringRenamedIndexes($diff) as $foreignKey) {
            if (! in_array($foreignKey, $modifiedForeignKeys, true)) {
                $sql[] = $this->getCreateForeignKeySQL($foreignKey, $tableName);
            }
        }

        return $sql;
    }

    /**
     * Returns the remaining foreign key constraints that require one of the renamed indexes.
     *
     * "Remaining" here refers to the diff between the foreign keys currently defined in the associated
     * table and the foreign keys to be removed.
     *
     * @param TableDiff $diff The table diff to evaluate.
     *
     * @return ForeignKeyConstraint[]
     */
    private function getRemainingForeignKeyConstraintsRequiringRenamedIndexes(TableDiff $diff): array
    {
        $renamedIndexes = $diff->getRenamedIndexes();

        if (count($renamedIndexes) === 0) {
            return [];
        }

        $foreignKeys = [];

        $remainingForeignKeys = array_diff_key(
            $diff->getOldTable()->getForeignKeys(),
            $diff->getDroppedForeignKeys(),
        );

        foreach ($remainingForeignKeys as $foreignKey) {
            foreach ($renamedIndexes as $index) {
                if ($foreignKey->intersectsIndexColumns($index)) {
                    $foreignKeys[] = $foreignKey;

                    break;
                }
            }
        }

        return $foreignKeys;
    }

    /** {@inheritDoc} */
    public function getColumnDeclarationSQL(string $name, array $column): string
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

        return parent::getColumnDeclarationSQL($name, $column);
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

        return new MariaDBKeywords();
    }
}
