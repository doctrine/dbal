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

use Doctrine\DBAL\Schema\Index,
    Doctrine\DBAL\Schema\TableDiff,
    Doctrine\DBAL\Schema\Table;

/**
 * AkibanServerPlatform.
 *
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 * @since  2.3
 */
class AkibanServerPlatform extends AbstractPlatform
{
    /**
     * Returns part of a string.
     *
     * Note: Not SQL92, but common functionality.
     *
     * @param string $value the target $value the string or the string column.
     * @param int $from extract from this character.
     * @param int $len extract this amount of characters.
     * @return string sql that extracts part of a string.
     * @override
     */
    public function getSubstringExpression($value, $from, $len = null)
    {
        if ($len === null) {
            return "SUBSTR(" . $value . ", " . $from . ")";
        } else {
            return "SUBSTR(" . $value . ", " . $from . ", " . $len . ")";
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
        if ($startPos !== false) {
            $str = $this->getSubstringExpression($str, $startPos);
            return "CASE WHEN (POSITION(" . $substr . " IN " . $str . ") = 0) THEN 0 ELSE (POSITION(" . $substr . " IN " . $str . ") + " . ($startPos-1) . ") END";
        } else {
            return "POSITION(" . $substr . " IN " . $str . ")";
        }
    }

    public function getDateDiffExpression($date1, $date2)
    {
        return "DATEDIFF(" . $date1 . ", " . $date2 . ")";
    }

    public function getDateAddDaysExpression($date, $days)
    {
        return "DATE_ADD(" . $date . ", INTERVAL " . $days . " DAY)";
    }

    public function getDateSubDaysExpression($date, $days)
    {
        return "DATE_SUB(" . $date . ", INTERVAL " . $days . " DAY)";
    }

    public function getDateAddMonthExpression($date, $months)
    {
        return "DATE_ADD(" . $date . ", INTERVAL " . $months . " MONTH)";
    }

    public function getDateSubMonthExpression($date, $months)
    {
        return "DATE_SUB(" . $date . ", INTERVAL " . $months . " MONTH)";
    }

    /**
     * {@inheritdoc}
     */
    public function getBitAndComparisonExpression($value1, $value2)
    {
        return "BITAND(" . $value1 . ", " . $value2 . ")";
    }

    /**
     * {@inheritdoc}
     */
    public function getBitOrComparisonExpression($value1, $value2)
    {
        return "BITOR(" . $value1 . ", " . $value2 . ")";
    }

    /**
     * Akiban does not support this syntax in current release.
     */
    public function getForUpdateSQL()
    {
        return "";
    }

    /**
     * Whether the platform supports sequences.
     * Akbian Server has native support for sequences.
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
     * Akiban Server supports these through the SERIAL keyword.
     *
     * @return boolean
     */
    public function supportsIdentityColumns()
    {
        return true;
    }

    public function supportsCommentOnStatement()
    {
        return false;
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

    /**
     * Whether the platform supports savepoints.
     *
     * @return boolean
     */
    public function supportsSavepoints()
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
        return $this->supportsSavepoints();
    }

    /**  
     * Does the platform supports foreign key constraints?
     *    
     * @return boolean
     */   
    public function supportsForeignKeyConstraints()
    {    
        return false;
    }    

    /**  
     * Does this platform supports onUpdate in foreign key constraints?
     *    
     * @return bool 
     */   
    public function supportsForeignKeyOnUpdate()
    {    
        return ($this->supportsForeignKeyConstraints() && true);
    }  


    public function getListDatabasesSQL()
    {
        return "SELECT schema_name FROM information_schema.schemata";
    }

    public function getListSequencesSQL($database)
    {
        return "SELECT sequence_name, sequence_schema as schemaname, increment as increment_by, minimum_value as min_value " .
               "FROM information_schema.sequences " .
               "WHERE sequence_name != 'information_schema'";
    }

    public function getListTablesSQL()
    {
        return "SELECT table_name, table_schema " .
                "FROM information_schema.tables WHERE table_schema != 'information_schema'";
    }

    public function getListViewsSQL($database)
    {
        return "SELECT table_name as viewname, view_definition as definition FROM information_schema.views";
    }

    public function getCreateViewSQL($name, $sql)
    {
        return "CREATE VIEW " . $name . " AS " . $sql;
    }

    public function getDropViewSQL($name)
    {
        return "DROP VIEW " . $name;
    }

    public function getListTableConstraintsSQL($table)
    {
        // TODO - do we only want unique and primary key indexes here?
        return "SELECT index_name " .
               "FROM information_schema.indexes " .
               "WHERE schema_name != 'information_schema' AND " .
               "table_name = '" . $table . "'";
    }

    /**
     * @param  string $table
     * @return string
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        // TODO - should $currentDatabase be used?
        return "SELECT table_name, index_name, is_unique " .
               "FROM information_schema.indexes " .
               "WHERE table_name = '" . $table . "'";
    }

    public function getListTableColumnsSQL($table, $database = null)
    {
        if (! is_null($database)) {
            $schemaPredicate = "schema_name = '" . $database . "' and ";
        } else {
            $schemePredicate = "";
        }
        return "SELECT column_name, type, nullable, character_set_name, collation_name " .
               "FROM information_schema.columns " .
               "WHERE schema_name != 'information_schema' and " . $schemaPredicate .
               "table_name = '" . $table . "'";
    }

    /**
     * create a new database
     *
     * @param string $name name of the database that should be created
     * @return string
     * @override
     */
    public function getCreateDatabaseSQL($name)
    {
        return "CREATE SCHEMA " . $name;
    }

    /**
     * drop an existing database
     *
     * @param string $name name of the database that should be dropped
     * @access public
     */
    public function getDropDatabaseSQL($name)
    {
        return "DROP SCHEMA IF EXISTS " . $name . " CASCADE";
    }

    /**
     * generates the sql for altering an existing table in Akiban Server
     *
     * @see Doctrine_Export::alterTable()
     * @param TableDiff $diff
     * @return array
     * @override
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $sql = array();
        $commentsSQL = array(); // Akiban Server does not support comments as of 1.4.0
        $columnSql = array(); 

        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $query = "ADD " . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
            $sql[] = "ALTER TABLE " . $diff->name . " " . $query;
            if ($comment = $this->getColumnComment($column)) {
                // TODO
            }
        }

        foreach ($diff->removedColumns as $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $query = "DROP " . $column->getQuotedName($this);
            $sql[] = "ALTER TABLE " . $diff->name . " " . $query;
        }

        foreach ($diff->changedColumns as $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = $columnDiff->oldColumnName;
            $column = $columnDiff->column;

            if ($columnDiff->hasChanged('type')) {
                $type = $column->getType();

                $query = "ALTER " . $oldColumnName . " SET DATA TYPE " . $type->getSqlDeclaration($column->toArray(), $this);
                $sql[] = "ALTER TABLE " . $diff->name . " " . $query;
            }
            if ($columnDiff->hasChanged('default')) {
                // TODO
            }
            if ($columnDiff->hasChanged('notnull')) {
                // TODO
            }
            if ($columnDiff->hasChanged('autoincrement')) {
                // TODO
            }
            if ($columnDiff->hasChanged('comment') && $comment = $this->getColumnComment($column)) {
                // TODO
            }
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }
            // TODO
        }

        $tableSql = array();

        if ( ! $this->onSchemaAlterTable($diff, $tableSql)) {
            if ($diff->newName !== false) {
                // TODO
            }
            // TODO - handle foreign keys alter statements
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * Gets the SQL to create a sequence on this platform.
     *
     * @param \Doctrine\DBAL\Schema\Sequence $sequence
     * @return string
     */
    public function getCreateSequenceSQL(\Doctrine\DBAL\Schema\Sequence $sequence)
    {
        return "CREATE SEQUENCE " . $sequence->getQuotedName($this) .
               " START WITH " . $sequence->getInitialValue() .
               " INCREMENT BY " . $sequence->getAllocationSize() .
               " MINVALUE " . $sequence->getInitialValue();
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
        return "DROP SEQUENCE " . $sequence . " RESTRICT";
    }

     /**  
      * @override
      */ 
    public function getUniqueConstraintDeclarationSQL($name, Index $index)
    {
        // TODO - akiban does not support speciifying names for unique constraints in 1.4.0

        return " UNIQUE ("
             . $this->getIndexFieldDeclarationListSQL($index->getColumns())
             . ")"; 
    }

    /**
     * Gets the SQL used to create a table.
     *
     * @param string $tableName
     * @param array $columns
     * @param array $options
     * @return string
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = array())
    {
        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && ! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $name => $definition) {
                $columnListSql .= $this->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if (isset($options['primary']) && ! empty($options['primary'])) {
            $keyColumns = array_unique(array_values($options['primary']));
            $columnListSql .= ", PRIMARY KEY(" . implode(", ", $keyColumns) . ")";
        }

        $query = "CREATE TABLE " . $tableName . " (" . $columnListSql . ")";

        $check = $this->getCheckDeclarationSQL($columns);
        if (! empty($check)) {
            // TODO - Akiban does not support CHECK constraints in 1.4.0
        }

        $sql[] = $query;

        foreach ($columns as $name => $column) {
            if (isset($column['sequence'])) {
                $sql[] = $this->getCreateSequenceSQL($column['sequence'], 1);
            }
        }

        if (isset($options['indexes']) && ! empty($options['indexes'])) {
            foreach ($options['indexes'] as $index) {
                $sql[] = $this->getCreateIndexSQL($index, $tableName);
            }
        }

        // TODO - foreign keys, Akiban does not support in 1.4.0

        return $sql;
    }

    public function getSequenceNextValSQL($sequenceName)
    {
        return "SELECT NEXT VALUE FOR ". $sequenceName;
    }

    /**
     * @override
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return "BOOLEAN";
    }

    /**
     * @override
     */
    public function getIntegerTypeDeclarationSQL(array $field)
    {
        if (! empty($field['autoincrement'])) {
            return "SERIAL";
        }
        return "INT";
    }

    /**
     * @override
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
        if (! empty($field['autoincrement'])) {
            return "BIGSERIAL";
        }
        return "BIGINT";
    }

    /**
     * @override
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return "SMALLINT";
    }

    /**
     * @override
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        if (isset($fieldDeclaration['version']) && $fieldDeclaration['version'] == true) {
            return "TIMESTAMP";
        } else {
            return "DATETIME";
        }
    }

    /**
     * @override
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        return "DATE";
    }

    /**
     * @override
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return "TIME";
    }

    /**
     * @override
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        return "";
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
        return "BLOB";
    }

    /**
     * Get the platform name for this instance
     *
     * @return string
     */
    public function getName()
    {
        return "akibansrv";
    }

    /**
     * Gets the character casing of a column in an SQL result set.
     *
     * Akiban Server returns all column names in SQL result sets in lowercase.
     *
     * @param string $column The column name for which to get the correct character casing.
     * @return string The column name in the character casing used in SQL result sets.
     */
    public function getSQLResultCasing($column)
    {
        return strtolower($column);
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
        return "INSERT INTO " . $quotedTableName . " (" . $quotedIdentifierColumnName . ") VALUES (DEFAULT)";
    }

    /**
     * @inheritdoc
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        return "TRUNCATE TABLE " . $tableName . " " . (($cascade) ? "CASCADE" : "");
    }

    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = array(
            'smallint'      => 'smallint',
            'serial'        => 'integer',
            'int'           => 'integer',
            'integer'       => 'integer',
            'bigserial'     => 'bigint',
            'bigint'        => 'bigint',
            'boolean'       => 'boolean',
            'varchar'       => 'string',
            'char'          => 'string',
            'date'          => 'date',
            'datetime'      => 'datetime',
            'timestamp'     => 'datetime',
            'time'          => 'time',
            'float'         => 'float',
            'double'        => 'float',
            'real'          => 'float',
            'decimal'       => 'decimal',
            'numeric'       => 'decimal',
            'year'          => 'date',
            'blob'          => 'blob',
        );
    }

    public function getVarcharMaxLength()
    {
        return 65535;
    }

    protected function getReservedKeywordsClass()
    {
        return "Doctrine\DBAL\Platforms\Keywords\AkibanSrvKeywords";
    }

    /**
     * Gets the SQL Snippet used to declare a BLOB column type.
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        return "BLOB";
    }
}
