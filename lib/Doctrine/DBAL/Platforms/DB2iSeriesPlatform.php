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
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;

class DB2iSeriesPlatform extends AbstractPlatform {

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = array(
            'smallint' => 'smallint',
            'bigint' => 'bigint',
            'integer' => 'integer',
            'time' => 'time',
            'date' => 'date',
            'varchar' => 'string',
            'char' => 'string',
            'character' => 'string',
            'clob' => 'text',
            'decimal' => 'decimal',
            'double' => 'float',
            'real' => 'float',
            'timestamp' => 'datetime',
            'timestmp' => 'datetime',
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? ($length ? 'CHAR(' . $length . ')' : 'CHAR(255)') : ($length ? 'VARCHAR(' . $length . ')' : 'VARCHAR(255)');
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

    public function getDecimalTypeDeclarationSQL(array $columnDef)
    {
        $columnDef['precision'] = (!isset($columnDef['precision']) || empty($columnDef['precision'])) ? 10 : $columnDef['precision'];
        $columnDef['scale'] = (!isset($columnDef['scale']) || empty($columnDef['scale'])) ? 0 : $columnDef['scale'];

        return 'DECIMAL(' . $columnDef['precision'] . ', ' . $columnDef['scale'] . ')';
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
        if (!empty($columnDef['autoincrement'])) {
            $autoinc = ' GENERATED ALWAYS AS IDENTITY  (START WITH 1, INCREMENT BY 1)';
            //$autoinc = ' GENERATED BY DEFAULT AS IDENTITY';
        }

        return $autoinc;
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        if (isset($fieldDeclaration['version']) && $fieldDeclaration['version'] == true) {
            return "TIMESTAMP WITH DEFAULT";
        }

        return 'TIMESTAMP';
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

    public function getListDatabasesSQL()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getListSequencesSQL($database)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getListTableConstraintsSQL($table)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * This code fragment is originally from the Zend_Db_Adapter_Db2 class.
     *
     * @license New BSD License
     * @param  string $table
     * @param string $database
     * @return string
     */
    public function getListTableColumnsSQL($table, $database = null)
    {
        return " SELECT c.table_schema as tabschema,c.table_name as tabname,c.column_name as colname,c.ordinal_position as colno,
                    trim(c.data_type) as typename,c.column_default as default,c.is_nullable as nulls,c.length,c.numeric_scale as scale,
                    case
                        when c.is_identity  ='YES' then 'Y'
                        else 'N'
                        end identity,
                    case 
                      when tc.constraint_type = 'PRIMARY KEY' then 'P'
                      else tc.constraint_type
                    end tabconsttype,k.ORDINAL_POSITION as colseq
                FROM " . $database . ".syscolumns c
                left join
                (
                   " . $database . ".SYSKEYCST k join " . $database . ".SYSCST tc on
                   (
                      k.table_schema = tc.table_schema
                      and k.table_name = tc.table_name
                      and tc.constraint_type = 'PRIMARY KEY'
                   )
                )
                on
                (
                   c.table_schema = k.table_schema
                   and c.table_name = k.table_name
                   and c.column_name = k.column_name
                )
                WHERE UPPER(C.TABLE_NAME) = UPPER('" . $table . "')";
    }

    public function getListTablesSQL($database = null)
    {
        return "SELECT TABLE_NAME FROM " . \strtoupper($database) . ".SYSTABLES WHERE TYPE = 'T'";
    }

    public function getListUsersSQL()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function getListViewsSQL($database)
    {
        return "SELECT NAME, TEXT FROM SYSIBM.SYSVIEWS";
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        return "SELECT i.index_name as NAME,t.column_names as COLNAMES ,i.is_unique as UNIQUERULE
            FROM QSYS2.SYSINDEXES i, qsys2.SYSINDEXSTAT t
            where i.index_name = t.index_name
            and i.table_name = UPPER('" . $table . "')
                union        
        SELECT s.constraint_name as NAME, 'ID' as COLNAMES ,'P' as UNIQUERULE
FROM " . $currentDatabase . ".SYSCST s left join sysibm.sysdummy1 a on (s.table_name = a.ibmreqd)";
    }

    public function getListTableForeignKeysSQL($table, $database = null)
    {
        return "SELECT table_name as TBNAME, rc.constraint_name as RELNAME, pktable_name as REFTBNAME,
rc.delete_rule as DELETERULE, rc.update_rule as UPDATERULE,fkcolumn_name as FKCOLNAMES, pkcolumn_name as PKCOLNAMES 
FROM  " . $database . ".SYSREFCST rc,  " . $database . ".SYSCST c, SYSIBM.SQLFOREIGNKEYS fk
where c.constraint_name = rc.constraint_name and fk.fk_name = rc.constraint_name
and table_name = UPPER('" . $table . "')
";
    }

    public function getCreateViewSQL($name, $sql)
    {
        return "CREATE VIEW " . $name . " AS " . $sql;
    }

    public function getDropViewSQL($name)
    {
        return "DROP VIEW " . $name;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropSequenceSQL($sequence)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getSequenceNextValSQL($sequenceName)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateDatabaseSQL($database)
    {
        return "CREATE DATABASE " . $database;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropDatabaseSQL($database)
    {
        return "DROP DATABASE " . $database . ";";
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
        return 'VALUES CURRENT DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentTimeSQL()
    {
        return 'VALUES CURRENT TIME';
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentTimestampSQL()
    {
        return "VALUES CURRENT TIMESTAMP";
    }

    /**
     * {@inheritDoc}
     */
    public function getIndexDeclarationSQL($name, Index $index)
    {
        return $this->getUniqueConstraintDeclarationSQL($name, $index);
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
     * Obtain DBMS specific SQL code portion needed to declare a generic type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string $name   name the field to be declared.
     * @param array  $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the text
     *          field. If this argument is missing the field should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      default
     *          Text value to be used as default for this field.
     *
     *      notnull
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to null.
     *      charset
     *          Text value with the default CHARACTER SET for this field.
     *      collation
     *          Text value with the default COLLATION for this field.
     *      unique
     *          unique constraint
     *      check
     *          column check constraint
     *      columnDefinition
     *          a string that defines the complete column
     *
     * @return string  DBMS specific SQL code portion that should be used to declare the column.
     */
    public function getAlterColumnDeclarationSQL($name, array $field, $alter)
    {
        if (isset($field['columnDefinition'])) {
            $columnDef = $this->getCustomTypeDeclarationSQL($field);
        } else {
            $default = $this->getDefaultValueDeclarationSQL($field);

            $charset = (isset($field['charset']) && $field['charset']) ?
                    ' ' . $this->getColumnCharsetDeclarationSQL($field['charset']) : '';

            $collation = (isset($field['collation']) && $field['collation']) ?
                    ' ' . $this->getColumnCollationDeclarationSQL($field['collation']) : '';


            $notnull = (isset($field['notnull']) && $field['notnull']) ? ' NOT NULL' : '';

            // Cannot add notnull to existing tables in db2
            $notnull = "";

            $unique = (isset($field['unique']) && $field['unique']) ?
                    ' ' . $this->getUniqueFieldDeclarationSQL() : '';

            $check = (isset($field['check']) && $field['check']) ?
                    ' ' . $field['check'] : '';

            $typeDecl = $field['type']->getSqlDeclaration($field, $this);

            $typePref = (isset($alter) && $alter) ? 'SET DATA TYPE ' : '';

            $columnDef = $typePref . $typeDecl . $charset . $default . $notnull . $unique . $check . $collation;
        }

        if ($this->supportsInlineColumnComments() && isset($field['comment']) && $field['comment']) {
            $columnDef .= " COMMENT '" . $field['comment'] . "'";
        }

        return $name . ' ' . $columnDef;
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $sql = array();
        $columnSql = array();

        $queryParts = array();
        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $queryParts[] = 'ADD COLUMN ' . $this->getAlterColumnDeclarationSQL($column->getQuotedName($this), $column->toArray(), false);
        }

        foreach ($diff->removedColumns as $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $queryParts[] = 'DROP COLUMN ' . $column->getQuotedName($this);
        }

        foreach ($diff->changedColumns as $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            /* @var $columnDiff \Doctrine\DBAL\Schema\ColumnDiff */
            $column = $columnDiff->column;
            //$queryParts[] =  'ALTER ' . ($columnDiff->oldColumnName) . ' '
            $queryParts[] = 'ALTER COLUMN '
                    . $this->getAlterColumnDeclarationSQL($column->getQuotedName($this), $column->toArray(), true);
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $queryParts[] = 'RENAME ' . $oldColumnName . ' TO ' . $column->getQuotedName($this);
        }

        $tableSql = array();

        if (!$this->onSchemaAlterTable($diff, $tableSql)) {
            if (count($queryParts) > 0) {
                $sql[] = 'ALTER TABLE ' . $diff->name . ' ' . implode(" ", $queryParts);
            }

            $sql = array_merge($sql, $this->_getAlterTableIndexForeignKeySQL($diff));

            if ($diff->newName !== false) {
                $sql[] = 'RENAME TABLE TO ' . $diff->newName;
            }
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultValueDeclarationSQL($field)
    {
        //var_dump($field);

        /**
         * If notnull is set a default value is defined according its data type
         */
        if (isset($field['notnull']) && $field['notnull'] && !isset($field['default'])) {
            if (in_array((string) $field['type'], array("Integer", "BigInteger", "SmallInteger", "Decimal"))) {
                $field['default'] = 0;
            } else if ((string) $field['type'] == "DateTime") {
                $field['default'] = "00-00-00 00:00:00";
            } else if ((string) $field['type'] == "Date") {
                $field['default'] = "00-00-00";
            } else if ((string) $field['type'] == "Time") {
                $field['default'] = "00:00:00";
            } else {
                $field['default'] = '';
            }
        }

        unset($field['default']); // @todo this needs fixing
        if (isset($field['version']) && $field['version']) {
            if ((string) $field['type'] != "DateTime") {
                $field['default'] = "1";
            }
        }

        //autoincrement field can have default value
        if (isset($field['autoincrement']) && $field['autoincrement']) {
            unset($field['default']);
        }

        /*
         * The SQL default is constructed according its data type
         * AbstractPlatform lacks of Decimal type so this code is
         * overwritten here
         */
//        $default = empty($field['notnull']) ? ' DEFAULT NULL' : '';
//
//        if (isset($field['default'])) {
//            $default = " DEFAULT '".$field['default']."'";
//            if (isset($field['type'])) {
//                if (in_array((string)$field['type'], array("Integer", "BigInteger", "SmallInteger", "Decimal"))) {
//                    $default = " DEFAULT ".$field['default'];
//                } else if ((string)$field['type'] == 'DateTime' && $field['default'] == $this->getCurrentTimestampSQL()) {
//                    $default = " DEFAULT ".$this->getCurrentTimestampSQL();
//                } else if ((string) $field['type'] == 'Boolean') {
//                    $default = " DEFAULT '" . $this->convertBooleans($field['default']) . "'";
//                }
//            }
//        }
        //return($default);
        return parent::getDefaultValueDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getEmptyIdentityInsertSQL($tableName, $identifierColumnName)
    {
        return 'INSERT INTO ' . $tableName . ' (' . $identifierColumnName . ') VALUES (DEFAULT)';
    }

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
        $offset = (int) (($offset)? : 0);

        // Todo OVER() needs ORDER BY data!
        $sql = 'SELECT db22.* FROM (SELECT ROW_NUMBER() OVER() AS DC_ROWNUM, db21.* ' .
                'FROM (' . $query . ') db21) db22 WHERE db22.DC_ROWNUM BETWEEN ' . ($offset + 1) . ' AND ' . ($offset + $limit);

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

        return 'LOCATE(' . $substr . ', ' . $str . ', ' . $startPos . ')';
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

    /**
     * Return the FOREIGN KEY query section dealing with non-standard options
     * as MATCH, INITIALLY DEFERRED, ON UPDATE, ...
     *
     * @param ForeignKeyConstraint $foreignKey     foreign key definition
     *
     * @return string
     */
    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey)
    {
        $query = '';
        if ($this->supportsForeignKeyOnUpdate() && $foreignKey->hasOption('onUpdate')) {
            $query .= ' ON UPDATE ' . $this->getForeignKeyReferentialActionSQLUpdate($foreignKey->getOption('onUpdate'));
        }
        if ($foreignKey->hasOption('onDelete')) {
            $query .= ' ON DELETE ' . $this->getForeignKeyReferentialActionSQLDelete($foreignKey->getOption('onDelete'));
        }
        return $query;
    }

    /**
     * returns given referential action in uppercase if valid, otherwise throws
     * an exception
     *
     * @throws \InvalidArgumentException if unknown referential action given
     *
     * @param string $action    foreign key referential action
     *
     * @return string
     */
    public function getForeignKeyReferentialActionSQLUpdate($action)
    {
        $upper = strtoupper($action);
        switch ($upper) {
            case 'CASCADE': 
                return 'RESTRICT';
            case 'NO ACTION':
            case 'RESTRICT':
            case 'SET DEFAULT':
                return $upper;
            default:
                throw new \InvalidArgumentException('Invalid foreign key action: ' . $upper);
        }
    }

    public function getForeignKeyReferentialActionSQLDelete($action)
    {
        $upper = strtoupper($action);
        switch ($upper) {
            case 'CASCADE':
            case 'SET NULL':
            case 'NO ACTION':
            case 'RESTRICT':
            case 'SET DEFAULT':
                return $upper;
            default:
                throw new \InvalidArgumentException('Invalid foreign key action: ' . $upper);
        }
    }

    /**
     * Get the Doctrine type that is mapped for the given database column type.
     *
     * @param  string $dbType
     *
     * @return string
     */
    public function getDoctrineTypeMapping($dbType)
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeDoctrineTypeMappings();
        }
        $dbType = strtolower($dbType);
        if (!isset($this->doctrineTypeMapping[$dbType])) {
            throw new \Doctrine\DBAL\DBALException("Unknown database type " . $dbType . " requested, " . get_class($this) . " may not support it.");
        }

        return $this->doctrineTypeMapping[$dbType];
    }

}