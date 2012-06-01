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
     * Assertion for Oracle identifiers
     *
     * @link http://docs.oracle.com/cd/B19306_01/server.102/b14200/sql_elements008.htm
     * @param string
     * @throws DBALException
     */
    static public function assertValidIdentifier($identifier)
    {
        if ( ! preg_match('(^(([a-zA-Z]{1}[a-zA-Z0-9_$#]{0,})|("[^"]+"))$)', $identifier)) {
            throw new DBALException("Invalid Oracle identifier");
        }
    }

    /**
     * return string to call a function to get a substring inside an SQL statement
     *
     * Note: Not SQL92, but common functionality.
     *
     * @param string $value         an sql string literal or column name/alias
     * @param integer $position     where to start the substring portion
     * @param integer $length       the substring portion length
     * @return string               SQL substring function with given parameters
     * @override
     */
    public function getSubstringExpression($value, $position, $length = null)
    {
        if ($length !== null) {
            return "SUBSTR($value, $position, $length)";
        }

        return "SUBSTR($value, $position)";
    }

    /**
     * Return string to call a variable with the current timestamp inside an SQL statement
     * There are three special variables for current date and time:
     * - CURRENT_TIMESTAMP (date and time, TIMESTAMP type)
     * - CURRENT_DATE (date, DATE type)
     * - CURRENT_TIME (time, TIME type)
     *
     * @return string to call a variable with the current timestamp
     * @override
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
     * returns the position of the first occurrence of substring $substr in string $str
     *
     * @param string $substr    literal string to find
     * @param string $str       literal string
     * @param int    $pos       position to start at, beginning of string by default
     * @return integer
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        if ($startPos == false) {
            return 'INSTR('.$str.', '.$substr.')';
        } else {
            return 'INSTR('.$str.', '.$substr.', '.$startPos.')';
        }
    }

    /**
     * Returns global unique identifier
     *
     * @return string to get global unique identifier
     * @override
     */
    public function getGuidExpression()
    {
        return 'SYS_GUID()';
    }

    /**
     * Get the number of days difference between two dates.
     *
     * Note: Since Oracle timestamp differences are calculated down to the microsecond we have to truncate
     * them to the difference in days. This is obviously a restriction of the original functionality, but we
     * need to make this a portable function.
     *
     * @param string $date1
     * @param string $date2
     * @return string
     */
    public function getDateDiffExpression($date1, $date2)
    {
        return "TRUNC(TO_NUMBER(SUBSTR((" . $date1 . "-" . $date2 . "), 1, INSTR(" . $date1 . "-" . $date2 .", ' '))))";
    }

    /**
     * {@inheritdoc}
     */
    public function getDateAddDaysExpression($date, $days)
    {
        return '(' . $date . '+' . $days . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getDateSubDaysExpression($date, $days)
    {
        return '(' . $date . '-' . $days . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getDateAddMonthExpression($date, $months)
    {
        return "ADD_MONTHS(" . $date . ", " . $months . ")";
    }

    /**
     * {@inheritdoc}
     */
    public function getDateSubMonthExpression($date, $months)
    {
        return "ADD_MONTHS(" . $date . ", -" . $months . ")";
    }

    /**
     * {@inheritdoc}
     */
    public function getBitAndComparisonExpression($value1, $value2)
    {
        return 'BITAND('.$value1 . ', ' . $value2 . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getBitOrComparisonExpression($value1, $value2)
    {
        return '(' . $value1 . '-' .
                $this->getBitAndComparisonExpression($value1, $value2)
                . '+' . $value2 . ')';
    }

    /**
     * Gets the SQL used to create a sequence that starts with a given value
     * and increments by the given allocation size.
     *
     * Need to specifiy minvalue, since start with is hidden in the system and MINVALUE <= START WITH.
     * Therefore we can use MINVALUE to be able to get a hint what START WITH was for later introspection
     * in {@see listSequences()}
     *
     * @param \Doctrine\DBAL\Schema\Sequence $sequence
     * @return string
     */
    public function getCreateSequenceSQL(\Doctrine\DBAL\Schema\Sequence $sequence)
    {
        return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this) .
               ' START WITH ' . $sequence->getInitialValue() .
               ' MINVALUE ' . $sequence->getInitialValue() .
               ' INCREMENT BY ' . $sequence->getAllocationSize();
    }

    public function getAlterSequenceSQL(\Doctrine\DBAL\Schema\Sequence $sequence)
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this) .
               ' INCREMENT BY ' . $sequence->getAllocationSize();
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sequenceName
     * @override
     */
    public function getSequenceNextValSQL($sequenceName)
    {
        return 'SELECT ' . $sequenceName . '.nextval FROM DUAL';
    }

    /**
     * {@inheritdoc}
     *
     * @param integer $level
     * @override
     */
    public function getSetTransactionIsolationSQL($level)
    {
        return 'SET TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

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
     * @override
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return 'NUMBER(1)';
    }

    /**
     * @override
     */
    public function getIntegerTypeDeclarationSQL(array $field)
    {
        return 'NUMBER(10)';
    }

    /**
     * @override
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
        return 'NUMBER(20)';
    }

    /**
     * @override
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return 'NUMBER(5)';
    }

    /**
     * @override
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'TIMESTAMP(0)';
    }

    /**
     * @override
     */
    public function getDateTimeTzTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'TIMESTAMP(0) WITH TIME ZONE';
    }

    /**
     * @override
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATE';
    }

    /**
     * @override
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATE';
    }

    /**
     * @override
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        return '';
    }

    /**
     * Gets the SQL snippet used to declare a VARCHAR column on the Oracle platform.
     *
     * @params array $field
     * @override
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? ($length ? 'CHAR(' . $length . ')' : 'CHAR(2000)')
                : ($length ? 'VARCHAR2(' . $length . ')' : 'VARCHAR2(4000)');
    }

    /** @override */
    public function getClobTypeDeclarationSQL(array $field)
    {
        return 'CLOB';
    }

    public function getListDatabasesSQL()
    {
        return 'SELECT username FROM all_users';
    }

    public function getListSequencesSQL($database)
    {
        return "SELECT sequence_name, min_value, increment_by FROM sys.all_sequences ".
               "WHERE SEQUENCE_OWNER = '".strtoupper($database)."'";
    }

    /**
     *
     * @param string $table
     * @param array $columns
     * @param array $options
     * @return array
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
     * @license New BSD License
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaOracleReader.html
     * @param  string $table
     * @return string
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

    public function getListTablesSQL()
    {
        return 'SELECT * FROM sys.user_tables';
    }

    public function getListViewsSQL($database)
    {
        return 'SELECT view_name, text FROM sys.user_views';
    }

    public function getCreateViewSQL($name, $sql)
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    public function getDropViewSQL($name)
    {
        return 'DROP VIEW '. $name;
    }

    public function getCreateAutoincrementSql($name, $table, $start = 1)
    {
        $table = strtoupper($table);
        $sql   = array();

        $indexName  = $table . '_AI_PK';

        $idx = new \Doctrine\DBAL\Schema\Index($indexName, array($name), true, true);

        $sql[] = 'DECLARE
  constraints_Count NUMBER;
BEGIN
  SELECT COUNT(CONSTRAINT_NAME) INTO constraints_Count FROM USER_CONSTRAINTS WHERE TABLE_NAME = \''.$table.'\' AND CONSTRAINT_TYPE = \'P\';
  IF constraints_Count = 0 OR constraints_Count = \'\' THEN
    EXECUTE IMMEDIATE \''.$this->getCreateConstraintSQL($idx, $table).'\';
  END IF;
END;';

        $sequenceName = $table . '_SEQ';
        $sequence = new \Doctrine\DBAL\Schema\Sequence($sequenceName, $start);
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

    public function getDropAutoincrementSql($table)
    {
        $table = strtoupper($table);
        $trigger = $table . '_AI_PK';

        if ($trigger) {
            $sql[] = 'DROP TRIGGER ' . $trigger;
            $sql[] = $this->getDropSequenceSQL($table.'_SEQ');

            $indexName = $table . '_AI_PK';
            $sql[] = $this->getDropConstraintSQL($indexName, $table);
        }

        return $sql;
    }

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

    public function getListTableConstraintsSQL($table)
    {
        $table = strtoupper($table);
        return 'SELECT * FROM user_constraints WHERE table_name = \'' . $table . '\'';
    }

    public function getListTableColumnsSQL($table, $database = null)
    {
        $table = strtoupper($table);

        $tabColumnsTableName = "user_tab_columns";
        $ownerCondition = '';
        if(null !== $database){
            $database = strtoupper($database);
            $tabColumnsTableName = "all_tab_columns";
            $ownerCondition = "AND c.owner = '".$database."'";
        }

        return "SELECT c.*, d.comments FROM $tabColumnsTableName c ".
               "INNER JOIN user_col_comments d ON d.TABLE_NAME = c.TABLE_NAME AND d.COLUMN_NAME = c.COLUMN_NAME ".
               "WHERE c.table_name = '" . $table . "' ".$ownerCondition." ORDER BY c.column_name";
    }

    /**
     *
     * @param  \Doctrine\DBAL\Schema\Sequence $sequence
     * @return string
     */
    public function getDropSequenceSQL($sequence)
    {
        if ($sequence instanceof \Doctrine\DBAL\Schema\Sequence) {
            $sequence = $sequence->getQuotedName($this);
        }

        return 'DROP SEQUENCE ' . $sequence;
    }

    /**
     * @param  \Doctrine\DBAL\Schema\ForeignKeyConstraint|string $foreignKey
     * @param  \Doctrine\DBAL\Schema\Table|string $table
     * @return string
     */
    public function getDropForeignKeySQL($foreignKey, $table)
    {
        if ($foreignKey instanceof \Doctrine\DBAL\Schema\ForeignKeyConstraint) {
            $foreignKey = $foreignKey->getQuotedName($this);
        }

        if ($table instanceof \Doctrine\DBAL\Schema\Table) {
            $table = $table->getQuotedName($this);
        }

        return 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $foreignKey;
    }

    public function getDropDatabaseSQL($database)
    {
        return 'DROP USER ' . $database . ' CASCADE';
    }

    /**
     * Gets the sql statements for altering an existing table.
     *
     * The method returns an array of sql statements, since some platforms need several statements.
     *
     * @param string $diff->name          name of the table that is intended to be changed.
     * @param array $changes        associative array that contains the details of each type      *
     * @param boolean $check        indicates whether the function should just check if the DBMS driver
     *                              can perform the requested table alterations if the value is true or
     *                              actually perform them otherwise.
     * @return array
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
            $fields[] = $column->getQuotedName($this). ' ' . $this->getColumnDeclarationSQL('', $column->toArray());
            if ($columnDiff->hasChanged('comment') && $comment = $this->getColumnComment($column)) {
                $commentsSQL[] = $this->getCommentOnColumnSQL($diff->name, $column->getName(), $comment);
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
     * Whether the platform prefers sequences for ID generation.
     *
     * @return boolean
     */
    public function prefersSequences()
    {
        return true;
    }

    public function supportsCommentOnStatement()
    {
        return true;
    }

    /**
     * Get the platform name for this instance
     *
     * @return string
     */
    public function getName()
    {
        return 'oracle';
    }

    /**
     * Adds an driver-specific LIMIT clause to the query
     *
     * @param string $query         query to modify
     * @param integer $limit        limit the number of rows
     * @param integer $offset       start reading from given offset
     * @return string               the modified query
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
     * Gets the character casing of a column in an SQL result set of this platform.
     *
     * Oracle returns all column names in SQL result sets in uppercase.
     *
     * @param string $column The column name for which to get the correct character casing.
     * @return string The column name in the character casing used in SQL result sets.
     */
    public function getSQLResultCasing($column)
    {
        return strtoupper($column);
    }

    public function getCreateTemporaryTableSnippetSQL()
    {
        return "CREATE GLOBAL TEMPORARY TABLE";
    }

    public function getDateTimeTzFormatString()
    {
        return 'Y-m-d H:i:sP';
    }

    public function getDateFormatString()
    {
        return 'Y-m-d 00:00:00';
    }

    public function getTimeFormatString()
    {
        return '1900-01-01 H:i:s';
    }

    public function fixSchemaElementName($schemaElementName)
    {
        if (strlen($schemaElementName) > 30) {
            // Trim it
            return substr($schemaElementName, 0, 30);
        }
        return $schemaElementName;
    }

    /**
     * Maximum length of any given databse identifier, like tables or column names.
     *
     * @return int
     */
    public function getMaxIdentifierLength()
    {
        return 30;
    }

    /**
     * Whether the platform supports sequences.
     *
     * @return boolean
     */
    public function supportsSequences()
    {
        return true;
    }

    public function supportsForeignKeyOnUpdate()
    {
        return false;
    }

    /**
     * Whether the platform supports releasing savepoints.
     *
     * @return boolean
     */
    public function supportsReleaseSavepoints()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        return 'TRUNCATE TABLE '.$tableName;
    }

    /**
     * This is for test reasons, many vendors have special requirements for dummy statements.
     *
     * @return string
     */
    public function getDummySelectSQL()
    {
        return 'SELECT 1 FROM DUAL';
    }

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
     * Generate SQL to release a savepoint
     *
     * @param string $savepoint
     * @return string
     */
    public function releaseSavePoint($savepoint)
    {
        return '';
    }

    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\OracleKeywords';
    }

    /**
     * Gets the SQL Snippet used to declare a BLOB column type.
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        return 'BLOB';
    }
}
