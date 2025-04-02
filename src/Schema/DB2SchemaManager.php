<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_change_key_case;
use function array_map;
use function implode;
use function preg_match;
use function sprintf;
use function str_replace;
use function strpos;
use function strtolower;
use function substr;

use const CASE_LOWER;

/**
 * IBM Db2 Schema Manager.
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
            'autoincrement'   => $tableColumn['generated'] === 'D',
            'notnull'         => $tableColumn['nulls'] === 'N',
        ];

        if ($tableColumn['remarks'] !== null) {
            $options['comment'] = $tableColumn['remarks'];
        }

        if ($scale !== null && $precision !== null) {
            $options['scale']     = $scale;
            $options['precision'] = $precision;
        }

        return new Column($tableColumn['colname'], Type::getType($type), $options);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableIndexesList(array $rows): array
    {
        return parent::_getPortableTableIndexesList(array_map(
            /** @param array<string, mixed> $row */
            static function (array $row): array {
                $row = array_change_key_case($row);

                return [
                    'key_name' => $row['indname'],
                    'type' => $row['uniquerule'] === 'U' ? IndexType::UNIQUE : IndexType::REGULAR,
                    'column_name' => $row['colname'],
                ];
            },
            $rows,
        ));
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableForeignKeysList(array $rows): array
    {
        $foreignKeys = [];

        foreach ($rows as $tableForeignKey) {
            $tableForeignKey = array_change_key_case($tableForeignKey, CASE_LOWER);

            if (! isset($foreignKeys[$tableForeignKey['constname']])) {
                $foreignKeys[$tableForeignKey['constname']] = [
                    'local' => [$tableForeignKey['local_column']],
                    'foreignTable' => $tableForeignKey['reftabname'],
                    'foreign' => [$tableForeignKey['foreign_column']],
                    'name' => $tableForeignKey['constname'],
                    'onUpdate' => match ($tableForeignKey['updaterule']) {
                        'R' => 'RESTRICT',
                        default => null,
                    },
                    'onDelete' => match ($tableForeignKey['deleterule']) {
                        'C' => 'CASCADE',
                        'N' => 'SET NULL',
                        'R' => 'RESTRICT',
                        default => null,
                    },
                ];
            } else {
                $foreignKeys[$tableForeignKey['constname']]['local'][]   = $tableForeignKey['local_column'];
                $foreignKeys[$tableForeignKey['constname']]['foreign'][] = $tableForeignKey['foreign_column'];
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

    protected function selectTableNames(string $databaseName): Result
    {
        $sql = sprintf(
            <<<'SQL'
                SELECT TABNAME AS %s
                FROM SYSCAT.TABLES
                WHERE TABSCHEMA = ?
                  AND TYPE = 'T'
                ORDER BY TABNAME
                SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
        );

        return $this->connection->executeQuery($sql, [$databaseName]);
    }

    protected function selectTableColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $conditions = ['C.TABSCHEMA = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[] = 'C.TABNAME = ?';
            $params[]     = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
SELECT
       C.TABNAME AS %s,
       C.COLNAME,
       C.TYPENAME,
       C.CODEPAGE,
       C.NULLS,
       C.LENGTH,
       C.SCALE,
       C.REMARKS,
       C.GENERATED,
       C.DEFAULT
FROM SYSCAT.COLUMNS C
         JOIN SYSCAT.TABLES AS T
              ON T.TABSCHEMA = C.TABSCHEMA
                  AND T.TABNAME = C.TABNAME
 WHERE %s
   AND T.TYPE = 'T'
ORDER BY C.TABNAME, C.COLNO
SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectIndexColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $conditions = ['IDX.TABSCHEMA = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[] = 'IDX.TABNAME = ?';
            $params[]     = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
      SELECT
             IDX.TABNAME AS %s,
             IDX.INDNAME,
             IDXCOL.COLNAME,
             IDX.UNIQUERULE
        FROM SYSCAT.INDEXES AS IDX
        JOIN SYSCAT.TABLES AS T
          ON IDX.TABSCHEMA = T.TABSCHEMA AND IDX.TABNAME = T.TABNAME
        JOIN SYSCAT.INDEXCOLUSE AS IDXCOL
          ON IDX.INDSCHEMA = IDXCOL.INDSCHEMA AND IDX.INDNAME = IDXCOL.INDNAME
       WHERE %s
         AND T.TYPE = 'T'
         AND IDX.UNIQUERULE != 'P'
    ORDER BY IDX.TABNAME,
             IDX.INDNAME,
             IDXCOL.COLSEQ
SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchPrimaryKeyConstraintColumns(
        string $databaseName,
        ?OptionallyQualifiedName $tableName = null,
    ): array {
        $conditions = ['TC.TABSCHEMA = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[] = 'TC.TABNAME = ?';
            $params[]     = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
            SELECT
                TC.TABNAME AS TABLE_NAME,
                TC.CONSTNAME AS CONSTRAINT_NAME,
                KCU.COLNAME AS COLUMN_NAME
            FROM
                SYSCAT.TABCONST TC
            INNER JOIN
                SYSCAT.KEYCOLUSE KCU
                ON KCU.TABSCHEMA = TC.TABSCHEMA
               AND KCU.TABNAME = TC.TABNAME
               AND KCU.CONSTNAME = TC.CONSTNAME
            WHERE %s
              AND TC.TYPE = 'P'
         ORDER BY TABLE_NAME,
                  KCU.COLSEQ
SQL,
            implode(' AND ', $conditions),
        );

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    protected function selectForeignKeyColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $conditions = ['R.TABSCHEMA = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[] = 'R.TABNAME = ?';
            $params[]     = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
      SELECT
             R.TABNAME AS %s,
             FKCOL.COLNAME AS LOCAL_COLUMN,
             R.REFTABNAME,
             PKCOL.COLNAME AS FOREIGN_COLUMN,
             R.CONSTNAME,
             R.UPDATERULE,
             R.DELETERULE
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
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchTableOptionsByTable(string $databaseName, ?OptionallyQualifiedName $tableName = null): array
    {
        $conditions = ['TABSCHEMA = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[] = 'TABNAME = ?';
            $params[]     = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
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
            $tableOptions[self::NULL_SCHEMA_KEY][$table] = ['comment' => $remarks];
        }

        return $tableOptions;
    }
}
