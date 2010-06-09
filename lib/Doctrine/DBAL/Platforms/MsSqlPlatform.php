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

use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Index;

/**
 * The MsSqlPlatform provides the behavior, features and SQL dialect of the
 * MySQL database platform.
 *
 * @since 2.0
 * @author Roman Borschel <roman@code-factory.org>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @todo Rename: MsSQLPlatform
 */
class MsSqlPlatform extends AbstractPlatform
{
    /**
     * Whether the platform prefers identity columns for ID generation.
     * MsSql prefers "autoincrement" identity columns since sequences can only
     * be emulated with a table.
     *
     * @return boolean
     * @override
     */
    public function prefersIdentityColumns()
    {
        return true;
    }

    /**
     * Whether the platform supports identity columns.
     * MsSql supports this through AUTO_INCREMENT columns.
     *
     * @return boolean
     * @override
     */
    public function supportsIdentityColumns()
    {
        return true;
    }

    /**
     * Whether the platform supports savepoints. MsSql does not.
     *
     * @return boolean
     * @override
     */
    public function supportsSavepoints()
    {
        return false;
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
        return 'CREATE DATABASE ' . $name;
    }

    /**
     * drop an existing database
     *
     * @param string $name name of the database that should be dropped
     * @return string
     * @override
     */
    public function getDropDatabaseSQL($name)
    {
        return 'DROP DATABASE ' . $name;
    }

    /**
     * @override
     */
    public function quoteIdentifier($str)
    {
        return '[' . $str . ']';
    }

    /**
     * @override
     */
    public function getDropForeignKeySQL($foreignKey, $table)
    {
        if ($foreignKey instanceof \Doctrine\DBAL\Schema\ForeignKeyConstraint) {
            $foreignKey = $foreignKey->getName();
        }

        if ($table instanceof \Doctrine\DBAL\Schema\Table) {
            $table = $table->getName();
        }

        return 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $foreignKey;
    }

