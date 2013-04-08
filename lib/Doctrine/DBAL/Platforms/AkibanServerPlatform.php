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

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\Table;

/**
 * AkibanServerPlatform.
 *
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 * @since  2.4
 */
class AkibanServerPlatform extends AbstractPlatform
{
    /**
     * {@inheritdoc}
     */
    public function getSubstringExpression($value, $from, $len = null)
    {
        if ($len === null) {
            return "SUBSTR(" . $value . ", " . $from . ")";
        }
        return "SUBSTR(" . $value . ", " . $from . ", " . $len . ")";
    }

    /**
     * {@inheritdoc}
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        if ($startPos !== false) {
            $str = $this->getSubstringExpression($str, $startPos);
            return "CASE WHEN (POSITION(" . $substr . " IN " . $str . ") = 0) THEN 0 ELSE (POSITION(" . $substr . " IN " . $str . ") + " . ($startPos-1) . ") END";
        }
        return "POSITION(" . $substr . " IN " . $str . ")";
    }

    /**
     * {@inheritdoc}
     */
    public function getDateDiffExpression($date1, $date2)
    {
        return "DATEDIFF(" . $date1 . ", " . $date2 . ")";
    }

    /**
     * {@inheritdoc}
     */
    public function getDateAddDaysExpression($date, $days)
    {
        return "DATE_ADD(" . $date . ", INTERVAL " . $days . " DAY)";
    }

    /**
     * {@inheritdoc}
     */
    public function getDateSubDaysExpression($date, $days)
    {
        return "DATE_SUB(" . $date . ", INTERVAL " . $days . " DAY)";
    }

    /**
     * {@inheritdoc}
     */
    public function getDateAddMonthExpression($date, $months)
    {
        return "DATE_ADD(" . $date . ", INTERVAL " . $months . " MONTH)";
    }

    /**
     * {@inheritdoc}
     */
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
     * Akiban does not support this syntax in 1.4.0 release.
     */
    public function getForUpdateSQL()
    {
        return "";
    }

    /**
     * {@inheritdoc}
     */
    public function supportsSequences()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsSchemas()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsCommentOnStatement()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function prefersSequences()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsSavepoints()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsReleaseSavepoints()
    {
        return $this->supportsSavepoints();
    }

    /**
     * {@inheritdoc}
     */
    public function supportsForeignKeyConstraints()
    {    
        return false;
    }    

    /**
     * {@inheritdoc}
     */
    public function supportsForeignKeyOnUpdate()
    {    
        return $this->supportsForeignKeyConstraints();
    }  


    /**
     * {@inheritdoc}
     */
    public function getListDatabasesSQL()
    {
        return "SELECT schema_name FROM information_schema.schemata";
    }

    /**
     * {@inheritdoc}
     */
    public function getListSequencesSQL($database)
    {
        return "SELECT sequence_name, sequence_schema as schemaname, increment as increment_by, minimum_value as min_value " .
               "FROM information_schema.sequences " .
               "WHERE sequence_name != 'information_schema'";
    }

    /**
     * {@inheritdoc}
     */
    public function getListTablesSQL()
    {
        return "SELECT table_name, table_schema " .
                "FROM information_schema.tables WHERE table_schema != 'information_schema'";
    }

    /**
     * {@inheritdoc}
     */
    public function getListViewsSQL($database)
    {
        return "SELECT table_name as viewname, view_definition as definition FROM information_schema.views";
    }

    /**
     * {@inheritdoc}
     */
    public function getCreateViewSQL($name, $sql)
    {
        return "CREATE VIEW " . $name . " AS " . $sql;
    }

