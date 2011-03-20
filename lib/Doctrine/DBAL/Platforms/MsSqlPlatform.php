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
use Doctrine\DBAL\Schema\Index, Doctrine\DBAL\Schema\Table;

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
     * Whether the platform supports releasing savepoints.
     *
     * @return boolean
     */
    public function supportsReleaseSavepoints()
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
	public function supportsCreateDropDatabase()
    {
        return false;
    }

    /**
     * @override
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

    /**
     * @override
     */
    public function getDropIndexSQL($index, $table=null)
    {
        if ($index instanceof \Doctrine\DBAL\Schema\Index) {
            $index_ = $index;
            $index = $index->getQuotedName($this);
        } else if (!is_string($index)) {
            throw new \InvalidArgumentException('AbstractPlatform::getDropIndexSQL() expects $index parameter to be string or \Doctrine\DBAL\Schema\Index.');
        }

        if (!isset($table)) {
            return 'DROP INDEX ' . $index;
        } else {
            if ($table instanceof \Doctrine\DBAL\Schema\Table) {
                $table = $table->getQuotedName($this);
            }

            return "IF EXISTS (SELECT * FROM sysobjects WHERE name = '$index')
						ALTER TABLE " . $table . " DROP CONSTRAINT " . $index . "
					ELSE
						DROP INDEX " . $index . " ON " . $table;
        }
    }

    /**
     * @override
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = array())
    {
		// @todo does other code breaks because of this?
		// foce primary keys to be not null
		foreach ($columns as &$column) {
			if (isset($column['primary']) && $column['primary']) {
				$column['notnull'] = true;
			}
		}
	
        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && !empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $name => $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if (isset($options['primary']) && !empty($options['primary'])) {
            $columnListSql .= ', PRIMARY KEY(' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }

        $query = 'CREATE TABLE ' . $tableName . ' (' . $columnListSql;

        $check = $this->getCheckDeclarationSQL($columns);
        if (!empty($check)) {
            $query .= ', ' . $check;
        }
        $query .= ')';

        $sql[] = $query;

        if (isset($options['indexes']) && !empty($options['indexes'])) {
            foreach ($options['indexes'] AS $index) {
                $sql[] = $this->getCreateIndexSQL($index, $tableName);
            }
        }

        if (isset($options['foreignKeys'])) {
            foreach ((array) $options['foreignKeys'] AS $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $tableName);
            }
        }

        return $sql;
    }
	
	/**
     * @override
     */
	public function getUniqueConstraintDeclarationSQL($name, Index $index)
    {
        $constraint = parent::getUniqueConstraintDeclarationSQL($name, $index);
		
		$constraint = $this->_appendUniqueConstraintDefinition($constraint, $index);
		
		return $constraint;
    }
	
	/**
     * @override
     */
	public function getCreateIndexSQL(Index $index, $table)
    {	
		$constraint = parent::getCreateIndexSQL($index, $table);
		
		if ($index->isUnique()) {
			$constraint = $this->_appendUniqueConstraintDefinition($constraint, $index);
		}
		
		return $constraint;
	}
	
	/**
     * Extend unique key constraint with required filters
	 *
	 * @param string $sql
	 * @param Index $index
	 * @return string
     */
	private function _appendUniqueConstraintDefinition($sql, Index $index)
	{
		$fields = array();
        foreach ($index->getColumns() as $field => $definition) {
            if (!is_array($definition)) {
                $field = $definition;
            }
			
			$fields[] = $field . ' IS NOT NULL';
        }
	
		return $sql . ' WHERE ' . implode(' AND ', $fields);
	}

    /**
     * @override
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $queryParts = array();
        if ($diff->newName !== false) {
            $queryParts[] = 'RENAME TO ' . $diff->newName;
        }

        foreach ($diff->addedColumns AS $fieldName => $column) {
            $queryParts[] = 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
        }

        foreach ($diff->removedColumns AS $column) {
            $queryParts[] = 'DROP COLUMN ' . $column->getQuotedName($this);
        }

        foreach ($diff->changedColumns AS $columnDiff) {
            /* @var $columnDiff Doctrine\DBAL\Schema\ColumnDiff */
            $column = $columnDiff->column;
            $queryParts[] = 'CHANGE ' . ($columnDiff->oldColumnName) . ' '
                    . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
        }

        foreach ($diff->renamedColumns AS $oldColumnName => $column) {
            $queryParts[] = 'CHANGE ' . $oldColumnName . ' '
                    . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
        }

        $sql = array();

        foreach ($queryParts as $query) {
            $sql[] = 'ALTER TABLE ' . $diff->name . ' ' . $query;
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
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        return "exec sp_helpindex '" . $table . "'";
    }

    /**
     * @override
     */
    public function getCreateViewSQL($name, $sql)
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    /**
     * @override
     */
    public function getListViewsSQL($database)
    {
        return "SELECT name FROM sysobjects WHERE type = 'V' ORDER BY name";
    }

    /**
     * @override
     */
    public function getDropViewSQL($name)
    {
        return 'DROP VIEW ' . $name;
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
            return 'CHARINDEX(' . $substr . ', ' . $str . ', ' . $startPos . ')';
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
        $trimFn = '';

		if (!$char) {
			if ($pos == self::TRIM_LEADING) {
				$trimFn = 'LTRIM';
			} else if ($pos == self::TRIM_TRAILING) {
				$trimFn = 'RTRIM';
			} else {
				return 'LTRIM(RTRIM(' . $str . '))';
			}

			return $trimFn . '(' . $str . ')';
		} else {
			/** Original query used to get those expressions
				declare @c varchar(100) = 'xxxBarxxx', @trim_char char(1) = 'x';
				declare @pat varchar(10) = '%[^' + @trim_char + ']%';
				select @c as string
					 , @trim_char as trim_char
					 , stuff(@c, 1, patindex(@pat, @c) - 1, null) as trim_leading
					 , reverse(stuff(reverse(@c), 1, patindex(@pat, reverse(@c)) - 1, null)) as trim_trailing
					 , reverse(stuff(reverse(stuff(@c, 1, patindex(@pat, @c) - 1, null)), 1, patindex(@pat, reverse(stuff(@c, 1, patindex(@pat, @c) - 1, null))) - 1, null)) as trim_both;
			 */
			$pattern = "'%[^' + $char + ']%'";
			
			if ($pos == self::TRIM_LEADING) {
				return 'stuff(' . $str . ', 1, patindex(' . $pattern .', ' . $str . ') - 1, null)';
			} else if ($pos == self::TRIM_TRAILING) {
				return 'reverse(stuff(reverse(' . $str . '), 1, patindex(' . $pattern .', reverse(' . $str . ')) - 1, null))';
			} else {
				return 'reverse(stuff(reverse(stuff(' . $str . ', 1, patindex(' . $pattern .', ' . $str . ') - 1, null)), 1, patindex(' . $pattern .', reverse(stuff(' . $str . ', 1, patindex(' . $pattern .', ' . $str . ') - 1, null))) - 1, null))';
			}
		}
    }

    /**
     * @override
     */
    public function getConcatExpression()
    {
        $args = func_get_args();
        return '(' . implode(' + ', $args) . ')';
    }

    public function getListDatabasesSQL()
    {
        return 'SELECT * FROM SYS.DATABASES';
    }

    /**
     * @override
     */
    public function getSubstringExpression($value, $from, $len = null)
    {
        if (!is_null($len)) {
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
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? ($length ? 'NCHAR(' . $length . ')' : 'CHAR(255)') : ($length ? 'NVARCHAR(' . $length . ')' : 'NVARCHAR(255)');
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
        if (!empty($columnDef['autoincrement'])) {
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
        return 'TIME(0)';
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
    protected function doModifyLimitQuery($query, $limit, $offset = null)
    {
        if ($limit > 0) {
            $count = intval($limit);
            $offset = intval($offset);

            if ($offset < 0) {
                throw new DBALException("LIMIT argument offset=$offset is not valid");
            }

            if ($offset == 0) {
                $query = preg_replace('/^SELECT\s/i', 'SELECT TOP ' . $count . ' ', $query);
            } else {
                $orderby = stristr($query, 'ORDER BY');

                if (!$orderby) {
                    $over = 'ORDER BY (SELECT 0)';
                } else {
                    $over = preg_replace('/\"[^,]*\".\"([^,]*)\"/i', '"inner_tbl"."$1"', $orderby);
                }

                // Remove ORDER BY clause from $query
                $query = preg_replace('/\s+ORDER BY(.*)/', '', $query);

                // Add ORDER BY clause as an argument for ROW_NUMBER()
                $query = "SELECT ROW_NUMBER() OVER ($over) AS \"doctrine_rownum\", * FROM ($query) AS inner_tbl";

                $start = $offset + 1;
                $end = $offset + $count;

                $query = "WITH outer_tbl AS ($query) SELECT * FROM outer_tbl WHERE \"doctrine_rownum\" BETWEEN $start AND $end";
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
    public function getDateTimeTzFormatString()
    {
        return $this->getDateTimeFormatString();
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

    /**
     * @override
     */
    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = array(
            'bigint'            => 'bigint',
            'numeric'           => 'decimal',
            'bit'               => 'boolean',
            'smallint'          => 'smallint',
            'decimal'           => 'decimal',
            'smallmoney'        => 'integer',
            'int'               => 'integer',
            'tinyint'           => 'smallint',
            'money'             => 'integer',
            'float'             => 'float',
            'real'              => 'float',
            'double'            => 'float',
            'double precision'  => 'float',
            'date'              => 'date',
            'datetimeoffset'    => 'datetimetz',
            'datetime2'         => 'datetime',
            'smalldatetime'     => 'datetime',
            'datetime'          => 'datetime',
            'time'              => 'time',
            'char'              => 'string',
            'varchar'           => 'string',
            'text'              => 'text',
            'nchar'             => 'string',
            'nvarchar'          => 'string',
            'ntext'             => 'text',
            'binary'            => 'text',
            'varbinary'         => 'text',
            'image'             => 'text',
        );
    }

    /**
     * Generate SQL to create a new savepoint
     *
     * @param string $savepoint
     * @return string
     */
    public function createSavePoint($savepoint)
    {
        return 'SAVE TRANSACTION ' . $savepoint;
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

    /**
     * Generate SQL to rollback a savepoint
     *
     * @param string $savepoint
     * @return string
     */
    public function rollbackSavePoint($savepoint)
    {
        return 'ROLLBACK TRANSACTION ' . $savepoint;
    }
	
	/**
     * @override
     */
	public function appendLockHint($fromClause, $lockMode)
    {
		// @todo coorect
		if ($lockMode == \Doctrine\DBAL\LockMode::PESSIMISTIC_READ) {
            return $fromClause . ' WITH (tablockx)';
        } else if ($lockMode == \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE) {
            return $fromClause . ' WITH (tablockx)';
        }
		else {
			return $fromClause;
		}
    }

    /**
     * @override
     */
    public function getForUpdateSQL()
    {
        return ' ';
    }
}