    /**
     * Gets the sql statements for altering an existing table.
     *
     * The method returns an array of sql statements, since some platforms need several statements.
     *
     * @param TableDiff $diff
     * @return array
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $queryParts = array();
        if ($diff->newName !== false) {
            $queryParts[] =  'RENAME TO ' . $diff->newName;
        }

        foreach ($diff->addedColumns AS $fieldName => $column) {
            $queryParts[] = 'ADD ' . $this->getColumnDeclarationSQL($column->getName(), $column->toArray());
        }

        foreach ($diff->removedColumns AS $column) {
            $queryParts[] =  'DROP ' . $column->getName();
        }

        foreach ($diff->changedColumns AS $columnDiff) {
            /* @var $columnDiff Doctrine\DBAL\Schema\ColumnDiff */
            $column = $columnDiff->column;
            $queryParts[] =  'CHANGE ' . ($columnDiff->oldColumnName) . ' '
                    . $this->getColumnDeclarationSQL($column->getName(), $column->toArray());
        }

        foreach ($diff->renamedColumns AS $oldColumnName => $column) {
            $queryParts[] =  'CHANGE ' . $oldColumnName . ' '
                    . $this->getColumnDeclarationSQL($column->getName(), $column->toArray());
        }

        $sql = array();
        if (count($queryParts) > 0) {
            $sql[] = 'ALTER TABLE ' . $diff->name . ' ' . implode(", ", $queryParts);
        }
        $sql = array_merge($sql, $this->_getAlterTableIndexForeignKeySQL($diff));
        return $sql;
    }

    /**
     * @override
     */
    public function getEmptyIdentityInsertSQL($quotedTableName, $quotedIdentifierColumnName)
    {
        return 'INSERT INTO ' . $quotedTableName . ' DEFAULT VALUES';
    }

    /**
     * @override
     */
    public function getShowDatabasesSQL()
    {
        return 'SHOW DATABASES';
    }

    /**
     * @override
     */
    public function getListTablesSQL()
    {
        return "SELECT name FROM sysobjects WHERE type = 'U' ORDER BY name";
    }

    /**
     * @override
     */
    public function getListTableColumnsSQL($table)
    {
        return 'exec sp_columns @table_name = ' . $table;
    }

    /**
     * @override
     */
    public function getListTableForeignKeysSQL($table, $database = null)
    {
        return "SELECT f.name AS ForeignKey,
                SCHEMA_NAME (f.SCHEMA_ID) AS SchemaName,
                OBJECT_NAME (f.parent_object_id) AS TableName,
                COL_NAME (fc.parent_object_id,fc.parent_column_id) AS ColumnName,
                SCHEMA_NAME (o.SCHEMA_ID) ReferenceSchemaName,
                OBJECT_NAME (f.referenced_object_id) AS ReferenceTableName,
                COL_NAME(fc.referenced_object_id,fc.referenced_column_id) AS ReferenceColumnName,
                f.delete_referential_action_desc,
                f.update_referential_action_desc
                FROM sys.foreign_keys AS f
                INNER JOIN sys.foreign_key_columns AS fc
                INNER JOIN sys.objects AS o ON o.OBJECT_ID = fc.referenced_object_id
                ON f.OBJECT_ID = fc.constraint_object_id
                WHERE OBJECT_NAME (f.parent_object_id) = '" . $table . "'";
    }

    /**
     * @override
     */
    public function getListTableIndexesSQL($table)
    {
        return "exec sp_helpindex '" . $table . "'";
    }

    /**
     * Returns the regular expression operator.
     *
     * @return string
     * @override
     */
    public function getRegexpExpression()
    {
        return 'RLIKE';
    }

    /**
     * Returns global unique identifier
     *
     * @return string to get global unique identifier
     * @override
     */
    public function getGuidExpression()
    {
        return 'UUID()';
    }

    /**
     * @override
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        if ($startPos == false) {
            return 'CHARINDEX(' . $substr . ', ' . $str . ')';
        } else {
            return 'CHARINDEX(' . $substr . ', ' . $str . ', '.$startPos.')';
        }
    }

    /**
     * @override
     */
    public function getModExpression($expression1, $expression2)
    {
        return $expression1 . ' % ' . $expression2;
    }

    /**
     * @override
     */
    public function getTrimExpression($str, $pos = self::TRIM_UNSPECIFIED, $char = false)
    {
        // @todo
        $trimFn = '';
        $trimChar = ($char != false) ? (', ' . $char) : '';

        if ($pos == self::TRIM_LEADING) {
            $trimFn = 'LTRIM';
        } else if($pos == self::TRIM_TRAILING) {
            $trimFn = 'RTRIM';
        } else {
            return 'LTRIM(RTRIM(' . $str . '))';
        }

        return $trimFn . '(' . $str . ')';
    }

    /**
     * @override
     */
    public function getConcatExpression()
    {
        $args = func_get_args();
        return '(' . implode(' + ', $args) . ')';
    }

    /**
     * @override
     */
    public function getSubstringExpression($value, $from, $len = null)
    {
        if ( ! is_null($len)) {
            return 'SUBSTRING(' . $value . ', ' . $from . ', ' . $len . ')';
        }
        return 'SUBSTRING(' . $value . ', ' . $from . ', LEN(' . $value . ') - ' . $from . ' + 1)';
    }

    /**
     * @override
     */
    public function getLengthExpression($column)
    {
        return 'LEN(' . $column . ')';
    }

    /**
     * @override
     */
    public function getSetTransactionIsolationSQL($level)
    {
        return 'SET TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    /**
     * @override
     */
    public function getIntegerTypeDeclarationSQL(array $field)
    {
        return 'INT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * @override
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * @override
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return 'SMALLINT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /** @override */
    public function getVarcharTypeDeclarationSQL(array $field)
    {
        if ( ! isset($field['length'])) {
            if (array_key_exists('default', $field)) {
                $field['length'] = $this->getVarcharMaxLength();
            } else {
                $field['length'] = false;
            }
        }

        $length = ($field['length'] <= $this->getVarcharMaxLength()) ? $field['length'] : false;
        $fixed = (isset($field['fixed'])) ? $field['fixed'] : false;

        return $fixed ? ($length ? 'CHAR(' . $length . ')' : 'CHAR(255)')
                : ($length ? 'VARCHAR(' . $length . ')' : 'TEXT');
    }

    /** @override */
    public function getClobTypeDeclarationSQL(array $field)
    {
        return 'TEXT';
    }

    /**
     * @override
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        $autoinc = '';
        if ( ! empty($columnDef['autoincrement'])) {
            $autoinc = ' IDENTITY';
        }
        $unsigned = (isset($columnDef['unsigned']) && $columnDef['unsigned']) ? ' UNSIGNED' : '';

        return $unsigned . $autoinc;
    }

    /**
     * @override
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        // 6 - microseconds precision length
        return 'DATETIME2(6)';
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
        return 'TIME';
    }

    /**
     * @override
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return 'BIT';
    }

    /**
     * Adds an adapter-specific LIMIT clause to the SELECT statement.
     *
     * @param string $query
     * @param mixed $limit
     * @param mixed $offset
     * @link http://lists.bestpractical.com/pipermail/rt-devel/2005-June/007339.html
     * @return string
     */
    public function modifyLimitQuery($query, $limit, $offset = null)
    {
        if ($limit > 0) {
            $count = intval($limit);
            $offset = intval($offset);

            if ($offset < 0) {
                throw new Doctrine_Connection_Exception("LIMIT argument offset=$offset is not valid");
            }

            $orderby = stristr($query, 'ORDER BY');

            if ($orderby !== false) {
                // Ticket #1835: Fix for ORDER BY alias
                // Ticket #2050: Fix for multiple ORDER BY clause
                $order = str_ireplace('ORDER BY', '', $orderby);
                $orders = explode(',', $order);

                for ($i = 0; $i < count($orders); $i++) {
                    $sorts[$i] = (stripos($orders[$i], ' DESC') !== false) ? 'DESC' : 'ASC';
                    $orders[$i] = trim(preg_replace('/\s+(ASC|DESC)$/i', '', $orders[$i]));

                    // find alias in query string
                    $helperString = stristr($query, $orders[$i]);

                    $fromClausePos = strpos($helperString, ' FROM ');
                    $fieldsString = substr($helperString, 0, $fromClausePos + 1);

                    $fieldArray = explode(',', $fieldsString);
                    $fieldArray = array_shift($fieldArray);
                    $aux2 = preg_split('/ as /i', $fieldArray);

                    $aliases[$i] = trim(end($aux2));
                }
            }

            // Ticket #1259: Fix for limit-subquery in MSSQL
            $selectRegExp = 'SELECT\s+';
            $selectReplace = 'SELECT ';

            if (preg_match('/^SELECT(\s+)DISTINCT/i', $query)) {
                $selectRegExp .= 'DISTINCT\s+';
                $selectReplace .= 'DISTINCT ';
            }

            $query = preg_replace('/^'.$selectRegExp.'/i', $selectReplace . 'TOP ' . ($count + $offset) . ' ', $query);
            $query = 'SELECT * FROM (SELECT TOP ' . $count . ' * FROM (' . $query . ') AS ' . 'inner_tbl';

            if ($orderby !== false) {
                $query .= ' ORDER BY ';

                for ($i = 0, $l = count($orders); $i < $l; $i++) {
                    if ($i > 0) { // not first order clause
                        $query .= ', ';
                    }

                    $query .= 'inner_tbl' . '.' . $aliases[$i] . ' ';
                    $query .= (stripos($sorts[$i], 'ASC') !== false) ? 'DESC' : 'ASC';
                }
            }

            $query .= ') AS ' . 'outer_tbl';

            if ($orderby !== false) {
                $query .= ' ORDER BY ';

                for ($i = 0, $l = count($orders); $i < $l; $i++) {
                    if ($i > 0) { // not first order clause
                        $query .= ', ';
                    }

                    $query .= 'outer_tbl' . '.' . $aliases[$i] . ' ' . $sorts[$i];
                }
            }
        }

        return $query;
    }

    /**
     * @override
     */
    public function convertBooleans($item)
    {
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                if (is_bool($value) || is_numeric($item)) {
                    $item[$key] = ($value) ? 'TRUE' : 'FALSE';
                }
            }
        } else {
           if (is_bool($item) || is_numeric($item)) {
               $item = ($item) ? 'TRUE' : 'FALSE';
           }
        }
        return $item;
    }

    /**
     * @override
     */
    public function getCreateTemporaryTableSnippetSQL()
    {
        return "CREATE TABLE";
    }

    /**
     * @override
     */
    public function getTemporaryTableName($tableName)
    {
        return '#' . $tableName;
    }

    /**
     * @override
     */
    public function getDateTimeFormatString()
    {
        return 'Y-m-d H:i:s.u';
    }

    /**
     * @override
     */
    public function getIndexDeclarationSQL($name, Index $index)
    {
        // @todo
        return $this->getUniqueConstraintDeclarationSQL($name, $index);
    }

    /**
     * Get the platform name for this instance
     *
     * @return string
     */
    public function getName()
    {
        return 'mssql';
    }

    protected function initializeDoctrineTypeMappings()
    {
        
    }
}
