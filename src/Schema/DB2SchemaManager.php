<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Types;

use function array_change_key_case;
use function implode;
use function preg_match;
use function sprintf;
use function str_replace;
use function strpos;
use function strtolower;
use function strtoupper;
use function substr;

use const CASE_LOWER;

/**
 * Db2 Schema Manager.
 *
 * @link https://www.ibm.com/docs/en/db2/11.5?topic=sql-catalog-views
 *
 * @extends AbstractSchemaManager<DB2Platform>
 */
class DB2SchemaManager extends AbstractSchemaManager
{
    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        $length = $precision = $default = null;
        $scale  = 0;
        $fixed  = false;

        if ($tableColumn['default'] !== null && $tableColumn['default'] !== 'NULL') {
            $default = $tableColumn['default'];

            if (preg_match('/^\'(.*)\'$/s', $default, $matches) === 1) {
                $default = str_replace("''", "'", $matches[1]);
            }
        }

        $type = $this->platform->getDoctrineTypeMapping($tableColumn['typename']);

        switch (strtolower($tableColumn['typename'])) {
            case 'varchar':
                if ($tableColumn['codepage'] === 0) {
                    $type = Types::BINARY;
                }

                $length = $tableColumn['length'];
                break;

            case 'character':
                if ($tableColumn['codepage'] === 0) {
                    $type = Types::BINARY;
                }

                $length = $tableColumn['length'];
                $fixed  = true;
                break;

            case 'clob':
                $length = $tableColumn['length'];
                break;

            case 'decimal':
            case 'double':
            case 'real':
                $scale     = $tableColumn['scale'];
                $precision = $tableColumn['length'];
                break;
        }

        $options = [
            'length'          => $length,
            'fixed'           => $fixed,
            'default'         => $default,
            'autoincrement'   => (bool) $tableColumn['autoincrement'],
            'notnull'         => $tableColumn['nulls'] === 'N',
        ];

        if ($tableColumn['comment'] !== null) {
            $options['comment'] = $tableColumn['comment'];
        }

        if ($scale !== null && $precision !== null) {
            $options['scale']     = $scale;
            $options['precision'] = $precision;
        }

