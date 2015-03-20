<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * IBMi Db2 Schema Manager.
 * More documentation about iSeries schema at https://www-01.ibm.com/support/knowledgecenter/ssw_ibm_i_72/db2/rbafzcatsqlcolumns.htm
 *
 * @author Cassiano Vailati <c.vailati@esconsulting.it>
 */
class DB2iSeriesPlatform extends AbstractPlatform
{
    /**
     * {@inheritdoc}
     */
    public function getBinaryMaxLength()
    {
        return 32704;
    }

    /**
     * {@inheritdoc}
     */
    public function getBinaryDefaultLength()
    {
        return 1;
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        // todo blob(n) with $field['length'];
        return 'BLOB(1M)';
    }

    /**
     * {@inheritDoc}
     */
    public function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = array(
            'smallint'      => 'smallint',
            'bigint'        => 'bigint',
            'integer'       => 'integer',
            'rowid'         => 'integer',
            'time'          => 'time',
            'date'          => 'date',
            'varchar'       => 'string',
            'character'     => 'string',
            'char'          => 'string',
            'nvarchar'          => 'string',
            'nchar'          => 'string',
            'char () for bit data' => 'string',
            'varchar () for bit data' => 'string',
            'varg'          => 'string',
            'vargraphic'          => 'string',
            'graphic'       => 'string',
            'varbinary'     => 'binary',
            'binary'        => 'binary',
            'varbin'        => 'binary',
            'clob'          => 'text',
            'nclob'          => 'text',
            'dbclob'        => 'text',
            'blob'          => 'blob',
            'decimal'       => 'decimal',
            'numeric'       => 'float',
            'double'        => 'float',
            'real'          => 'float',
            'float'         => 'float',
            'timestamp'     => 'datetime',
            'timestmp'      => 'datetime',
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? ($length ? 'CHAR(' . $length . ')' : 'CHAR(255)')
            : ($length ? 'VARCHAR(' . $length . ')' : 'VARCHAR(255)');
    }

