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
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\TableDiff,
    Doctrine\DBAL\Schema\Table;

/**
 * PostgreSqlPlatform.
 *
 * @since 2.0
 * @author Roman Borschel <roman@code-factory.org>
 * @author Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @todo Rename: PostgreSQLPlatform
 */
class PostgreSqlPlatform extends AbstractPlatform
{
    /**
     * Returns part of a string.
     *
     * Note: Not SQL92, but common functionality.
     *
     * @param string $value the target $value the string or the string column.
     * @param int $from extract from this characeter.
     * @param int $len extract this amount of characters.
     * @return string sql that extracts part of a string.
     * @override
     */
    public function getSubstringExpression($value, $from, $len = null)
    {
        if ($len === null) {
            return 'SUBSTR(' . $value . ', ' . $from . ')';
        } else {
            return 'SUBSTR(' . $value . ', ' . $from . ', ' . $len . ')';
        }
    }

    /**
     * Returns the SQL string to return the current system date and time.
     *
     * @return string
     */
    public function getNowExpression()
    {
        return 'LOCALTIMESTAMP(0)';
    }

    /**
     * regexp
     *
     * @return string           the regular expression operator
     * @override
     */
    public function getRegexpExpression()
    {
        return 'SIMILAR TO';
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
        if ($startPos !== false) {
            $str = $this->getSubstringExpression($str, $startPos);
            return 'CASE WHEN (POSITION('.$substr.' IN '.$str.') = 0) THEN 0 ELSE (POSITION('.$substr.' IN '.$str.') + '.($startPos-1).') END';
        } else {
            return 'POSITION('.$substr.' IN '.$str.')';
        }
    }

    public function getDateDiffExpression($date1, $date2)
    {
        return '(DATE(' . $date1 . ')-DATE(' . $date2 . '))';
    }

    public function getDateAddDaysExpression($date, $days)
    {
        return "(" . $date . "+ interval '" . $days . " day')";
    }

    public function getDateSubDaysExpression($date, $days)
    {
        return "(" . $date . "- interval '" . $days . " day')";
    }

    public function getDateAddMonthExpression($date, $months)
    {
        return "(" . $date . "+ interval '" . $months . " month')";
    }

    public function getDateSubMonthExpression($date, $months)
    {
        return "(" . $date . "- interval '" . $months . " month')";
    }
    
    /**
     * parses a literal boolean value and returns
     * proper sql equivalent
     *
     * @param string $value     boolean value to be parsed
     * @return string           parsed boolean value
     */
    /*public function parseBoolean($value)
    {
        return $value;
    }*/
    
    /**
     * Whether the platform supports sequences.
     * Postgres has native support for sequences.
     *
     * @return boolean
     */
    public function supportsSequences()
    {
        return true;
    }
    
    /**
     * Whether the platform supports database schemas.
     * 
     * @return boolean
     */
    public function supportsSchemas()
    {
        return true;
    }
    
    /**
     * Whether the platform supports identity columns.
     * Postgres supports these through the SERIAL keyword.
     *
     * @return boolean
     */
    public function supportsIdentityColumns()
    {
        return true;
    }

