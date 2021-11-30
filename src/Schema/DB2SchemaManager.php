<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_change_key_case;
use function assert;
use function implode;
use function preg_match;
use function str_replace;
use function strpos;
use function strtolower;
use function substr;

use const CASE_LOWER;

/**
 * IBM Db2 Schema Manager.
 *
 * @extends AbstractSchemaManager<DB2Platform>
 */
class DB2SchemaManager extends AbstractSchemaManager
{
    /**
     * {@inheritdoc}
     *
     * Apparently creator is the schema not the user who created it:
     * {@link http://publib.boulder.ibm.com/infocenter/dzichelp/v2r2/index.jsp?topic=/com.ibm.db29.doc.sqlref/db2z_sysibmsystablestable.htm}
     */
    public function listTableNames()
    {
        $sql = $this->_platform->getListTablesSQL() . ' AND CREATOR = CURRENT_USER';

        $tables = $this->_conn->fetchAllAssociative($sql);

        return $this->filterAssetNames($this->_getPortableTablesList($tables));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        $length    = null;
        $fixed     = null;
        $scale     = false;
        $precision = false;

        $default = null;

        if ($tableColumn['default'] !== null && $tableColumn['default'] !== 'NULL') {
            $default = $tableColumn['default'];

            if (preg_match('/^\'(.*)\'$/s', $default, $matches) === 1) {
                $default = str_replace("''", "'", $matches[1]);
            }
        }

        $type = $this->_platform->getDoctrineTypeMapping($tableColumn['typename']);

        if (isset($tableColumn['comment'])) {
            $type                   = $this->extractDoctrineTypeFromComment($tableColumn['comment'], $type);
            $tableColumn['comment'] = $this->removeDoctrineTypeFromComment($tableColumn['comment'], $type);
        }

        switch (strtolower($tableColumn['typename'])) {
            case 'varchar':
                if ($tableColumn['codepage'] === 0) {
                    $type = Types::BINARY;
                }

                $length = $tableColumn['length'];
                $fixed  = false;
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
            'length'        => $length,
            'unsigned'      => false,
            'fixed'         => (bool) $fixed,
            'default'       => $default,
            'autoincrement' => (bool) $tableColumn['autoincrement'],
            'notnull'       => $tableColumn['nulls'] === 'N',
            'scale'         => null,
            'precision'     => null,
            'comment'       => isset($tableColumn['comment']) && $tableColumn['comment'] !== ''
                ? $tableColumn['comment']
                : null,
            'platformOptions' => [],
        ];

        if ($scale !== null && $precision !== null) {
            $options['scale']     = $scale;
            $options['precision'] = $precision;
        }

        return new Column($tableColumn['colname'], Type::getType($type), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTablesList($tables)
    {
        $tableNames = [];
        foreach ($tables as $tableRow) {
            $tableRow     = array_change_key_case($tableRow, CASE_LOWER);
            $tableNames[] = $tableRow['name'];
        }

        return $tableNames;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null)
    {
        foreach ($tableIndexes as &$tableIndexRow) {
            $tableIndexRow            = array_change_key_case($tableIndexRow, CASE_LOWER);
            $tableIndexRow['primary'] = (bool) $tableIndexRow['primary'];
        }

        return parent::_getPortableTableIndexesList($tableIndexes, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableForeignKeyDefinition($tableForeignKey)
    {
        return new ForeignKeyConstraint(
            $tableForeignKey['local_columns'],
            $tableForeignKey['foreign_table'],
            $tableForeignKey['foreign_columns'],
            $tableForeignKey['name'],
            $tableForeignKey['options']
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableForeignKeysList($tableForeignKeys)
    {
        $foreignKeys = [];

        foreach ($tableForeignKeys as $tableForeignKey) {
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
     * @param string $def
     *
     * @return string|null
     */
    protected function _getPortableForeignKeyRuleDef($def)
    {
        if ($def === 'C') {
            return 'CASCADE';
        }

        if ($def === 'N') {
            return 'SET NULL';
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableViewDefinition($view)
    {
        $view = array_change_key_case($view, CASE_LOWER);

        $sql = '';
        $pos = strpos($view['text'], ' AS ');

        if ($pos !== false) {
            $sql = substr($view['text'], $pos + 4);
        }

        return new View($view['name'], $sql);
    }

    /**
     * {@inheritdoc}
     */
    public function listTableDetails($name): Table
    {
        $currentDatabase = $this->_conn->getDatabase();

        assert($currentDatabase !== null);

        $options = [];
        $comment = $this->selectDatabaseTableComments($currentDatabase, $name)
            ->fetchOne();

        if ($comment !== false) {
            $options['comment'] = $comment;
        }

        return new Table(
            $name,
            $this->_getPortableTableColumnList(
                $name,
                $currentDatabase,
                $this->selectDatabaseColumns($currentDatabase, $name)
                    ->fetchAllAssociative()
            ),
            $this->_getPortableTableIndexesList(
                $this->selectDatabaseIndexes($currentDatabase, $name)
                    ->fetchAllAssociative(),
                $name
            ),
            [],
            $this->_getPortableTableForeignKeysList(
                $this->selectDatabaseForeignKeys($currentDatabase, $name)
                    ->fetchAllAssociative()
            ),
            $options
        );
    }

    /**
     * {@inheritdoc}
     */
    public function listTables()
    {
        $currentDatabase = $this->_conn->getDatabase();

        assert($currentDatabase !== null);

        /** @var array<string,list<array<string,mixed>>> $columns */
        $columns = $this->selectDatabaseColumns($currentDatabase)
            ->fetchAllAssociativeGrouped();

        $indexes = $this->selectDatabaseIndexes($currentDatabase)
            ->fetchAllAssociativeGrouped();

        $foreignKeys = $this->selectDatabaseForeignKeys($currentDatabase)
            ->fetchAllAssociativeGrouped();

        $comments = $this->selectDatabaseTableComments($currentDatabase)
            ->fetchAllKeyValue();

        $tables = [];

        foreach ($columns as $tableName => $tableColumns) {
            $options = [];

            if (isset($comments[$tableName])) {
                $options['comment'] = $comments[$tableName];
            }

            $tables[] = new Table(
                $tableName,
                $this->_getPortableTableColumnList($tableName, $currentDatabase, $tableColumns),
                $this->_getPortableTableIndexesList($indexes[$tableName] ?? [], $tableName),
                [],
                $this->_getPortableTableForeignKeysList($foreignKeys[$tableName] ?? []),
                $options
            );
        }

        return $tables;
    }

    /**
     * Selects column definitions of the tables in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * @throws Exception
     */
    private function selectDatabaseColumns(string $databaseName, ?string $tableName = null): Result
    {
        // We do the funky subquery and join syscat.columns.default this crazy way because
        // as of db2 v10, the column is CLOB(64k) and the distinct operator won't allow a CLOB,
        // it wants shorter stuff like a varchar.

        $sql = 'SELECT';

        if ($tableName === null) {
            $sql .= ' SUBQ.TABNAME,';
        }

        $sql .= <<<'SQL'
             COLS.DEFAULT,
             SUBQ.*
        FROM (
               SELECT DISTINCT
                 C.TABSCHEMA,
                 C.TABNAME,
                 C.COLNAME,
                 C.COLNO,
                 C.TYPENAME,
                 C.CODEPAGE,
                 C.NULLS,
                 C.LENGTH,
                 C.SCALE,
                 C.IDENTITY,
                 TC.TYPE AS TABCONSTTYPE,
                 C.REMARKS AS COMMENT,
                 K.COLSEQ,
                 CASE
                 WHEN C.GENERATED = 'D' THEN 1
                 ELSE 0
                 END     AS AUTOINCREMENT
               FROM SYSCAT.COLUMNS C
               JOIN SYSCAT.TABLES AS T
                 ON C.TABSCHEMA = T.TABSCHEMA AND C.TABNAME = T.TABNAME
          LEFT JOIN (SYSCAT.KEYCOLUSE K
                       JOIN SYSCAT.TABCONST TC
                         ON (K.TABSCHEMA = TC.TABSCHEMA AND K.TABNAME = TC.TABNAME AND TC.TYPE = 'P')
                    )
                 ON (C.TABSCHEMA = K.TABSCHEMA AND C.TABNAME = K.TABNAME AND C.COLNAME = K.COLNAME)
SQL;

        $conditions = ['T.TYPE = \'T\'', 'T.TABSCHEMA <> \'SYSIBMTS\'', 'T.OWNER = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $conditions[] = 'UPPER(c.tabname) = UPPER(?)';
            $params[]     = $tableName;
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions);

        $sql .= <<<'SQL'
               ORDER BY C.COLNO
             ) SUBQ
          JOIN SYSCAT.COLUMNS COLS
            ON SUBQ.TABSCHEMA = COLS.TABSCHEMA
               AND SUBQ.TABNAME = COLS.TABNAME
               AND SUBQ.COLNO = COLS.COLNO
        ORDER BY SUBQ.COLNO
SQL;

        return $this->_conn->executeQuery($sql, $params);
    }

    /**
     * Selects index definitions of the tables in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * @throws Exception
     */
    private function selectDatabaseIndexes(string $databaseName, ?string $tableName = null): Result
    {
        $sql = 'SELECT';

        if ($tableName === null) {
            $sql .= ' IDX.TABNAME,';
        }

        $sql .= <<<'SQL'
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
SQL;

        $conditions = ['T.TYPE = \'T\'', 'T.TABSCHEMA <> \'SYSIBMTS\'', 'T.OWNER = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $conditions[] = 'UPPER(T.TABNAME) = UPPER(?)';
            $params[]     = $tableName;
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions);

        $sql .= ' ORDER BY IDX.INDNAME, IDXCOL.COLSEQ ASC';

        return $this->_conn->executeQuery($sql, $params);
    }

    /**
     * Selects foreign key definitions of the tables in the specified database. If the table name is specified,
     * narrows down the selection to this table.
     *
     * @throws Exception
     */
    private function selectDatabaseForeignKeys(string $databaseName, ?string $tableName = null): Result
    {
        $sql = 'SELECT';

        if ($tableName === null) {
            $sql .= ' FK.REFTABNAME,';
        }

        $sql .= <<<'SQL'
             FKCOL.COLNAME AS LOCAL_COLUMN,
             FK.REFTABNAME AS FOREIGN_TABLE,
             PKCOL.COLNAME AS FOREIGN_COLUMN,
             FK.CONSTNAME AS INDEX_NAME,
             CASE
                 WHEN FK.UPDATERULE = 'R' THEN 'RESTRICT'
                 ELSE NULL
             END AS ON_UPDATE,
             CASE
                 WHEN FK.DELETERULE = 'C' THEN 'CASCADE'
                 WHEN FK.DELETERULE = 'N' THEN 'SET NULL'
                 WHEN FK.DELETERULE = 'R' THEN 'RESTRICT'
                 ELSE NULL
             END AS ON_DELETE
        FROM SYSCAT.REFERENCES AS FK
        JOIN SYSCAT.TABLES AS T
          ON FK.TABSCHEMA = T.TABSCHEMA AND FK.TABNAME = T.TABNAME
        JOIN SYSCAT.KEYCOLUSE AS FKCOL
          ON FK.CONSTNAME = FKCOL.CONSTNAME AND FK.TABSCHEMA = FKCOL.TABSCHEMA AND FK.TABNAME = FKCOL.TABNAME
        JOIN SYSCAT.KEYCOLUSE AS PKCOL
          ON FK.REFKEYNAME = PKCOL.CONSTNAME AND FK.REFTABSCHEMA = PKCOL.TABSCHEMA AND FK.REFTABNAME = PKCOL.TABNAME
SQL;

        $conditions = ['T.TYPE = \'T\'', 'T.TABSCHEMA <> \'SYSIBMTS\'', 'T.OWNER = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $conditions[] = 'UPPER(T.TABNAME) = UPPER(?)';
            $params[]     = $tableName;
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions);

        $sql .= ' ORDER BY FK.CONSTNAME, FKCOL.COLSEQ ASC';

        return $this->_conn->executeQuery($sql, $params);
    }

    /**
     * Selects comments of the tables in the specified database. If the table name is specified, narrows down the
     * selection to this table.
     *
     * @throws Exception
     */
    private function selectDatabaseTableComments(string $databaseName, ?string $tableName = null): Result
    {
        $sql = 'SELECT';

        if ($tableName === null) {
            $sql .= ' NAME,';
        }

        $sql .= ' REMARKS';

        $conditions = [];
        $params     = [];

        if ($tableName !== null) {
            $conditions[] = 'NAME = UPPER(?)';
            $params[]     = $tableName;
        }

        $sql .= ' FROM SYSIBM.SYSTABLES';

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        return $this->_conn->executeQuery($sql, $params);
    }
}