    /**
     * {@inheritdoc}
     */
    protected function getBinaryTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? 'BINARY(' . ($length ?: 255) . ')' : 'VARBINARY(' . ($length ?: 255) . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
        // todo clob(n) with $field['length'];
        return 'CLOB(1M)';
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'db2';
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $columnDef)
    {
        return 'SMALLINT';
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $columnDef)
    {
        return 'INTEGER' . $this->_getCommonIntegerTypeDeclarationSQL($columnDef);
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $columnDef)
    {
        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($columnDef);
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $columnDef)
    {
        return 'SMALLINT' . $this->_getCommonIntegerTypeDeclarationSQL($columnDef);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        $autoinc = '';
        if ( ! empty($columnDef['autoincrement'])) {
            $autoinc = ' GENERATED BY DEFAULT AS IDENTITY';
        }

        return $autoinc;
    }

    /**
     * {@inheritdoc}
     */
    public function getBitAndComparisonExpression($value1, $value2)
    {
        return 'BITAND(' . $value1 . ', ' . $value2 . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getBitOrComparisonExpression($value1, $value2)
    {
        return 'BITOR(' . $value1 . ', ' . $value2 . ')';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateArithmeticIntervalExpression($date, $operator, $interval, $unit)
    {
        switch ($unit) {
            case self::DATE_INTERVAL_UNIT_WEEK:
                $interval *= 7;
                $unit = self::DATE_INTERVAL_UNIT_DAY;
                break;

            case self::DATE_INTERVAL_UNIT_QUARTER:
                $interval *= 3;
                $unit = self::DATE_INTERVAL_UNIT_MONTH;
                break;
        }

        return $date . ' ' . $operator . ' ' . $interval . ' ' . $unit;
    }

    /**
     * {@inheritdoc}
     */
    public function getDateDiffExpression($date1, $date2)
    {
        return 'DAYS(' . $date1 . ') - DAYS(' . $date2 . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        if (isset($fieldDeclaration['version']) && $fieldDeclaration['version'] == true) {
            return "TIMESTAMP(0) WITH DEFAULT";
        }

        return 'TIMESTAMP(0)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'TIME';
    }

    /**
     * {@inheritdoc}
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        return 'TRUNCATE ' . $tableName . ' IMMEDIATE';
    }

    /**
     * This code fragment is originally from the Zend_Db_Adapter_Db2 class, but has been edited.
     *
     * @license New BSD License
     *
     * @param string $table
     * @param string $database
     *
     * @return string
     */
    public function getListTableColumnsSQL($table, $database = null)
    {
        return "
            SELECT DISTINCT
               c.column_def as default,
               c.table_schem as tabschema,
               c.table_name as tabname,
               c.column_name as colname,
               c.ordinal_position as colno,
               c.type_name as typename,
               c.is_nullable as nulls,
               c.column_size as length,
               c.decimal_digits as scale,
               CASE
                   WHEN c.pseudo_column = 2 THEN 'YES'
                   ELSE 'NO'
               END as identity,
               pk.constraint_type AS tabconsttype,
               pk.key_seq as colseq,
               CASE
                   WHEN c.HAS_DEFAULT = 'J' THEN 1
                   ELSE 0
               END AS autoincrement
             FROM SYSIBM.sqlcolumns as c
             LEFT JOIN
             (
                SELECT
                tc.TABLE_SCHEMA,
                tc.TABLE_NAME,
                tc.CONSTRAINT_TYPE,
                spk.COLUMN_NAME,
                spk.KEY_SEQ
                FROM SYSIBM.TABLE_CONSTRAINTS tc
                LEFT JOIN SYSIBM.SQLPRIMARYKEYS spk
                    ON tc.CONSTRAINT_NAME = spk.PK_NAME AND tc.TABLE_SCHEMA = spk.TABLE_SCHEM AND tc.TABLE_NAME = spk.TABLE_NAME
                WHERE CONSTRAINT_TYPE = 'PRIMARY KEY'
                AND UPPER(tc.TABLE_NAME) = UPPER('" . $table . "')
                ". (!is_null($database) ? "AND tc.TABLE_SCHEMA = UPPER('$database')" : '') ."
             ) pk ON
                c.TABLE_SCHEM = pk.TABLE_SCHEMA
                AND c.TABLE_NAME = pk.TABLE_NAME
                AND c.COLUMN_NAME = pk.COLUMN_NAME
             WHERE
                UPPER(c.TABLE_NAME) = UPPER('" . $table . "')
                ". (!is_null($database) ? "AND c.TABLE_SCHEM = UPPER('$database')" : '') ."
             ORDER BY c.ordinal_position
        ";
    }

    /**
     * {@inheritDoc}
     */
    public function getListTablesSQL($database = null)
    {
        return "
            SELECT
              DISTINCT NAME
            FROM
                SYSIBM.tables t
            WHERE
              table_type='BASE TABLE'
              ". (!is_null($database) ? "AND t.TABLE_SCHEMA = UPPER('$database')" : '') ."
            ORDER BY NAME
        ";
    }

    /**
     * {@inheritDoc}
     */
    public function getListViewsSQL($database = null)
    {
        return "
            SELECT
              DISTINCT NAME,
              TEXT
            FROM QSYS2.sysviews v
            WHERE 1=1
            ". (!is_null($database) ? "AND v.TABLE_SCHEMA = UPPER('$database')" : '') ."
            ORDER BY NAME
        ";
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableIndexesSQL($table, $database = null)
    {
        return  "
            SELECT
                  scc.CONSTRAINT_NAME as key_name,
                  scc.COLUMN_NAME as column_name,
                  CASE
                      WHEN sc.CONSTRAINT_TYPE = 'PRIMARY KEY' THEN 1
                      ELSE 0
                  END AS primary,
                  CASE
                      WHEN sc.CONSTRAINT_TYPE = 'UNIQUE' THEN 0
                      ELSE 1
                  END AS non_unique
              FROM
              QSYS2.syscstcol scc
              LEFT JOIN QSYS2.syscst sc ON
                  scc.TABLE_SCHEMA = sc.TABLE_SCHEMA AND scc.TABLE_NAME = sc.TABLE_NAME AND scc.CONSTRAINT_NAME = sc.CONSTRAINT_NAME
            WHERE scc.TABLE_NAME = UPPER('" . $table . "')
            ". (!is_null($database) ? "AND scc.TABLE_SCHEMA = UPPER('$database')" : '') ."
        ";
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableForeignKeysSQL($table)
    {
        return "
            SELECT DISTINCT
                fk.COLUMN_NAME AS local_column,
                pk.TABLE_NAME AS foreign_table,
                pk.COLUMN_NAME AS foreign_column,
                fk.CONSTRAINT_NAME AS index_name,
                rc.UPDATE_RULE AS on_update,
                rc.DELETE_RULE AS on_delete
            FROM QSYS2.REFERENTIAL_CONSTRAINTS rc
            LEFT JOIN QSYS2.SYSCSTCOL fk ON
                rc.CONSTRAINT_SCHEMA = fk.CONSTRAINT_SCHEMA AND
                rc.CONSTRAINT_NAME = fk.CONSTRAINT_NAME
            LEFT JOIN QSYS2.SYSCSTCOL pk ON
                rc.UNIQUE_CONSTRAINT_SCHEMA = pk.CONSTRAINT_SCHEMA AND
                rc.UNIQUE_CONSTRAINT_NAME = pk.CONSTRAINT_NAME
            WHERE fk.TABLE_NAME = UPPER('" . $table . "')
        ";
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateViewSQL($name, $sql)
    {
        return "CREATE VIEW ".$name." AS ".$sql;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropViewSQL($name)
    {
        return "DROP VIEW ".$name;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateDatabaseSQL($database)
    {
        return "CREATE COLLECTION ".$database;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropDatabaseSQL($database)
    {
        return "DROP DATABASE " . $database;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsCreateDropDatabase()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsReleaseSavepoints()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentDateSQL()
    {
        return 'CURRENT DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentTimeSQL()
    {
        return 'CURRENT TIME';
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentTimestampSQL()
    {
        return "CURRENT TIMESTAMP";
    }

    /**
     * {@inheritDoc}
     */
    public function getIndexDeclarationSQL($name, Index $index)
    {
        // Index declaration in statements like CREATE TABLE is not supported.
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = array())
    {
        $indexes = array();
        if (isset($options['indexes'])) {
            $indexes = $options['indexes'];
        }
        $options['indexes'] = array();

        $sqls = parent::_getCreateTableSQL($tableName, $columns, $options);

        foreach ($indexes as $definition) {
            $sqls[] = $this->getCreateIndexSQL($definition, $tableName);
        }
        return $sqls;
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $sql = array();
        $columnSql = array();
        $commentsSQL = array();

        $queryParts = array();
        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $columnDef = $column->toArray();
            $queryPart = 'ADD COLUMN ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnDef);

            // Adding non-nullable columns to a table requires a default value to be specified.
            if ( ! empty($columnDef['notnull']) &&
                ! isset($columnDef['default']) &&
                empty($columnDef['autoincrement'])
            ) {
                $queryPart .= ' WITH DEFAULT';
            }

            $queryParts[] = $queryPart;

            $comment = $this->getColumnComment($column);

            if (null !== $comment && '' !== $comment) {
                $commentsSQL[] = $this->getCommentOnColumnSQL(
                    $diff->getName($this)->getQuotedName($this),
                    $column->getQuotedName($this),
                    $comment
                );
            }
        }

        foreach ($diff->removedColumns as $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $queryParts[] =  'DROP COLUMN ' . $column->getQuotedName($this);
        }

        foreach ($diff->changedColumns as $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            if ($columnDiff->hasChanged('comment')) {
                $commentsSQL[] = $this->getCommentOnColumnSQL(
                    $diff->getName($this)->getQuotedName($this),
                    $columnDiff->column->getQuotedName($this),
                    $this->getColumnComment($columnDiff->column)
                );

                if (count($columnDiff->changedProperties) === 1) {
                    continue;
                }
            }

            $this->gatherAlterColumnSQL($diff->fromTable, $columnDiff, $sql, $queryParts);
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = new Identifier($oldColumnName);

            $queryParts[] =  'RENAME COLUMN ' . $oldColumnName->getQuotedName($this) .
                ' TO ' . $column->getQuotedName($this);
        }

        $tableSql = array();

        if ( ! $this->onSchemaAlterTable($diff, $tableSql)) {
            if (count($queryParts) > 0) {
                $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . implode(" ", $queryParts);
            }

            // Some table alteration operations require a table reorganization.
            if ( ! empty($diff->removedColumns) || ! empty($diff->changedColumns)) {
                $sql[] = "CALL SYSPROC.ADMIN_CMD ('REORG TABLE " . $diff->getName($this)->getQuotedName($this) . "')";
            }

            $sql = array_merge($sql, $commentsSQL);

            if ($diff->newName !== false) {
                $sql[] =  'RENAME TABLE ' . $diff->getName($this)->getQuotedName($this) . ' TO ' . $diff->getNewName()->getQuotedName($this);
            }

            $sql = array_merge(
                $this->getPreAlterTableIndexForeignKeySQL($diff),
                $sql,
                $this->getPostAlterTableIndexForeignKeySQL($diff)
            );
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * Gathers the table alteration SQL for a given column diff.
     *
     * @param Table      $table      The table to gather the SQL for.
     * @param ColumnDiff $columnDiff The column diff to evaluate.
     * @param array      $sql        The sequence of table alteration statements to fill.
     * @param array      $queryParts The sequence of column alteration clauses to fill.
     */
    private function gatherAlterColumnSQL(Table $table, ColumnDiff $columnDiff, array &$sql, array &$queryParts)
    {
        $alterColumnClauses = $this->getAlterColumnClausesSQL($columnDiff);

        if (empty($alterColumnClauses)) {
            return;
        }

        // If we have a single column alteration, we can append the clause to the main query.
        if (count($alterColumnClauses) === 1) {
            $queryParts[] = current($alterColumnClauses);

            return;
        }

        // We have multiple alterations for the same column,
        // so we need to trigger a complete ALTER TABLE statement
        // for each ALTER COLUMN clause.
        foreach ($alterColumnClauses as $alterColumnClause) {
            $sql[] = 'ALTER TABLE ' . $table->getQuotedName($this) . ' ' . $alterColumnClause;
        }
    }

    /**
     * Returns the ALTER COLUMN SQL clauses for altering a column described by the given column diff.
     *
     * @param ColumnDiff $columnDiff The column diff to evaluate.
     *
     * @return array
     */
    private function getAlterColumnClausesSQL(ColumnDiff $columnDiff)
    {
        $column = $columnDiff->column->toArray();

        $alterClause = 'ALTER COLUMN ' . $columnDiff->column->getQuotedName($this);

        if ($column['columnDefinition']) {
            return array($alterClause . ' ' . $column['columnDefinition']);
        }

        $clauses = array();

        if ($columnDiff->hasChanged('type') ||
            $columnDiff->hasChanged('length') ||
            $columnDiff->hasChanged('precision') ||
            $columnDiff->hasChanged('scale') ||
            $columnDiff->hasChanged('fixed')
        ) {
            $clauses[] = $alterClause . ' SET DATA TYPE ' . $column['type']->getSQLDeclaration($column, $this);
        }

        if ($columnDiff->hasChanged('notnull')) {
            $clauses[] = $column['notnull'] ? $alterClause . ' SET NOT NULL' : $alterClause . ' DROP NOT NULL';
        }

        if ($columnDiff->hasChanged('default')) {
            if (isset($column['default'])) {
                $defaultClause = $this->getDefaultValueDeclarationSQL($column);

                if ($defaultClause) {
                    $clauses[] = $alterClause . ' SET' . $defaultClause;
                }
            } else {
                $clauses[] = $alterClause . ' DROP DEFAULT';
            }
        }

        return $clauses;
    }

    /**
     * {@inheritDoc}
     */
    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff)
    {
        $sql = array();
        $table = $diff->getName($this)->getQuotedName($this);

        foreach ($diff->removedIndexes as $remKey => $remIndex) {
            foreach ($diff->addedIndexes as $addKey => $addIndex) {
                if ($remIndex->getColumns() == $addIndex->getColumns()) {
                    if ($remIndex->isPrimary()) {
                        $sql[] = 'ALTER TABLE ' . $table . ' DROP PRIMARY KEY';
                    } elseif ($remIndex->isUnique()) {
                        $sql[] = 'ALTER TABLE ' . $table . ' DROP UNIQUE ' . $remIndex->getQuotedName($this);
                    } else {
                        $sql[] = $this->getDropIndexSQL($remIndex, $table);
                    }

                    $sql[] = $this->getCreateIndexSQL($addIndex, $table);

                    unset($diff->removedIndexes[$remKey]);
                    unset($diff->addedIndexes[$addKey]);

                    break;
                }
            }
        }

        $sql = array_merge($sql, parent::getPreAlterTableIndexForeignKeySQL($diff));

        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameIndexSQL($oldIndexName, Index $index, $tableName)
    {
        if (strpos($tableName, '.') !== false) {
            list($schema) = explode('.', $tableName);
            $oldIndexName = $schema . '.' . $oldIndexName;
        }

        return array('RENAME INDEX ' . $oldIndexName . ' TO ' . $index->getQuotedName($this));
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultValueDeclarationSQL($field)
    {
        if ( ! empty($field['autoincrement'])) {
            return '';
        }

        if (isset($field['version']) && $field['version']) {
            if ((string) $field['type'] != "DateTime") {
                $field['default'] = "1";
            }
        }

        return parent::getDefaultValueDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getEmptyIdentityInsertSQL($tableName, $identifierColumnName)
    {
        return 'INSERT INTO ' . $tableName . ' (' . $identifierColumnName . ') VALUES (DEFAULT)';
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTemporaryTableSnippetSQL()
    {
        return "DECLARE GLOBAL TEMPORARY TABLE";
    }

    /**
     * {@inheritDoc}
     */
    public function getTemporaryTableName($tableName)
    {
        return "SESSION." . $tableName;
    }

    /**
     * {@inheritDoc}
     */
    protected function doModifyLimitQuery($query, $limit, $offset = null)
    {
        if ($limit === null && $offset === null) {
            return $query;
        }

        $limit = (int) $limit;
        $offset = (int) (($offset)?:0);

        $orderBy = stristr($query, 'ORDER BY');

        $orderByBlocks = preg_split('/\s*ORDER\s+BY/', $orderBy );

        //Reversing arrays beacause external order by is more important
        $orderByBlocks = array_reverse($orderByBlocks);

        //Splitting ORDER BY
        $orderByParts = array();
        foreach($orderByBlocks as $orderByBlock){
            $blockArray   = explode(',', $orderByBlock);
            foreach($blockArray as $block){
                $block = trim($block);
                if(!empty($block)) {
                    $orderByParts[] = $block;
                }
            }
        }

        //Clear ORDER BY
        foreach ($orderByParts as &$orderByPart) {

            $orderByPart = preg_replace('/ORDER\s+BY\s+([^\)]*)(.*)/', '$1', 'ORDER BY '.$orderByPart);
        }

        $orderByColumns = array();

        //Split ORDER BY into parts
        foreach ($orderByParts as &$part) {

            if (preg_match('/(([^\s]*)\.)?([^\.\s]*)\s*(ASC|DESC)?/i', trim($part), $matches)) {
                $orderByColumns[] = array(
                    'column'    => $matches[3],
                    'hasTable'  => ( ! empty($matches[2])),
                    'sort'      => isset($matches[4]) ? $matches[4] : null,
                    'table'     => empty($matches[2]) ? '[^\.\s]*' : $matches[2]
                );
            }
        }

        //Find alias for each colum used in ORDER BY
        if ( ! empty($orderByColumns)) {
            foreach ($orderByColumns as $column) {

                $pattern = sprintf('/%s\.%s\s+(?:AS\s+)?([^,\s)]+)/i', $column['table'], $column['column']);
                $overColumn = preg_match($pattern, $query, $matches)
                    ? ($column['hasTable'] ? $column['table']  . '.' : '') . $column['column']
                    : $column['column'];

                if (isset($column['sort'])) {
                    $overColumn .= ' ' . $column['sort'];
                }

                $overColumns[] = $overColumn;
            }
        }

        //Replace only first occurrence of FROM with $over to prevent changing FROM also in subqueries.
        if ( ! $orderBy) {
            $over = '';
        }
        else
        {
            $over  = 'ORDER BY ' . implode(', ', $overColumns);
        }

        $sql = 'SELECT DOCTRINE_TBL.* FROM (SELECT ROW_NUMBER() OVER('.$over.') AS DOCTRINE_ROWNUM, DOCTRINE_TBL1.* '.
            'FROM (' . $query . ') DOCTRINE_TBL1) DOCTRINE_TBL WHERE DOCTRINE_TBL.DOCTRINE_ROWNUM BETWEEN ' . ($offset+1) .' AND ' . ($offset+$limit);

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        if ($startPos == false) {
            return 'LOCATE(' . $substr . ', ' . $str . ')';
        }

        return 'LOCATE(' . $substr . ', ' . $str . ', '.$startPos.')';
    }

    /**
     * {@inheritDoc}
     */
    public function getSubstringExpression($value, $from, $length = null)
    {
        if ($length === null) {
            return 'SUBSTR(' . $value . ', ' . $from . ')';
        }

        return 'SUBSTR(' . $value . ', ' . $from . ', ' . $length . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function supportsIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function prefersIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * DB2 returns all column names in SQL result sets in uppercase.
     */
    public function getSQLResultCasing($column)
    {
        return strtoupper($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getForUpdateSQL()
    {
        return ' WITH RR USE AND KEEP UPDATE LOCKS';
    }

    /**
     * {@inheritDoc}
     */
    public function getDummySelectSQL()
    {
        return 'SELECT 1 FROM sysibm.sysdummy1';
    }

    /**
     * {@inheritDoc}
     *
     * DB2 supports savepoints, but they work semantically different than on other vendor platforms.
     *
     * TODO: We have to investigate how to get DB2 up and running with savepoints.
     */
    public function supportsSavepoints()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\DB2Keywords';
    }
}