    /**
     * {@inheritdoc}
     */
    public function getDropViewSQL($name)
    {
        return "DROP VIEW " . $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getListTableConstraintsSQL($table)
    {
        // TODO - do we only want unique and primary key indexes here?
        return "SELECT index_name " .
               "FROM information_schema.indexes " .
               "WHERE schema_name != 'information_schema' AND " .
               "table_name = '" . $table . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        $schemaPredicate = "";
        if (null !== $currentDatabase) {
            $schemaPredicate = "i.schema_name = '" . $currentDatabase . "' and ";
        }
        return "SELECT i.table_name as table_name, i.index_name as index_name, i.is_unique as is_unique, i.index_type as index_type, c.column_name as column_name " .
               "FROM information_schema.indexes i join information_schema.index_columns c on i.index_name = c.index_name and i.table_name = c.index_table_name " .
               "WHERE c.schema_name != 'information_schema' and " . $schemaPredicate . "i.table_name = '" . $table . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function getListTableColumnsSQL($table, $database = null)
    {
        $schemaPredicate = "";
        if (null !== $database) {
            $schemaPredicate = "c.schema_name = '" . $database . "' and ";
        }
        return "SELECT c.column_name as column_name, c.length as length, c.type as type, c.nullable as nullable, " .
               "c.character_set_name as character_set_name, c.collation_name as collation_name, " .
               "i.index_type as index_type " .
               "FROM information_schema.columns c left outer join information_schema.indexes i on c.table_name = i.table_name " .
               "WHERE c.schema_name != 'information_schema' and " . $schemaPredicate .
               "c.table_name = '" . $table . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function getCreateDatabaseSQL($name)
    {
        return "CREATE SCHEMA " . $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getDropDatabaseSQL($name)
    {
        return "DROP SCHEMA IF EXISTS " . $name . " CASCADE";
    }

    /**
     * {@inheritdoc}
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
                // TODO - Akiban does not support comments on tables or columns in 1.4.0
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
                $query = "ALTER " . $oldColumnName . $this->getDefaultValueDeclarationSQL($column->toArray());
                $sql[] = "ALTER TABLE " . $diff->name . " " . $query;
            }
            if ($columnDiff->hasChanged('notnull')) {
                $query = 'ALTER ' . $oldColumnName . ' ' . ($column->getNotNull() ? 'NOT NULL' : 'NULL');
                $sql[] = "ALTER TABLE " . $diff->name . " " . $query;
            }
            if ($columnDiff->hasChanged('autoincrement')) {
                // TODO - Akiban does not support modifying sequences created with SERIAL in 1.4.0
            }
            if ($columnDiff->hasChanged('comment') && $comment = $this->getColumnComment($column)) {
                // TODO - Akiban does not support comments on tables or columns in 1.4.0
            }
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }
            // TODO - Akiban does not support renaming columns with ALTER in 1.4.0
        }

        $tableSql = array();

        if (! $this->onSchemaAlterTable($diff, $tableSql)) {
            if ($diff->newName !== false) {
                // TODO
            }
            // TODO - handle foreign keys alter statements
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreateSequenceSQL(\Doctrine\DBAL\Schema\Sequence $sequence)
    {
        return "CREATE SEQUENCE " . $sequence->getQuotedName($this) .
               " START WITH " . $sequence->getInitialValue() .
               " INCREMENT BY " . $sequence->getAllocationSize() .
               " MINVALUE " . $sequence->getInitialValue();
    }

    /**
     * {@inheritdoc}
     */
    public function getDropSequenceSQL($sequence)
    {
        if ($sequence instanceof \Doctrine\DBAL\Schema\Sequence) {
            $sequence = $sequence->getQuotedName($this);
        }
        return "DROP SEQUENCE " . $sequence . " RESTRICT";
    }

    /**
     * {@inheritdoc}
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

    /**
     * {@inheritdoc}
     */
    public function getSequenceNextValSQL($sequenceName)
    {
        return "SELECT NEXT VALUE FOR ". $sequenceName;
    }

    /**
     * {@inheritdoc}
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return "BOOLEAN";
    }

    /**
     * {@inheritdoc}
     */
    public function getIntegerTypeDeclarationSQL(array $field)
    {
        if (! empty($field['autoincrement'])) {
            return "SERIAL";
        }
        return "INT";
    }

    /**
     * {@inheritdoc}
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
        if (! empty($field['autoincrement'])) {
            return "BIGSERIAL";
        }
        return "BIGINT";
    }

    /**
     * {@inheritdoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return "SMALLINT";
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        if (isset($fieldDeclaration['version']) && $fieldDeclaration['version'] === true) {
            return "TIMESTAMP";
        }
        return "DATETIME";
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        return "DATE";
    }

    /**
     * {@inheritdoc}
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return "TIME";
    }

    /**
     * {@inheritdoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        return "";
    }

    /**
     * {@inheritdoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? ($length ? 'CHAR(' . $length . ')' : 'CHAR(255)')
                : ($length ? 'VARCHAR(' . $length . ')' : 'VARCHAR(255)');
    }

    /**
     * {@inheritdoc}
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
        return "BLOB";
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return "akibansrv";
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLResultCasing($column)
    {
        return strtolower($column);
    }

    /**
     * {@inheritdoc}
     */
    public function getEmptyIdentityInsertSQL($quotedTableName, $quotedIdentifierColumnName)
    {
        return "INSERT INTO " . $quotedTableName . " (" . $quotedIdentifierColumnName . ") VALUES (DEFAULT)";
    }

    /**
     * {@inheritdoc}
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
            'longblob'      => 'blob',
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
     * {@inheritdoc}
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        return "BLOB";
    }
}

