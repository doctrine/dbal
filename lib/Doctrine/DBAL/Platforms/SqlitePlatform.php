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
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;

/**
 * The SqlitePlatform class describes the specifics and dialects of the SQLite
 * database platform.
 *
 * @since 2.0
 * @author Roman Borschel <roman@code-factory.org>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @todo Rename: SQLitePlatform
 */
class SqlitePlatform extends AbstractPlatform
{
    /**
     * {@inheritDoc}
     */
    public function getRegexpExpression()
    {
        return 'RLIKE';
    }

    /**
     * {@inheritDoc}
     */
    public function getNowExpression($type = 'timestamp')
    {
        switch ($type) {
            case 'time':
                return 'time(\'now\')';
            case 'date':
                return 'date(\'now\')';
            case 'timestamp':
            default:
                return 'datetime(\'now\')';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getTrimExpression($str, $pos = self::TRIM_UNSPECIFIED, $char = false)
    {
        $trimChar = ($char != false) ? (', ' . $char) : '';

        switch ($pos) {
            case self::TRIM_LEADING:
                $trimFn = 'LTRIM';
                break;

            case self::TRIM_TRAILING:
                $trimFn = 'RTRIM';
                break;

            default:
                $trimFn = 'TRIM';
        }

        return $trimFn . '(' . $str . $trimChar . ')';
    }

    /**
     * {@inheritDoc}
     *
     * SQLite only supports the 2 parameter variant of this function
     */
    public function getSubstringExpression($value, $position, $length = null)
    {
        if ($length !== null) {
            return 'SUBSTR(' . $value . ', ' . $position . ', ' . $length . ')';
        }

        return 'SUBSTR(' . $value . ', ' . $position . ', LENGTH(' . $value . '))';
    }

    /**
     * {@inheritDoc}
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        if ($startPos == false) {
            return 'LOCATE('.$str.', '.$substr.')';
        }

        return 'LOCATE('.$str.', '.$substr.', '.$startPos.')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateDiffExpression($date1, $date2)
    {
        return 'ROUND(JULIANDAY('.$date1 . ')-JULIANDAY('.$date2.'))';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddDaysExpression($date, $days)
    {
        return "DATE(" . $date . ",'+". $days . " day')";
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubDaysExpression($date, $days)
    {
        return "DATE(" . $date . ",'-". $days . " day')";
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddMonthExpression($date, $months)
    {
        return "DATE(" . $date . ",'+". $months . " month')";
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubMonthExpression($date, $months)
    {
        return "DATE(" . $date . ",'-". $months . " month')";
    }

    /**
     * {@inheritDoc}
     */
    protected function _getTransactionIsolationLevelSQL($level)
    {
        switch ($level) {
            case \Doctrine\DBAL\Connection::TRANSACTION_READ_UNCOMMITTED:
                return 0;
            case \Doctrine\DBAL\Connection::TRANSACTION_READ_COMMITTED:
            case \Doctrine\DBAL\Connection::TRANSACTION_REPEATABLE_READ:
            case \Doctrine\DBAL\Connection::TRANSACTION_SERIALIZABLE:
                return 1;
            default:
                return parent::_getTransactionIsolationLevelSQL($level);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSetTransactionIsolationSQL($level)
    {
        return 'PRAGMA read_uncommitted = ' . $this->_getTransactionIsolationLevelSQL($level);
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
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return 'BOOLEAN';
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $field)
    {
        return $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
        return $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getTinyIntTypeDeclarationSql(array $field)
    {
        return $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getMediumIntTypeDeclarationSql(array $field)
    {
        return $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATETIME';
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
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        return 'INTEGER';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL($name, array $columns, array $options = array())
    {
        $name = str_replace('.', '__', $name);
        $queryFields = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && ! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $name => $definition) {
                $queryFields .= ', ' . $this->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if (isset($options['primary']) && ! empty($options['primary'])) {
            $keyColumns = array_unique(array_values($options['primary']));
            $queryFields.= ', PRIMARY KEY('.implode(', ', $keyColumns).')';
        }

        if (isset($options['foreignKeys'])) {
            foreach ($options['foreignKeys'] as $foreignKey) {
                $queryFields.= ', '.$this->getForeignKeyDeclarationSQL($foreignKey);
            }
        }

        $query[] = 'CREATE TABLE ' . $name . ' (' . $queryFields . ')';

        if (isset($options['alter']) && true === $options['alter']) {
            return $query;
        }

        if (isset($options['indexes']) && ! empty($options['indexes'])) {
            foreach ($options['indexes'] as $index => $indexDef) {
                $query[] = $this->getCreateIndexSQL($indexDef, $name);
            }
        }

        return $query;
    }

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? ($length ? 'CHAR(' . $length . ')' : 'CHAR(255)')
                : ($length ? 'VARCHAR(' . $length . ')' : 'TEXT');
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
        return 'CLOB';
    }

    public function getListTableConstraintsSQL($table)
    {
        $table = str_replace('.', '__', $table);

        return "SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name = '$table' AND sql NOT NULL ORDER BY name";
    }

    public function getListTableColumnsSQL($table, $currentDatabase = null)
    {
        $table = str_replace(".", "__", $table);

        return "PRAGMA table_info($table)";
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        $table = str_replace('.', '__', $table);

        return "PRAGMA index_list($table)";
    }

    public function getListTablesSQL()
    {
        return "SELECT name FROM sqlite_master WHERE type = 'table' AND name != 'sqlite_sequence' AND name != 'geometry_columns' AND name != 'spatial_ref_sys' "
             . "UNION ALL SELECT name FROM sqlite_temp_master "
             . "WHERE type = 'table' ORDER BY name";
    }

    /**
     * {@inheritDoc}
     */
    public function getListViewsSQL($database)
    {
        return "SELECT name, sql FROM sqlite_master WHERE type='view' AND sql NOT NULL";
    }

    public function getCreateViewSQL($name, $sql)
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    public function getDropViewSQL($name)
    {
        return 'DROP VIEW '. $name;
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
    public function getName()
    {
        return 'sqlite';
    }

    /**
     * {@inheritDoc}
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        $tableName = str_replace('.', '__', $tableName);
        return 'DELETE FROM '.$tableName;
    }

    /**
     * User-defined function for Sqlite that is used with PDO::sqliteCreateFunction()
     *
     * @param  int|float $value
     *
     * @return float
     */
    static public function udfSqrt($value)
    {
        return sqrt($value);
    }

    /**
     * User-defined function for Sqlite that implements MOD(a, b)
     *
     * @param integer $a
     * @param integer $b
     *
     * @return integer
     */
    static public function udfMod($a, $b)
    {
        return ($a % $b);
    }

    /**
     * @param string $str
     * @param string $substr
     * @param integer $offset
     *
     * @return integer
     */
    static public function udfLocate($str, $substr, $offset = 0)
    {
        $pos = strpos($str, $substr, $offset);
        if ($pos !== false) {
            return $pos+1;
        }

        return 0;
    }

    public function getForUpdateSql()
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = array(
            'boolean'          => 'boolean',
            'tinyint'          => 'boolean',
            'smallint'         => 'smallint',
            'mediumint'        => 'integer',
            'int'              => 'integer',
            'integer'          => 'integer',
            'serial'           => 'integer',
            'bigint'           => 'bigint',
            'bigserial'        => 'bigint',
            'clob'             => 'text',
            'tinytext'         => 'text',
            'mediumtext'       => 'text',
            'longtext'         => 'text',
            'text'             => 'text',
            'varchar'          => 'string',
            'longvarchar'      => 'string',
            'varchar2'         => 'string',
            'nvarchar'         => 'string',
            'image'            => 'string',
            'ntext'            => 'string',
            'char'             => 'string',
            'date'             => 'date',
            'datetime'         => 'datetime',
            'timestamp'        => 'datetime',
            'time'             => 'time',
            'float'            => 'float',
            'double'           => 'float',
            'double precision' => 'float',
            'real'             => 'float',
            'decimal'          => 'decimal',
            'numeric'          => 'decimal',
            'blob'             => 'blob',
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\SQLiteKeywords';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff)
    {
        if ( ! $diff->fromTable instanceof Table) {
            throw new DBALException('Sqlite platform requires for alter table the table diff with reference to original table schema');
        }

        $sql = array();
        foreach ($diff->fromTable->getIndexes() as $index) {
            if ( ! $index->isPrimary()) {
                $sql[] = $this->getDropIndexSQL($index, $diff->name);
            }
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff)
    {
        if ( ! $diff->fromTable instanceof Table) {
            throw new DBALException('Sqlite platform requires for alter table the table diff with reference to original table schema');
        }

        $sql = array();
        $indexes = $diff->fromTable->getIndexes();

        foreach ($diff->removedIndexes as $index) {
            if (isset($indexes[$index->getName()])) {
                unset($indexes[$index->getName()]);
            }
        }

        foreach (array_merge($diff->changedIndexes, $diff->addedIndexes) as $index) {
            $name = $index->getName();
            $indexes[$name] = $index;
        }

        $tableName = $diff->newName ?: $diff->name;
        foreach ($indexes as $indexName => $index) {
            if ($index->isPrimary()) {
                continue;
            }

            $sql[] = $this->getCreateIndexSQL($index, $tableName);
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        return 'BLOB';
    }

    /**
     * {@inheritDoc}
     */
    public function getTemporaryTableName($tableName)
    {
        $tableName = str_replace('.', '__', $tableName);

        return $tableName;
    }

    /**
     * {@inheritDoc}
     *
     * Sqlite Platform emulates schema by underscoring each dot and generating tables
     * into the default database.
     *
     * This hack is implemented to be able to use SQLite as testdriver when
     * using schema supporting databases.
     */
    public function canEmulateSchemas()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreatePrimaryKeySQL(Index $index, $table)
    {
        throw new DBALException('Sqlite platform does not support alter primary key.');
    }

    /**
     * {@inheritdoc}
     */
    public function getCreateForeignKeySQL(ForeignKeyConstraint $foreignKey, $table)
    {
        throw new DBALException('Sqlite platform does not support alter foreign key.');
    }

    /**
     * {@inheritdoc}
     */
    public function getDropForeignKeySQL($foreignKey, $table)
    {
        throw new DBALException('Sqlite platform does not support alter foreign key.');
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableForeignKeysSQL($table, $database = null)
    {
        $table = str_replace('.', '__', $table);

        return "PRAGMA foreign_key_list($table)";
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $fromTable = $diff->fromTable;
        if ( ! $fromTable instanceof Table) {
            throw new DBALException('Sqlite platform requires for alter table the table diff with reference to original table schema');
        }

        $table = clone $fromTable;

        $columns = $table->getColumns();

        $columnSql = array();
        foreach ($diff->removedColumns as $columnName => $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            unset($columns[$columnName]);
        }

        $fromColumns = array();
        $toColumns = array();
        foreach ($columns as $columnName => $column) {
            $fromColumns[$columnName] = $toColumns[$columnName] = $column->getQuotedName($this);
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            unset($columns[$oldColumnName]);
            $columns[$column->getName()] = $column;
            $toColumns[$oldColumnName] = $column->getQuotedName($this);
        }

        foreach ($diff->changedColumns as $oldColumnName => $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            unset($columns[$oldColumnName]);
            $columnName = $columnDiff->column->getName();
            $columns[$columnName] = $columnDiff->column;
            $toColumns[$oldColumnName] = $columnDiff->column->getQuotedName($this);
        }

        foreach ($diff->addedColumns as $columnName => $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $columns[$columnName] = $column;
        }

        $foreignKeys = $table->getForeignKeys();

        foreach ($diff->removedForeignKeys as $constraint) {
            $constraintName = strtolower($constraint->getName());
            if (isset($foreignKeys[$constraintName])) {
                unset($foreignKeys[$constraintName]);
            }
        }

        foreach ($diff->changedForeignKeys as $constraint) {
            $constraintName = strtolower($constraint->getName());
            $foreignKeys[$constraintName] = $constraint;
        }

        foreach ($diff->addedForeignKeys as $constraint) {
            $foreignKeys[] = $constraint;
        }

        $sql = array();
        $tableSql = array();
        if ( ! $this->onSchemaAlterTable($diff, $tableSql)) {
            $newTableName = $diff->newName ?: $diff->name;

            $tempTable = new Table('__temp__'.$newTableName, $columns, $this->getPrimaryIndex($diff), $foreignKeys, 0, $table->getOptions());
            $tempTable->addOption('alter', true);
            $newTable = new Table($newTableName);

            $sql = array_merge($this->getPreAlterTableIndexForeignKeySQL($diff), $this->getCreateTableSQL($tempTable, self::CREATE_INDEXES | self::CREATE_FOREIGNKEYS));
            $sql[] = sprintf('INSERT INTO %s (%s) SELECT %s FROM %s', $tempTable->getQuotedName($this), implode(', ', $toColumns), implode(', ', $fromColumns), $table->getQuotedName($this));
            $sql[] = $this->getDropTableSQL($fromTable);
            $sql[] = 'ALTER TABLE '.$tempTable->getQuotedName($this).' RENAME TO '.$newTable->getQuotedName($this);
            $sql = array_merge($sql, $this->getPostAlterTableIndexForeignKeySQL($diff));
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    private function getPrimaryIndex(TableDiff $diff)
    {
        $primaryIndex = array();

        foreach ($diff->fromTable->getIndexes() as $index) {
            if ($index->isPrimary()) {
                $primaryIndex = array($index->getName() => $index);
            }
        }

        foreach ($diff->removedIndexes as $index) {
            if (isset($primaryIndex[$index->getName()])) {
                $primaryIndex = array();
                break;
            }
        }

        foreach ($diff->changedIndexes as $index) {
            if ($index->isPrimary()) {
                $primaryIndex = array($index);
            } elseif (isset($primaryIndex[$index->getName()])) {
                $primaryIndex = array();
            }
        }

        foreach ($diff->addedIndexes as $index) {
            if ($index->isPrimary()) {
                $primaryIndex = array($index);
            }
        }

        return $primaryIndex;
    }
}