    public function supportsCommentOnStatement()
    {
        return true;
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

    public function getListDatabasesSQL()
    {
        return 'SELECT datname FROM pg_database';
    }

    public function getListSequencesSQL($database)
    {
        return "SELECT
                    c.relname, n.nspname AS schemaname
                FROM
                   pg_class c, pg_namespace n
                WHERE relkind = 'S' AND n.oid = c.relnamespace AND 
                    (n.nspname NOT LIKE 'pg_%' AND n.nspname != 'information_schema')";
    }

    public function getListTablesSQL()
    {
        return "SELECT tablename AS table_name, schemaname AS schema_name
                FROM pg_tables WHERE schemaname NOT LIKE 'pg_%' AND schemaname != 'information_schema'";
    }

    public function getListViewsSQL($database)
    {
        return 'SELECT viewname, definition FROM pg_views';
    }

    public function getListTableForeignKeysSQL($table, $database = null)
    {
        return "SELECT r.conname, pg_catalog.pg_get_constraintdef(r.oid, true) as condef
                  FROM pg_catalog.pg_constraint r
                  WHERE r.conrelid =
                  (
                      SELECT c.oid
                      FROM pg_catalog.pg_class c, pg_catalog.pg_namespace n
                      WHERE " .$this->getTableWhereClause($table) ."
                        AND n.oid = c.relnamespace
                  )
                  AND r.contype = 'f'";
    }

    public function getCreateViewSQL($name, $sql)
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    public function getDropViewSQL($name)
    {
        return 'DROP VIEW '. $name;
    }

    public function getListTableConstraintsSQL($table)
    {
        return "SELECT
                    relname
                FROM
                    pg_class
                WHERE oid IN (
                    SELECT indexrelid
                    FROM pg_index, pg_class
                    WHERE pg_class.relname = '$table'
                        AND pg_class.oid = pg_index.indrelid
                        AND (indisunique = 't' OR indisprimary = 't')
                        )";
    }

    /**
     * @license New BSD License
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaPgsqlReader.html
     * @param  string $table
     * @return string
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        return "SELECT relname, pg_index.indisunique, pg_index.indisprimary,
                       pg_index.indkey, pg_index.indrelid
                 FROM pg_class, pg_index
                 WHERE oid IN (
                    SELECT indexrelid
                    FROM pg_index si, pg_class sc, pg_namespace sn
                    WHERE " . $this->getTableWhereClause($table, 'sc', 'sn')." AND sc.oid=si.indrelid AND sc.relnamespace = sn.oid
                 ) AND pg_index.indexrelid = oid";
    }

    private function getTableWhereClause($table, $classAlias = 'c', $namespaceAlias = 'n')
    {
        $whereClause = "";
        if (strpos($table, ".") !== false) {
            list($schema, $table) = explode(".", $table);
            $whereClause = "$classAlias.relname = '" . $table . "' AND $namespaceAlias.nspname = '" . $schema . "'";
        } else {
            $whereClause = "$classAlias.relname = '" . $table . "'";
        }
        return $whereClause;
    }

    public function getListTableColumnsSQL($table, $database = null)
    {
        return "SELECT
                    a.attnum,
                    a.attname AS field,
                    t.typname AS type,
                    format_type(a.atttypid, a.atttypmod) AS complete_type,
                    (SELECT t1.typname FROM pg_catalog.pg_type t1 WHERE t1.oid = t.typbasetype) AS domain_type,
                    (SELECT format_type(t2.typbasetype, t2.typtypmod) FROM pg_catalog.pg_type t2
                     WHERE t2.typtype = 'd' AND t2.typname = format_type(a.atttypid, a.atttypmod)) AS domain_complete_type,
                    a.attnotnull AS isnotnull,
                    (SELECT 't'
                     FROM pg_index
                     WHERE c.oid = pg_index.indrelid
                        AND pg_index.indkey[0] = a.attnum
                        AND pg_index.indisprimary = 't'
                    ) AS pri,
                    (SELECT pg_attrdef.adsrc
                     FROM pg_attrdef
                     WHERE c.oid = pg_attrdef.adrelid
                        AND pg_attrdef.adnum=a.attnum
                    ) AS default,
                    (SELECT pg_description.description
                        FROM pg_description WHERE pg_description.objoid = c.oid AND a.attnum = pg_description.objsubid
                    ) AS comment
                    FROM pg_attribute a, pg_class c, pg_type t, pg_namespace n
                    WHERE ".$this->getTableWhereClause($table, 'c', 'n') ."
                        AND a.attnum > 0
                        AND a.attrelid = c.oid
                        AND a.atttypid = t.oid
                        AND n.oid = c.relnamespace
                    ORDER BY a.attnum";
    }
    
    /**
     * create a new database
     *
     * @param string $name name of the database that should be created
     * @throws PDOException
     * @return void
     * @override
     */
    public function getCreateDatabaseSQL($name)
    {
        return 'CREATE DATABASE ' . $name;
    }

    /**
     * drop an existing database
     *
     * @param string $name name of the database that should be dropped
     * @throws PDOException
     * @access public
     */
    public function getDropDatabaseSQL($name)
    {
        return 'DROP DATABASE ' . $name;
    }

    /**
     * Return the FOREIGN KEY query section dealing with non-standard options
     * as MATCH, INITIALLY DEFERRED, ON UPDATE, ...
     *
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint $foreignKey         foreign key definition
     * @return string
     * @override
     */
    public function getAdvancedForeignKeyOptionsSQL(\Doctrine\DBAL\Schema\ForeignKeyConstraint $foreignKey)
    {
        $query = '';
        if ($foreignKey->hasOption('match')) {
            $query .= ' MATCH ' . $foreignKey->getOption('match');
        }
        $query .= parent::getAdvancedForeignKeyOptionsSQL($foreignKey);
        if ($foreignKey->hasOption('deferrable') && $foreignKey->getOption('deferrable') !== false) {
            $query .= ' DEFERRABLE';
        } else {
            $query .= ' NOT DEFERRABLE';
        }
        if ($foreignKey->hasOption('feferred') && $foreignKey->getOption('feferred') !== false) {
            $query .= ' INITIALLY DEFERRED';
        } else {
            $query .= ' INITIALLY IMMEDIATE';
        }
        return $query;
    }
    
    /**
     * generates the sql for altering an existing table on postgresql
     *
     * @param string $name          name of the table that is intended to be changed.
     * @param array $changes        associative array that contains the details of each type      *
     * @param boolean $check        indicates whether the function should just check if the DBMS driver
     *                              can perform the requested table alterations if the value is true or
     *                              actually perform them otherwise.
     * @see Doctrine_Export::alterTable()
     * @return array
     * @override
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $sql = array();
        $commentsSQL = array();

        foreach ($diff->addedColumns as $column) {
            $query = 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
            $sql[] = 'ALTER TABLE ' . $diff->name . ' ' . $query;
            if ($comment = $this->getColumnComment($column)) {
                $commentsSQL[] = $this->getCommentOnColumnSQL($diff->name, $column->getName(), $comment);
            }
        }

        foreach ($diff->removedColumns as $column) {
            $query = 'DROP ' . $column->getQuotedName($this);
            $sql[] = 'ALTER TABLE ' . $diff->name . ' ' . $query;
        }

        foreach ($diff->changedColumns AS $columnDiff) {
            $oldColumnName = $columnDiff->oldColumnName;
            $column = $columnDiff->column;
            
            if ($columnDiff->hasChanged('type')) {
                $type = $column->getType();

                // here was a server version check before, but DBAL API does not support this anymore.
                $query = 'ALTER ' . $oldColumnName . ' TYPE ' . $type->getSqlDeclaration($column->toArray(), $this);
                $sql[] = 'ALTER TABLE ' . $diff->name . ' ' . $query;
            }
            if ($columnDiff->hasChanged('default')) {
                $query = 'ALTER ' . $oldColumnName . ' SET ' . $this->getDefaultValueDeclarationSQL($column->toArray());
                $sql[] = 'ALTER TABLE ' . $diff->name . ' ' . $query;
            }
            if ($columnDiff->hasChanged('notnull')) {
                $query = 'ALTER ' . $oldColumnName . ' ' . ($column->getNotNull() ? 'SET' : 'DROP') . ' NOT NULL';
                $sql[] = 'ALTER TABLE ' . $diff->name . ' ' . $query;
            }
            if ($columnDiff->hasChanged('autoincrement')) {
                if ($column->getAutoincrement()) {
                    // add autoincrement
                    $seqName = $diff->name . '_' . $oldColumnName . '_seq';

                    $sql[] = "CREATE SEQUENCE " . $seqName;
                    $sql[] = "SELECT setval('" . $seqName . "', (SELECT MAX(" . $oldColumnName . ") FROM " . $diff->name . "))";
                    $query = "ALTER " . $oldColumnName . " SET DEFAULT nextval('" . $seqName . "')";
                    $sql[] = "ALTER TABLE " . $diff->name . " " . $query;
                } else {
                    // Drop autoincrement, but do NOT drop the sequence. It might be re-used by other tables or have
                    $query = "ALTER " . $oldColumnName . " " . "DROP DEFAULT";
                    $sql[] = "ALTER TABLE " . $diff->name . " " . $query;
                }
            }
            if ($columnDiff->hasChanged('comment') && $comment = $this->getColumnComment($column)) {
                $commentsSQL[] = $this->getCommentOnColumnSQL($diff->name, $column->getName(), $comment);
            }
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            $sql[] = 'ALTER TABLE ' . $diff->name . ' RENAME COLUMN ' . $oldColumnName . ' TO ' . $column->getQuotedName($this);
        }

        if ($diff->newName !== false) {
            $sql[] = 'ALTER TABLE ' . $diff->name . ' RENAME TO ' . $diff->newName;
        }

        return array_merge($sql, $this->_getAlterTableIndexForeignKeySQL($diff), $commentsSQL);
    }
    
    /**
     * Gets the SQL to create a sequence on this platform.
     *
     * @param \Doctrine\DBAL\Schema\Sequence $sequence
     * @return string
     */
    public function getCreateSequenceSQL(\Doctrine\DBAL\Schema\Sequence $sequence)
    {
        return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this) .
               ' INCREMENT BY ' . $sequence->getAllocationSize() .
               ' MINVALUE ' . $sequence->getInitialValue() .
               ' START ' . $sequence->getInitialValue();
    }
    