        return new Column($tableColumn['colname'], $type, $options);
    }

    /**
     * @deprecated Use the schema name and the unqualified table name separately instead.
     *
     * {@inheritDoc}
     */
    protected function _getPortableTableDefinition(array $table): string
    {
        $table = array_change_key_case($table, CASE_LOWER);

        return $table['name'];
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableIndexesList(array $rows, string $tableName): array
    {
        foreach ($rows as &$row) {
            $row            = array_change_key_case($row, CASE_LOWER);
            $row['primary'] = (bool) $row['primary'];
        }

        return parent::_getPortableTableIndexesList($rows, $tableName);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableForeignKeyDefinition(array $tableForeignKey): ForeignKeyConstraint
    {
        return new ForeignKeyConstraint(
            $tableForeignKey['local_columns'],
            $tableForeignKey['foreign_table'],
            $tableForeignKey['foreign_columns'],
            $tableForeignKey['name'],
            $tableForeignKey['options'],
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableForeignKeysList(array $rows): array
    {
        $foreignKeys = [];

        foreach ($rows as $tableForeignKey) {
            $tableForeignKey = array_change_key_case($tableForeignKey, CASE_LOWER);

            if (! isset($foreignKeys[$tableForeignKey['index_name']])) {
                $foreignKeys[$tableForeignKey['index_name']] = [
                    'local_columns'   => [$tableForeignKey['local_column']],
                    'foreign_table'   => $tableForeignKey['foreign_table'],
                    'foreign_columns' => [$tableForeignKey['foreign_column']],
                    'name'            => $tableForeignKey['index_name'],
                    'options'         => [
                        'onUpdate' => $tableForeignKey['on_update'],
                        'onDelete' => $tableForeignKey['on_delete'],
                    ],
                ];
            } else {
                $foreignKeys[$tableForeignKey['index_name']]['local_columns'][]   = $tableForeignKey['local_column'];
                $foreignKeys[$tableForeignKey['index_name']]['foreign_columns'][] = $tableForeignKey['foreign_column'];
            }
        }

        return parent::_getPortableTableForeignKeysList($foreignKeys);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        $view = array_change_key_case($view, CASE_LOWER);

        $sql = '';
        $pos = strpos($view['text'], ' AS ');

        if ($pos !== false) {
            $sql = substr($view['text'], $pos + 4);
        }

        return new View($view['name'], $sql);
    }

    /** @deprecated Use {@see Identifier::toNormalizedValue()} instead. */
    protected function normalizeName(string $name): string
    {
        $identifier = new Identifier($name);

        return $identifier->isQuoted() ? $identifier->getName() : strtoupper($name);
    }

    protected function selectTableNames(string $databaseName): Result
    {
        $sql = <<<'SQL'
SELECT TABNAME AS NAME
FROM SYSCAT.TABLES
WHERE TYPE = 'T'
  AND TABSCHEMA = ?
SQL;

        return $this->connection->executeQuery($sql, [$databaseName]);
    }

    protected function selectTableColumns(string $databaseName, ?string $tableName = null): Result
    {
        $conditions = ['C.TABSCHEMA = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $conditions[] = 'C.TABNAME = ?';
            $params[]     = $tableName;
        }

        $sql = sprintf(
            <<<'SQL'
SELECT
       C.TABNAME AS NAME,
       C.COLNAME,
       C.TYPENAME,
       C.CODEPAGE,
       C.NULLS,
       C.LENGTH,
       C.SCALE,
       C.REMARKS AS COMMENT,
       CASE
           WHEN C.GENERATED = 'D' THEN 1
           ELSE 0
           END   AS AUTOINCREMENT,
       C.DEFAULT
FROM SYSCAT.COLUMNS C
         JOIN SYSCAT.TABLES AS T
              ON T.TABSCHEMA = C.TABSCHEMA
                  AND T.TABNAME = C.TABNAME
 WHERE %s
   AND T.TYPE = 'T'
ORDER BY C.TABNAME, C.COLNO
SQL,
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectIndexColumns(string $databaseName, ?string $tableName = null): Result
    {
        $conditions = ['IDX.TABSCHEMA = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $conditions[] = 'IDX.TABNAME = ?';
            $params[]     = $tableName;
        }

        $sql = sprintf(
            <<<'SQL'
      SELECT
             IDX.TABNAME AS NAME,
             IDX.INDNAME AS KEY_NAME,
             IDXCOL.COLNAME AS COLUMN_NAME,
             CASE
                 WHEN IDX.UNIQUERULE = 'P' THEN 1
                 ELSE 0
             END AS PRIMARY,
             CASE
                 WHEN IDX.UNIQUERULE = 'D' THEN 1
                 ELSE 0
             END AS NON_UNIQUE
        FROM SYSCAT.INDEXES AS IDX
        JOIN SYSCAT.TABLES AS T
          ON IDX.TABSCHEMA = T.TABSCHEMA AND IDX.TABNAME = T.TABNAME
        JOIN SYSCAT.INDEXCOLUSE AS IDXCOL
          ON IDX.INDSCHEMA = IDXCOL.INDSCHEMA AND IDX.INDNAME = IDXCOL.INDNAME
       WHERE %s
         AND T.TYPE = 'T'
    ORDER BY IDX.TABNAME,
             IDX.INDNAME,
             IDXCOL.COLSEQ
SQL,
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectForeignKeyColumns(string $databaseName, ?string $tableName = null): Result
    {
        $conditions = ['R.TABSCHEMA = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $conditions[] = 'R.TABNAME = ?';
            $params[]     = $tableName;
        }

        $sql = sprintf(
            <<<'SQL'
      SELECT
             R.TABNAME AS NAME,
             FKCOL.COLNAME AS LOCAL_COLUMN,
             R.REFTABNAME AS FOREIGN_TABLE,
             PKCOL.COLNAME AS FOREIGN_COLUMN,
             R.CONSTNAME AS INDEX_NAME,
             CASE
                 WHEN R.UPDATERULE = 'R' THEN 'RESTRICT'
             END AS ON_UPDATE,
             CASE
                 WHEN R.DELETERULE = 'C' THEN 'CASCADE'
                 WHEN R.DELETERULE = 'N' THEN 'SET NULL'
                 WHEN R.DELETERULE = 'R' THEN 'RESTRICT'
             END AS ON_DELETE
        FROM SYSCAT.REFERENCES AS R
         JOIN SYSCAT.TABLES AS T
              ON T.TABSCHEMA = R.TABSCHEMA
                  AND T.TABNAME = R.TABNAME
         JOIN SYSCAT.KEYCOLUSE AS FKCOL
              ON FKCOL.CONSTNAME = R.CONSTNAME
                  AND FKCOL.TABSCHEMA = R.TABSCHEMA
                  AND FKCOL.TABNAME = R.TABNAME
         JOIN SYSCAT.KEYCOLUSE AS PKCOL
              ON PKCOL.CONSTNAME = R.REFKEYNAME
                  AND PKCOL.TABSCHEMA = R.REFTABSCHEMA
                  AND PKCOL.TABNAME = R.REFTABNAME
                  AND PKCOL.COLSEQ = FKCOL.COLSEQ
      WHERE %s
        AND T.TYPE = 'T'
   ORDER BY R.TABNAME,
            R.CONSTNAME,
            FKCOL.COLSEQ
SQL,
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchTableOptionsByTable(string $databaseName, ?string $tableName = null): array
    {
        $conditions = ['TABSCHEMA = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $conditions[] = 'TABNAME = ?';
            $params[]     = $tableName;
        }

        $sql = sprintf(
            <<<'SQL'
      SELECT TABNAME,
             REMARKS
        FROM SYSCAT.TABLES
      WHERE %s
        AND TYPE = 'T'
   ORDER BY TABNAME
SQL,
            implode(' AND ', $conditions),
        );

        $tableOptions = [];
        foreach ($this->connection->iterateKeyValue($sql, $params) as $table => $remarks) {
            $tableOptions[$table] = ['comment' => $remarks];
        }

        return $tableOptions;
    }
}
