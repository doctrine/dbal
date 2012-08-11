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

use Doctrine\DBAL\Schema\TableDiff,
    Doctrine\DBAL\Schema\Table;

/**
 * AkibanServerPlatform.
 *
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 * @since 2.3
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
        // TODO
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
        return 'DATEDIFF(' . $date1 . ', ' . $date2 . ')';
    }

    public function getDateAddDaysExpression($date, $days)
    {
        return 'DATE_ADD(' . $date . ', INTERVAL ' . $days . ' DAY)';
    }

    public function getDateSubDaysExpression($date, $days)
    {
        return 'DATE_SUB(' . $date . ', INTERVAL ' . $days . ' DAY)';
    }

    public function getDateAddMonthExpression($date, $months)
    {
        return 'DATE_ADD(' . $date . ', INTERVAL ' . $months . ' MONTH)';
    }

    public function getDateSubMonthExpression($date, $months)
    {
        return 'DATE_SUB(' . $date . ', INTERVAL ' . $months . ' MONTH)';
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

    public function getListDatabasesSQL()
    {
        return "SELECT schema_name FROM information_schema.schemata";
    }

    public function getListSequencesSQL($database)
    {
        // TODO
    }

    public function getListTablesSQL()
    {
        return "SELECT table_name, table_schema
                FROM information_schema.tables WHERE table_schema != 'information_schema';
    }

    public function getListViewsSQL($database)
    {
        return "SELECT table_name as viewname, view_definition as definition FROM information_schema.views";
    }

    public function getListTableForeignKeysSQL($table, $database = null)
    {
        // TODO
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
        // TODO
    }

    /**
     * @param  string $table
     * @return string
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        // TODO
    }

    /**
     * @param string $table
     * @param string $classAlias
     * @param string $namespaceAlias
     * @return string
     */
    private function getTableWhereClause($table, $classAlias = 'c', $namespaceAlias = 'n')
    {
        // TODO
    }

    public function getListTableColumnsSQL($table, $database = null)
    {
        // TODO
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
        return 'CREATE SCHEMA ' . $name;
    }

    /**
     * drop an existing database
     *
     * @param string $name name of the database that should be dropped
     * @access public
     */
    public function getDropDatabaseSQL($name)
    {
        return "DROP SCHEMA " . $name . " CASCADE";
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
        // TODO
    }

    /**
     * generates the sql for altering an existing table in Akiban Server
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
        // TODO
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

    public function getAlterSequenceSQL(\Doctrine\DBAL\Schema\Sequence $sequence)
    {
        // TODO
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
        return 'DROP SEQUENCE ' . $sequence . ' RESTRICT';
    }

    /**
     * @param  \Doctrine\DBAL\Schema\ForeignKeyConstraint|string $foreignKey
     * @param  Table|string $table
     * @return string
     */
    public function getDropForeignKeySQL($foreignKey, $table)
    {
        // TODO
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
        // TODO
    }

    /**
     * Postgres wants boolean values converted to the strings 'true'/'false'.
     *
     * @param array $item
     * @override
     */
    public function convertBooleans($item)
    {
        // TODO
    }

    public function getSequenceNextValSQL($sequenceName)
    {
        return "SELECT NEXT VALUE FOR ". $sequenceName;
    }

    public function getSetTransactionIsolationSQL($level)
    {
        // TODO
    }

    /**
     * @override
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        // TODO
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
        // TODO
    }

    /**
     * @override
     */
    public function getDateTimeTzTypeDeclarationSQL(array $fieldDeclaration)
    {
        // TODO
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
        // TODO
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
        return 'BLOB';
    }

    /**
     * Get the platform name for this instance
     *
     * @return string
     */
    public function getName()
    {
        return 'akibansrv';
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

    public function getDateTimeTzFormatString()
    {
        // TODO
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

    protected function initializeDoctrineTypeMappings()
    {
        // TODO
    }

    public function getVarcharMaxLength()
    {
        return 65535;
    }

    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\AkibanSrvKeywords';
    }

    /**
     * Gets the SQL Snippet used to declare a BLOB column type.
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        return 'BLOB';
    }
}