    public function getAlterSequenceSQL(\Doctrine\DBAL\Schema\Sequence $sequence)
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this) . 
               ' INCREMENT BY ' . $sequence->getAllocationSize();
    }
    
    /**
     * Drop existing sequence
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
     * @param  ForeignKeyConstraint|string $foreignKey
     * @param  Table|string $table
     * @return string
     */
    public function getDropForeignKeySQL($foreignKey, $table)
    {
        return $this->getDropConstraintSQL($foreignKey, $table);
    }
    
    /**
     * Gets the SQL used to create a table.
     *
     * @param unknown_type $tableName
     * @param array $columns
     * @param array $options
     * @return unknown
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = array())
    {
        $queryFields = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['primary']) && ! empty($options['primary'])) {
            $keyColumns = array_unique(array_values($options['primary']));
            $queryFields .= ', PRIMARY KEY(' . implode(', ', $keyColumns) . ')';
        }

        $query = 'CREATE TABLE ' . $tableName . ' (' . $queryFields . ')';

        $sql[] = $query;

        if (isset($options['indexes']) && ! empty($options['indexes'])) {
            foreach ($options['indexes'] AS $index) {
                $sql[] = $this->getCreateIndexSQL($index, $tableName);
            }
        }

        if (isset($options['foreignKeys'])) {
            foreach ((array) $options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $tableName);
            }
        }

        return $sql;
    }
    
    /**
     * Postgres wants boolean values converted to the strings 'true'/'false'.
     *
     * @param array $item
     * @override
     */
    public function convertBooleans($item)
    {
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                if (is_bool($value) || is_numeric($item)) {
                    $item[$key] = ($value) ? 'true' : 'false';
                }
            }
        } else {
           if (is_bool($item) || is_numeric($item)) {
               $item = ($item) ? 'true' : 'false';
           }
        }
        return $item;
    }

    public function getSequenceNextValSQL($sequenceName)
    {
        return "SELECT NEXTVAL('" . $sequenceName . "')";
    }

    public function getSetTransactionIsolationSQL($level)
    {
        return 'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL '
                . $this->_getTransactionIsolationLevelSQL($level);
    }
    
    /**
     * @override
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return 'BOOLEAN';
    }

    /**
     * @override
     */
    public function getIntegerTypeDeclarationSQL(array $field)
    {
        if ( ! empty($field['autoincrement'])) {
            return 'SERIAL';
        }
        
        return 'INT';
    }

    /**
     * @override
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
        if ( ! empty($field['autoincrement'])) {
            return 'BIGSERIAL';
        }
        return 'BIGINT';
    }

    /**
     * @override
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return 'SMALLINT';
    }

    /**
     * @override
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'TIMESTAMP(0) WITHOUT TIME ZONE';
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
        return 'TIME(0) WITHOUT TIME ZONE';
    }

    /**
     * @override
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        return '';
    }

    /**
     * Gets the SQL snippet used to declare a VARCHAR column on the MySql platform.
     *
     * @params array $field
     * @override
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? ($length ? 'CHAR(' . $length . ')' : 'CHAR(255)')
                : ($length ? 'VARCHAR(' . $length . ')' : 'VARCHAR(255)');
    }
    
    /** @override */
    public function getClobTypeDeclarationSQL(array $field)
    {
        return 'TEXT';
    }

    /**
     * Get the platform name for this instance
     *
     * @return string
     */
    public function getName()
    {
        return 'postgresql';
    }
    
    /**
     * Gets the character casing of a column in an SQL result set.
     * 
     * PostgreSQL returns all column names in SQL result sets in lowercase.
     * 
     * @param string $column The column name for which to get the correct character casing.
     * @return string The column name in the character casing used in SQL result sets.
     */
    public function getSQLResultCasing($column)
    {
        return strtolower($column);
    }
    
    public function getDateTimeTzFormatString()
    {
        return 'Y-m-d H:i:sO';
    }

    /**
     * Get the insert sql for an empty insert statement
     *
     * @param string $tableName 
     * @param string $identifierColumnName 
     * @return string $sql
     */
    public function getEmptyIdentityInsertSQL($quotedTableName, $quotedIdentifierColumnName)
    {
        return 'INSERT INTO ' . $quotedTableName . ' (' . $quotedIdentifierColumnName . ') VALUES (DEFAULT)';
    }

    /**
     * @inheritdoc
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        return 'TRUNCATE '.$tableName.' '.(($cascade)?'CASCADE':'');
    }

    public function getReadLockSQL()
    {
        return 'FOR SHARE';
    }

    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = array(
            'smallint'      => 'smallint',
            'int2'          => 'smallint',
            'serial'        => 'integer',
            'serial4'       => 'integer',
            'int'           => 'integer',
            'int4'          => 'integer',
            'integer'       => 'integer',
            'bigserial'     => 'bigint',
            'serial8'       => 'bigint',
            'bigint'        => 'bigint',
            'int8'          => 'bigint',
            'bool'          => 'boolean',
            'boolean'       => 'boolean',
            'text'          => 'text',
            'varchar'       => 'string',
            'interval'      => 'string',
            '_varchar'      => 'string',
            'char'          => 'string',
            'bpchar'        => 'string',
            'date'          => 'date',
            'datetime'      => 'datetime',
            'timestamp'     => 'datetime',
            'timestamptz'   => 'datetimetz',
            'time'          => 'time',
            'timetz'        => 'time',
            'float'         => 'float',
            'float4'        => 'float',
            'float8'        => 'float',
            'double'        => 'float',
            'double precision' => 'float',
            'real'          => 'float',
            'decimal'       => 'decimal',
            'money'         => 'decimal',
            'numeric'       => 'decimal',
            'year'          => 'date',
        );
    }

    public function getVarcharMaxLength()
    {
        return 65535;
    }
    
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\PostgreSQLKeywords';
    }
}
