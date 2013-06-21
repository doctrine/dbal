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

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\DBALException;

/**
 * OraclePlatform.
 *
 * @since 2.0
 * @author Roman Borschel <roman@code-factory.org>
 * @author Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class OraclePlatform extends AbstractPlatform
{
    /**
     * Assertion for Oracle identifiers.
     *
     * @link http://docs.oracle.com/cd/B19306_01/server.102/b14200/sql_elements008.htm
     *
     * @param string $identifier
     *
     * @throws DBALException
     */
    static public function assertValidIdentifier($identifier)
    {
        if ( ! preg_match('(^(([a-zA-Z]{1}[a-zA-Z0-9_$#]{0,})|("[^"]+"))$)', $identifier)) {
            throw new DBALException("Invalid Oracle identifier");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSubstringExpression($value, $position, $length = null)
    {
        if ($length !== null) {
            return "SUBSTR($value, $position, $length)";
        }

        return "SUBSTR($value, $position)";
    }

    /**
     * {@inheritDoc}
     */
    public function getNowExpression($type = 'timestamp')
    {
        switch ($type) {
            case 'date':
            case 'time':
            case 'timestamp':
            default:
                return 'TO_CHAR(CURRENT_TIMESTAMP, \'YYYY-MM-DD HH24:MI:SS\')';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        if ($startPos == false) {
            return 'INSTR('.$str.', '.$substr.')';
        }

        return 'INSTR('.$str.', '.$substr.', '.$startPos.')';
    }

    /**
     * {@inheritDoc}
     */
    public function getGuidExpression()
    {
        return 'SYS_GUID()';
    }

    /**
     * {@inheritDoc}
     *
     * Note: Since Oracle timestamp differences are calculated down to the microsecond we have to truncate
     * them to the difference in days. This is obviously a restriction of the original functionality, but we
     * need to make this a portable function.
     */
    public function getDateDiffExpression($date1, $date2)
    {
        return "TRUNC(TO_NUMBER(SUBSTR((" . $date1 . "-" . $date2 . "), 1, INSTR(" . $date1 . "-" . $date2 .", ' '))))";
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddDaysExpression($date, $days)
    {
        return '(' . $date . '+' . $days . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubDaysExpression($date, $days)
    {
        return '(' . $date . '-' . $days . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddMonthExpression($date, $months)
    {
        return "ADD_MONTHS(" . $date . ", " . $months . ")";
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubMonthExpression($date, $months)
    {
        return "ADD_MONTHS(" . $date . ", -" . $months . ")";
    }

    /**
     * {@inheritDoc}
     */
    public function getBitAndComparisonExpression($value1, $value2)
    {
        return 'BITAND('.$value1 . ', ' . $value2 . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getBitOrComparisonExpression($value1, $value2)
    {
        return '(' . $value1 . '-' .
                $this->getBitAndComparisonExpression($value1, $value2)
                . '+' . $value2 . ')';
    }

    /**
     * {@inheritDoc}
     *
     * Need to specifiy minvalue, since start with is hidden in the system and MINVALUE <= START WITH.
     * Therefore we can use MINVALUE to be able to get a hint what START WITH was for later introspection
     * in {@see listSequences()}
     */
    public function getCreateSequenceSQL(Sequence $sequence)
    {
        return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this) .
               ' START WITH ' . $sequence->getInitialValue() .
               ' MINVALUE ' . $sequence->getInitialValue() .
               ' INCREMENT BY ' . $sequence->getAllocationSize();
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterSequenceSQL(\Doctrine\DBAL\Schema\Sequence $sequence)
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this) .
               ' INCREMENT BY ' . $sequence->getAllocationSize();
    }

    /**
     * {@inheritDoc}
     */
    public function getSequenceNextValSQL($sequenceName)
    {
        return 'SELECT ' . $sequenceName . '.nextval FROM DUAL';
    }

    /**
     * {@inheritDoc}
     */
    public function getSetTransactionIsolationSQL($level)
    {
        return 'SET TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getTransactionIsolationLevelSQL($level)
    {
        switch ($level) {
            case \Doctrine\DBAL\Connection::TRANSACTION_READ_UNCOMMITTED:
                return 'READ UNCOMMITTED';
            case \Doctrine\DBAL\Connection::TRANSACTION_READ_COMMITTED:
                return 'READ COMMITTED';
            case \Doctrine\DBAL\Connection::TRANSACTION_REPEATABLE_READ:
            case \Doctrine\DBAL\Connection::TRANSACTION_SERIALIZABLE:
                return 'SERIALIZABLE';
            default:
                return parent::_getTransactionIsolationLevelSQL($level);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return 'NUMBER(1)';
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $field)
    {
        return 'NUMBER(10)';
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
        return 'NUMBER(20)';
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return 'NUMBER(5)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'TIMESTAMP(0)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'TIMESTAMP(0) WITH TIME ZONE';
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
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? ($length ? 'CHAR(' . $length . ')' : 'CHAR(2000)')
                : ($length ? 'VARCHAR2(' . $length . ')' : 'VARCHAR2(4000)');
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
        return 'CLOB';
    }

    /**
     * {@inheritDoc}
     */
    public function getListDatabasesSQL()
    {
        return 'SELECT username FROM all_users';
    }

    /**
     * {@inheritDoc}
     */
    public function getListSequencesSQL($database)
    {
        return "SELECT sequence_name, min_value, increment_by FROM sys.all_sequences ".
               "WHERE SEQUENCE_OWNER = '".strtoupper($database)."'";
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL($table, array $columns, array $options = array())
    {
        $indexes = isset($options['indexes']) ? $options['indexes'] : array();
        $options['indexes'] = array();
        $sql = parent::_getCreateTableSQL($table, $columns, $options);

        foreach ($columns as $name => $column) {
            if (isset($column['sequence'])) {
                $sql[] = $this->getCreateSequenceSQL($column['sequence'], 1);
            }

            if (isset($column['autoincrement']) && $column['autoincrement'] ||
               (isset($column['autoinc']) && $column['autoinc'])) {
                $sql = array_merge($sql, $this->getCreateAutoincrementSql($name, $table));
            }
        }

        if (isset($indexes) && ! empty($indexes)) {
            foreach ($indexes as $index) {
                $sql[] = $this->getCreateIndexSQL($index, $table);
            }
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     *
     * @license New BSD License
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaOracleReader.html
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        $table = strtoupper($table);

        return "SELECT uind.index_name AS name, " .
             "       uind.index_type AS type, " .
             "       decode( uind.uniqueness, 'NONUNIQUE', 0, 'UNIQUE', 1 ) AS is_unique, " .
             "       uind_col.column_name AS column_name, " .
             "       uind_col.column_position AS column_pos, " .
             "       (SELECT ucon.constraint_type FROM user_constraints ucon WHERE ucon.constraint_name = uind.index_name) AS is_primary ".
             "FROM user_indexes uind, user_ind_columns uind_col " .
             "WHERE uind.index_name = uind_col.index_name AND uind_col.table_name = '$table' ORDER BY uind_col.column_position ASC";
    }

    /**
     * {@inheritDoc}
     */
    public function getListTablesSQL()
    {
        return 'SELECT * FROM sys.user_tables';
    }

    /**
     * {@inheritDoc}
     */
    public function getListViewsSQL($database)
    {
        return 'SELECT view_name, text FROM sys.user_views';
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateViewSQL($name, $sql)
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropViewSQL($name)
    {
        return 'DROP VIEW '. $name;
    }

    /**
     * @param string  $name
     * @param string  $table
     * @param integer $start
     *
     * @return array
     */
    public function getCreateAutoincrementSql($name, $table, $start = 1)
    {
        $table = strtoupper($table);
        $name = strtoupper($name);

        $sql   = array();

        $indexName  = $table . '_AI_PK';

        $idx = new Index($indexName, array($name), true, true);

        $sql[] = 'DECLARE
  constraints_Count NUMBER;
BEGIN
  SELECT COUNT(CONSTRAINT_NAME) INTO constraints_Count FROM USER_CONSTRAINTS WHERE TABLE_NAME = \''.$table.'\' AND CONSTRAINT_TYPE = \'P\';
  IF constraints_Count = 0 OR constraints_Count = \'\' THEN
    EXECUTE IMMEDIATE \''.$this->getCreateConstraintSQL($idx, $table).'\';
  END IF;
END;';

        $sequenceName = $table . '_' . $name . '_SEQ';
        $sequence = new Sequence($sequenceName, $start);
        $sql[] = $this->getCreateSequenceSQL($sequence);

        $triggerName  = $table . '_AI_PK';
        $sql[] = 'CREATE TRIGGER ' . $triggerName . '
   BEFORE INSERT
   ON ' . $table . '
   FOR EACH ROW
DECLARE
   last_Sequence NUMBER;
   last_InsertID NUMBER;
BEGIN
   SELECT ' . $sequenceName . '.NEXTVAL INTO :NEW.' . $name . ' FROM DUAL;
   IF (:NEW.' . $name . ' IS NULL OR :NEW.'.$name.' = 0) THEN
      SELECT ' . $sequenceName . '.NEXTVAL INTO :NEW.' . $name . ' FROM DUAL;
   ELSE
      SELECT NVL(Last_Number, 0) INTO last_Sequence
        FROM User_Sequences
       WHERE Sequence_Name = \'' . $sequenceName . '\';
      SELECT :NEW.' . $name . ' INTO last_InsertID FROM DUAL;
      WHILE (last_InsertID > last_Sequence) LOOP
         SELECT ' . $sequenceName . '.NEXTVAL INTO last_Sequence FROM DUAL;
      END LOOP;
   END IF;
END;';

        return $sql;
    }

    /**
     * @param string $table
     *
     * @return array
     */
    public function getDropAutoincrementSql($table)
    {
        $table = strtoupper($table);
        $trigger = $table . '_AI_PK';

        $sql[] = 'DROP TRIGGER ' . $trigger;
        $sql[] = $this->getDropSequenceSQL($table.'_SEQ');

        $indexName = $table . '_AI_PK';
        $sql[] = $this->getDropConstraintSQL($indexName, $table);

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableForeignKeysSQL($table)
    {
        $table = strtoupper($table);

        return "SELECT alc.constraint_name,
          alc.DELETE_RULE,
          alc.search_condition,
          cols.column_name \"local_column\",
          cols.position,
          r_alc.table_name \"references_table\",
          r_cols.column_name \"foreign_column\"
     FROM user_cons_columns cols
LEFT JOIN user_constraints alc
       ON alc.constraint_name = cols.constraint_name
LEFT JOIN user_constraints r_alc
       ON alc.r_constraint_name = r_alc.constraint_name
LEFT JOIN user_cons_columns r_cols
       ON r_alc.constraint_name = r_cols.constraint_name
      AND cols.position = r_cols.position
    WHERE alc.constraint_name = cols.constraint_name
      AND alc.constraint_type = 'R'
      AND alc.table_name = '".$table."'";
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableConstraintsSQL($table)
    {
        $table = strtoupper($table);
        return 'SELECT * FROM user_constraints WHERE table_name = \'' . $table . '\'';
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableColumnsSQL($table, $database = null)
    {
        $table = strtoupper($table);

        $tabColumnsTableName = "user_tab_columns";
        $ownerCondition = '';

        if (null !== $database){
            $database = strtoupper($database);
            $tabColumnsTableName = "all_tab_columns";
            $ownerCondition = "AND c.owner = '".$database."'";
        }

        return "SELECT c.*, d.comments FROM $tabColumnsTableName c ".
               "INNER JOIN user_col_comments d ON d.TABLE_NAME = c.TABLE_NAME AND d.COLUMN_NAME = c.COLUMN_NAME ".
               "WHERE c.table_name = '" . $table . "' ".$ownerCondition." ORDER BY c.column_name";
    }

    /**
     * {@inheritDoc}
     */
    public function getDropSequenceSQL($sequence)
    {
        if ($sequence instanceof Sequence) {
            $sequence = $sequence->getQuotedName($this);
        }

        return 'DROP SEQUENCE ' . $sequence;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropForeignKeySQL($foreignKey, $table)
    {
        if ($foreignKey instanceof ForeignKeyConstraint) {
            $foreignKey = $foreignKey->getQuotedName($this);
        }

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        return 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $foreignKey;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropDatabaseSQL($database)
    {
        return 'DROP USER ' . $database . ' CASCADE';
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $sql = array();
        $commentsSQL = array();
        $columnSql = array();

        $fields = array();

        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $fields[] = $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
            if ($comment = $this->getColumnComment($column)) {
                $commentsSQL[] = $this->getCommentOnColumnSQL($diff->name, $column->getName(), $comment);
            }
        }

        if (count($fields)) {
            $sql[] = 'ALTER TABLE ' . $diff->name . ' ADD (' . implode(', ', $fields) . ')';
        }

        $fields = array();
        foreach ($diff->changedColumns as $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            $column = $columnDiff->column;
            $columnHasChangedComment = $columnDiff->hasChanged('comment');

            /**
             * Do not add query part if only comment has changed
             */
            if ( ! ($columnHasChangedComment && count($columnDiff->changedProperties) === 1)) {
                $fields[] = $column->getQuotedName($this). ' ' . $this->getColumnDeclarationSQL('', $column->toArray());
            }

            if ($columnHasChangedComment) {
                $commentsSQL[] = $this->getCommentOnColumnSQL(
                    $diff->name,
                    $column->getName(),
                    $this->getColumnComment($column)
                );
            }
        }

        if (count($fields)) {
            $sql[] = 'ALTER TABLE ' . $diff->name . ' MODIFY (' . implode(', ', $fields) . ')';
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $sql[] = 'ALTER TABLE ' . $diff->name . ' RENAME COLUMN ' . $oldColumnName .' TO ' . $column->getQuotedName($this);
        }

        $fields = array();
        foreach ($diff->removedColumns as $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $fields[] = $column->getQuotedName($this);
        }

        if (count($fields)) {
            $sql[] = 'ALTER TABLE ' . $diff->name . ' DROP (' . implode(', ', $fields).')';
        }

        $tableSql = array();

        if ( ! $this->onSchemaAlterTable($diff, $tableSql)) {
            if ($diff->newName !== false) {
                $sql[] = 'ALTER TABLE ' . $diff->name . ' RENAME TO ' . $diff->newName;
            }

            $sql = array_merge($sql, $this->_getAlterTableIndexForeignKeySQL($diff), $commentsSQL);
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * {@inheritDoc}
     */
    public function prefersSequences()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsCommentOnStatement()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'oracle';
    }

    /**
     * {@inheritDoc}
     */
    protected function doModifyLimitQuery($query, $limit, $offset = null)
    {
        $limit = (int) $limit;
        $offset = (int) $offset;

        if (preg_match('/^\s*SELECT/i', $query)) {
            if (!preg_match('/\sFROM\s/i', $query)) {
                $query .= " FROM dual";
            }
            if ($limit > 0) {
                $max = $offset + $limit;
                $column = '*';
                if ($offset > 0) {
                    $min = $offset + 1;
                    $query = 'SELECT * FROM (SELECT a.' . $column . ', rownum AS doctrine_rownum FROM (' .
                            $query .
                            ') a WHERE rownum <= ' . $max . ') WHERE doctrine_rownum >= ' . $min;
                } else {
                    $query = 'SELECT a.' . $column . ' FROM (' . $query . ') a WHERE ROWNUM <= ' . $max;
                }
            }
        }

        return $query;
    }

    /**
     * {@inheritDoc}
     *
     * Oracle returns all column names in SQL result sets in uppercase.
     */
    public function getSQLResultCasing($column)
    {
        return strtoupper($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTemporaryTableSnippetSQL()
    {
        return "CREATE GLOBAL TEMPORARY TABLE";
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzFormatString()
    {
        return 'Y-m-d H:i:sP';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateFormatString()
    {
        return 'Y-m-d 00:00:00';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeFormatString()
    {
        return '1900-01-01 H:i:s';
    }

    /**
     * {@inheritDoc}
     */
    public function fixSchemaElementName($schemaElementName)
    {
        if (strlen($schemaElementName) > 30) {
            // Trim it
            return substr($schemaElementName, 0, 30);
        }

        return $schemaElementName;
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxIdentifierLength()
    {
        return 30;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsSequences()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsForeignKeyOnUpdate()
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
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        return 'TRUNCATE TABLE '.$tableName;
    }

    /**
     * {@inheritDoc}
     */
    public function getDummySelectSQL()
    {
        return 'SELECT 1 FROM DUAL';
    }

    /**
     * {@inheritDoc}
     */
    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = array(
            'integer'           => 'integer',
            'number'            => 'integer',
            'pls_integer'       => 'boolean',
            'binary_integer'    => 'boolean',
            'varchar'           => 'string',
            'varchar2'          => 'string',
            'nvarchar2'         => 'string',
            'char'              => 'string',
            'nchar'             => 'string',
            'date'              => 'datetime',
            'timestamp'         => 'datetime',
            'timestamptz'       => 'datetimetz',
            'float'             => 'float',
            'long'              => 'string',
            'clob'              => 'text',
            'nclob'             => 'text',
            'raw'               => 'text',
            'long raw'          => 'text',
            'rowid'             => 'string',
            'urowid'            => 'string',
            'blob'              => 'blob',
        );
    }

    /**
     * {@inheritDoc}
     */
    public function releaseSavePoint($savepoint)
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\OracleKeywords';
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        return 'BLOB';
    }
}
