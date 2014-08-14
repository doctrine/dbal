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
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;

/**
 * The SQLServerPlatform provides the behavior, features and SQL dialect of the
 * Microsoft SQL Server database platform.
 *
 * @since 2.0
 * @author Roman Borschel <roman@code-factory.org>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Steve Müller <st.mueller@dzh-online.de>
 */
class SQLServerPlatform extends AbstractPlatform
{
    /**
     * {@inheritdoc}
     */
    protected function getDateArithmeticIntervalExpression($date, $operator, $interval, $unit)
    {
        $factorClause = '';

        if ('-' === $operator) {
            $factorClause = '-1 * ';
        }

        return 'DATEADD(' . $unit . ', ' . $factorClause . $interval . ', ' . $date . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateDiffExpression($date1, $date2)
    {
        return 'DATEDIFF(day, ' . $date2 . ',' . $date1 . ')';
    }

    /**
     * {@inheritDoc}
     *
     * Microsoft SQL Server prefers "autoincrement" identity columns
     * since sequences can only be emulated with a table.
     */
    public function prefersIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * Microsoft SQL Server supports this through AUTO_INCREMENT columns.
     */
    public function supportsIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsReleaseSavepoints()
    {
        return false;
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
    public function getDefaultSchemaName()
    {
        return 'dbo';
    }

    /**
     * {@inheritDoc}
     */
    public function supportsColumnCollation()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function hasNativeGuidType()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateDatabaseSQL($name)
    {
        return 'CREATE DATABASE ' . $name;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropDatabaseSQL($name)
    {
        return 'DROP DATABASE ' . $name;
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
    public function getCreateSchemaSQL($schemaName)
    {
        return 'CREATE SCHEMA ' . $schemaName;
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
    public function getDropIndexSQL($index, $table = null)
    {
        if ($index instanceof Index) {
            $index = $index->getQuotedName($this);
        } elseif (!is_string($index)) {
            throw new \InvalidArgumentException('AbstractPlatform::getDropIndexSQL() expects $index parameter to be string or \Doctrine\DBAL\Schema\Index.');
        }

        if (!isset($table)) {
            return 'DROP INDEX ' . $index;
        }

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        return "IF EXISTS (SELECT * FROM sysobjects WHERE name = '$index')
                    ALTER TABLE " . $table . " DROP CONSTRAINT " . $index . "
                ELSE
                    DROP INDEX " . $index . " ON " . $table;
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = array())
    {
        $defaultConstraintsSql = array();
        $commentsSql           = array();

        // @todo does other code breaks because of this?
        // force primary keys to be not null
        foreach ($columns as &$column) {
            if (isset($column['primary']) && $column['primary']) {
                $column['notnull'] = true;
            }

            // Build default constraints SQL statements.
            if (isset($column['default'])) {
                $defaultConstraintsSql[] = 'ALTER TABLE ' . $tableName .
                    ' ADD' . $this->getDefaultConstraintDeclarationSQL($tableName, $column);
            }

            if ( ! empty($column['comment']) || is_numeric($column['comment'])) {
                $commentsSql[] = $this->getCreateColumnCommentSQL($tableName, $column['name'], $column['comment']);
            }
        }

        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && !empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $name => $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if (isset($options['primary']) && !empty($options['primary'])) {
            $flags = '';
            if (isset($options['primary_index']) && $options['primary_index']->hasFlag('nonclustered')) {
                $flags = ' NONCLUSTERED';
            }
            $columnListSql .= ', PRIMARY KEY' . $flags . ' (' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }

        $query = 'CREATE TABLE ' . $tableName . ' (' . $columnListSql;

        $check = $this->getCheckDeclarationSQL($columns);
        if (!empty($check)) {
            $query .= ', ' . $check;
        }
        $query .= ')';

        $sql[] = $query;

        if (isset($options['indexes']) && !empty($options['indexes'])) {
            foreach ($options['indexes'] as $index) {
                $sql[] = $this->getCreateIndexSQL($index, $tableName);
            }
        }

        if (isset($options['foreignKeys'])) {
            foreach ((array) $options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $tableName);
            }
        }

        return array_merge($sql, $commentsSql, $defaultConstraintsSql);
    }

    /**
     * {@inheritDoc}
     */
    public function getCreatePrimaryKeySQL(Index $index, $table)
    {
        $flags = '';
        if ($index->hasFlag('nonclustered')) {
            $flags = ' NONCLUSTERED';
        }
        return 'ALTER TABLE ' . $table . ' ADD PRIMARY KEY' . $flags . ' (' . $this->getIndexFieldDeclarationListSQL($index->getQuotedColumns($this)) . ')';
    }

    /**
     * Returns the SQL statement for creating a column comment.
     *
     * SQL Server does not support native column comments,
     * therefore the extended properties functionality is used
     * as a workaround to store them.
     * The property name used to store column comments is "MS_Description"
     * which provides compatibility with SQL Server Management Studio,
     * as column comments are stored in the same property there when
     * specifying a column's "Description" attribute.
     *
     * @param string $tableName  The quoted table name to which the column belongs.
     * @param string $columnName The quoted column name to create the comment for.
     * @param string $comment    The column's comment.
     *
     * @return string
     */
    protected function getCreateColumnCommentSQL($tableName, $columnName, $comment)
    {
        return $this->getAddExtendedPropertySQL(
            'MS_Description',
            $comment,
            'SCHEMA',
            'dbo',
            'TABLE',
            $tableName,
            'COLUMN',
            $columnName
        );
    }

    /**
     * Returns the SQL snippet for declaring a default constraint.
     *
     * @param string $table  Name of the table to return the default constraint declaration for.
     * @param array  $column Column definition.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getDefaultConstraintDeclarationSQL($table, array $column)
    {
        if ( ! isset($column['default'])) {
            throw new \InvalidArgumentException("Incomplete column definition. 'default' required.");
        }

        $columnName = new Identifier($column['name']);

        return
            ' CONSTRAINT ' .
            $this->generateDefaultConstraintName($table, $column['name']) .
            $this->getDefaultValueDeclarationSQL($column) .
            ' FOR ' . $columnName->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    public function getUniqueConstraintDeclarationSQL($name, Index $index)
    {
        $constraint = parent::getUniqueConstraintDeclarationSQL($name, $index);

        $constraint = $this->_appendUniqueConstraintDefinition($constraint, $index);

        return $constraint;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateIndexSQL(Index $index, $table)
    {
        $constraint = parent::getCreateIndexSQL($index, $table);

        if ($index->isUnique() && !$index->isPrimary()) {
            $constraint = $this->_appendUniqueConstraintDefinition($constraint, $index);
        }

        return $constraint;
    }

    /**
     * {@inheritDoc}
     */
    protected function getCreateIndexSQLFlags(Index $index)
    {
        $type = '';
        if ($index->isUnique()) {
            $type .= 'UNIQUE ';
        }

        if ($index->hasFlag('clustered')) {
            $type .= 'CLUSTERED ';
        } elseif ($index->hasFlag('nonclustered')) {
            $type .= 'NONCLUSTERED ';
        }

        return $type;
    }

    /**
     * Extend unique key constraint with required filters
     *
     * @param string                      $sql
     * @param \Doctrine\DBAL\Schema\Index $index
     *
     * @return string
     */
    private function _appendUniqueConstraintDefinition($sql, Index $index)
    {
        $fields = array();

        foreach ($index->getQuotedColumns($this) as $field) {
            $fields[] = $field . ' IS NOT NULL';
        }

        return $sql . ' WHERE ' . implode(' AND ', $fields);
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $queryParts  = array();
        $sql         = array();
        $columnSql   = array();
        $commentsSql = array();

        /** @var \Doctrine\DBAL\Schema\Column $column */
        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $columnDef = $column->toArray();
            $queryParts[] = 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnDef);

            if (isset($columnDef['default'])) {
                $queryParts[] = $this->getAlterTableAddDefaultConstraintClause($diff->name, $column);
            }

            $comment = $this->getColumnComment($column);

            if ( ! empty($comment) || is_numeric($comment)) {
                $commentsSql[] = $this->getCreateColumnCommentSQL(
                    $diff->name,
                    $column->getQuotedName($this),
                    $comment
                );
            }
        }

        foreach ($diff->removedColumns as $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $queryParts[] = 'DROP COLUMN ' . $column->getQuotedName($this);
        }

        /* @var $columnDiff \Doctrine\DBAL\Schema\ColumnDiff */
        foreach ($diff->changedColumns as $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            $column     = $columnDiff->column;
            $comment    = $this->getColumnComment($column);
            $hasComment = ! empty ($comment) || is_numeric($comment);

            if ($columnDiff->fromColumn instanceof Column) {
                $fromComment    = $this->getColumnComment($columnDiff->fromColumn);
                $hasFromComment = ! empty ($fromComment) || is_numeric($fromComment);

                if ($hasFromComment && $hasComment && $fromComment != $comment) {
                    $commentsSql[] = $this->getAlterColumnCommentSQL(
                        $diff->name,
                        $column->getQuotedName($this),
                        $comment
                    );
                } elseif ($hasFromComment && ! $hasComment) {
                    $commentsSql[] = $this->getDropColumnCommentSQL($diff->name, $column->getQuotedName($this));
                } elseif ($hasComment) {
                    $commentsSql[] = $this->getCreateColumnCommentSQL(
                        $diff->name,
                        $column->getQuotedName($this),
                        $comment
                    );
                }
            } else {
                // todo: Original comment cannot be determined. What to do? Add, update, drop or skip?
            }

            // Do not add query part if only comment has changed.
            if ($columnDiff->hasChanged('comment') && count($columnDiff->changedProperties) === 1) {
                continue;
            }

            $requireDropDefaultConstraint = $this->alterColumnRequiresDropDefaultConstraint($columnDiff);

            if ($requireDropDefaultConstraint) {
                $queryParts[] = $this->getAlterTableDropDefaultConstraintClause(
                    $diff->name,
                    $columnDiff->oldColumnName
                );
            }

            $columnDef = $column->toArray();

            $queryParts[] = 'ALTER COLUMN ' .
                $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnDef);

            if (isset($columnDef['default']) && ($requireDropDefaultConstraint || $columnDiff->hasChanged('default'))) {
                $queryParts[] = $this->getAlterTableAddDefaultConstraintClause($diff->name, $column);
            }
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = new Identifier($oldColumnName);

            $sql[] = "sp_RENAME '" .
                $diff->getName($this)->getQuotedName($this) . "." . $oldColumnName->getQuotedName($this) .
                "', '" . $column->getQuotedName($this) . "', 'COLUMN'";

            // Recreate default constraint with new column name if necessary (for future reference).
            if ($column->getDefault() !== null) {
                $queryParts[] = $this->getAlterTableDropDefaultConstraintClause(
                    $diff->name,
                    $oldColumnName->getQuotedName($this)
                );
                $queryParts[] = $this->getAlterTableAddDefaultConstraintClause($diff->name, $column);
            }
        }

        $tableSql = array();

        if ($this->onSchemaAlterTable($diff, $tableSql)) {
            return array_merge($tableSql, $columnSql);
        }

        foreach ($queryParts as $query) {
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
        }

        $sql = array_merge($sql, $commentsSql);

        if ($diff->newName !== false) {
            $sql[] = "sp_RENAME '" . $diff->getName($this)->getQuotedName($this) . "', '" . $diff->getNewName()->getName() . "'";

            /**
             * Rename table's default constraints names
             * to match the new table name.
             * This is necessary to ensure that the default
             * constraints can be referenced in future table
             * alterations as the table name is encoded in
             * default constraints' names.
             */
            $sql[] = "DECLARE @sql NVARCHAR(MAX) = N''; " .
                "SELECT @sql += N'EXEC sp_rename N''' + dc.name + ''', N''' " .
                "+ REPLACE(dc.name, '" . $this->generateIdentifierName($diff->name) . "', " .
                "'" . $this->generateIdentifierName($diff->newName) . "') + ''', ''OBJECT'';' " .
                "FROM sys.default_constraints dc " .
                "JOIN sys.tables tbl ON dc.parent_object_id = tbl.object_id " .
                "WHERE tbl.name = '" . $diff->getNewName()->getName() . "';" .
                "EXEC sp_executesql @sql";
        }

        $sql = array_merge(
            $this->getPreAlterTableIndexForeignKeySQL($diff),
            $sql,
            $this->getPostAlterTableIndexForeignKeySQL($diff)
        );

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * Returns the SQL clause for adding a default constraint in an ALTER TABLE statement.
     *
     * @param  string $tableName The name of the table to generate the clause for.
     * @param  Column $column    The column to generate the clause for.
     *
     * @return string
     */
    private function getAlterTableAddDefaultConstraintClause($tableName, Column $column)
    {
        $columnDef = $column->toArray();
        $columnDef['name'] = $column->getQuotedName($this);

        return 'ADD' . $this->getDefaultConstraintDeclarationSQL($tableName, $columnDef);
    }

    /**
     * Returns the SQL clause for dropping an existing default constraint in an ALTER TABLE statement.
     *
     * @param  string $tableName  The name of the table to generate the clause for.
     * @param  string $columnName The name of the column to generate the clause for.
     *
     * @return string
     */
    private function getAlterTableDropDefaultConstraintClause($tableName, $columnName)
    {
        return 'DROP CONSTRAINT ' . $this->generateDefaultConstraintName($tableName, $columnName);
    }

    /**
     * Checks whether a column alteration requires dropping its default constraint first.
     *
     * Different to other database vendors SQL Server implements column default values
     * as constraints and therefore changes in a column's default value as well as changes
     * in a column's type require dropping the default constraint first before being to
     * alter the particular column to the new definition.
     *
     * @param  ColumnDiff $columnDiff The column diff to evaluate.
     *
     * @return boolean True if the column alteration requires dropping its default constraint first, false otherwise.
     */
    private function alterColumnRequiresDropDefaultConstraint(ColumnDiff $columnDiff)
    {
        // We can only decide whether to drop an existing default constraint
        // if we know the original default value.
        if ( ! $columnDiff->fromColumn instanceof Column) {
            return false;
        }

        // We only need to drop an existing default constraint if we know the
        // column was defined with a default value before.
        if ($columnDiff->fromColumn->getDefault() === null) {
            return false;
        }

        // We need to drop an existing default constraint if the column was
        // defined with a default value before and it has changed.
        if ($columnDiff->hasChanged('default')) {
            return true;
        }

        // We need to drop an existing default constraint if the column was
        // defined with a default value before and the native column type has changed.
        if ($columnDiff->hasChanged('type') || $columnDiff->hasChanged('fixed')) {
            return true;
        }

        return false;
    }

    /**
     * Returns the SQL statement for altering a column comment.
     *
     * SQL Server does not support native column comments,
     * therefore the extended properties functionality is used
     * as a workaround to store them.
     * The property name used to store column comments is "MS_Description"
     * which provides compatibility with SQL Server Management Studio,
     * as column comments are stored in the same property there when
     * specifying a column's "Description" attribute.
     *
     * @param string $tableName  The quoted table name to which the column belongs.
     * @param string $columnName The quoted column name to alter the comment for.
     * @param string $comment    The column's comment.
     *
     * @return string
     */
    protected function getAlterColumnCommentSQL($tableName, $columnName, $comment)
    {
        return $this->getUpdateExtendedPropertySQL(
            'MS_Description',
            $comment,
            'SCHEMA',
            'dbo',
            'TABLE',
            $tableName,
            'COLUMN',
            $columnName
        );
    }

    /**
     * Returns the SQL statement for dropping a column comment.
     *
     * SQL Server does not support native column comments,
     * therefore the extended properties functionality is used
     * as a workaround to store them.
     * The property name used to store column comments is "MS_Description"
     * which provides compatibility with SQL Server Management Studio,
     * as column comments are stored in the same property there when
     * specifying a column's "Description" attribute.
     *
     * @param string $tableName  The quoted table name to which the column belongs.
     * @param string $columnName The quoted column name to drop the comment for.
     *
     * @return string
     */
    protected function getDropColumnCommentSQL($tableName, $columnName)
    {
        return $this->getDropExtendedPropertySQL(
            'MS_Description',
            'SCHEMA',
            'dbo',
            'TABLE',
            $tableName,
            'COLUMN',
            $columnName
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameIndexSQL($oldIndexName, Index $index, $tableName)
    {
        return array(
            sprintf(
                "EXEC sp_RENAME N'%s.%s', N'%s', N'INDEX'",
                $tableName,
                $oldIndexName,
                $index->getQuotedName($this)
            )
        );
    }

    /**
     * Returns the SQL statement for adding an extended property to a database object.
     *
     * @param string      $name       The name of the property to add.
     * @param string|null $value      The value of the property to add.
     * @param string|null $level0Type The type of the object at level 0 the property belongs to.
     * @param string|null $level0Name The name of the object at level 0 the property belongs to.
     * @param string|null $level1Type The type of the object at level 1 the property belongs to.
     * @param string|null $level1Name The name of the object at level 1 the property belongs to.
     * @param string|null $level2Type The type of the object at level 2 the property belongs to.
     * @param string|null $level2Name The name of the object at level 2 the property belongs to.
     *
     * @return string
     *
     * @link http://msdn.microsoft.com/en-us/library/ms180047%28v=sql.90%29.aspx
     */
    public function getAddExtendedPropertySQL(
        $name,
        $value = null,
        $level0Type = null,
        $level0Name = null,
        $level1Type = null,
        $level1Name = null,
        $level2Type = null,
        $level2Name = null
    ) {
        return "EXEC sp_addextendedproperty " .
            "N" . $this->quoteStringLiteral($name) . ", N" . $this->quoteStringLiteral($value) . ", " .
            "N" . $this->quoteStringLiteral($level0Type) . ", " . $level0Name . ', ' .
            "N" . $this->quoteStringLiteral($level1Type) . ", " . $level1Name . ', ' .
            "N" . $this->quoteStringLiteral($level2Type) . ", " . $level2Name;
    }

    /**
     * Returns the SQL statement for dropping an extended property from a database object.
     *
     * @param string      $name       The name of the property to drop.
     * @param string|null $level0Type The type of the object at level 0 the property belongs to.
     * @param string|null $level0Name The name of the object at level 0 the property belongs to.
     * @param string|null $level1Type The type of the object at level 1 the property belongs to.
     * @param string|null $level1Name The name of the object at level 1 the property belongs to.
     * @param string|null $level2Type The type of the object at level 2 the property belongs to.
     * @param string|null $level2Name The name of the object at level 2 the property belongs to.
     *
     * @return string
     *
     * @link http://technet.microsoft.com/en-gb/library/ms178595%28v=sql.90%29.aspx
     */
    public function getDropExtendedPropertySQL(
        $name,
        $level0Type = null,
        $level0Name = null,
        $level1Type = null,
        $level1Name = null,
        $level2Type = null,
        $level2Name = null
    ) {
        return "EXEC sp_dropextendedproperty " .
            "N" . $this->quoteStringLiteral($name) . ", " .
            "N" . $this->quoteStringLiteral($level0Type) . ", " . $level0Name . ', ' .
            "N" . $this->quoteStringLiteral($level1Type) . ", " . $level1Name . ', ' .
            "N" . $this->quoteStringLiteral($level2Type) . ", " . $level2Name;
    }

    /**
     * Returns the SQL statement for updating an extended property of a database object.
     *
     * @param string      $name       The name of the property to update.
     * @param string|null $value      The value of the property to update.
     * @param string|null $level0Type The type of the object at level 0 the property belongs to.
     * @param string|null $level0Name The name of the object at level 0 the property belongs to.
     * @param string|null $level1Type The type of the object at level 1 the property belongs to.
     * @param string|null $level1Name The name of the object at level 1 the property belongs to.
     * @param string|null $level2Type The type of the object at level 2 the property belongs to.
     * @param string|null $level2Name The name of the object at level 2 the property belongs to.
     *
     * @return string
     *
     * @link http://msdn.microsoft.com/en-us/library/ms186885%28v=sql.90%29.aspx
     */
    public function getUpdateExtendedPropertySQL(
        $name,
        $value = null,
        $level0Type = null,
        $level0Name = null,
        $level1Type = null,
        $level1Name = null,
        $level2Type = null,
        $level2Name = null
    ) {
        return "EXEC sp_updateextendedproperty " .
        "N" . $this->quoteStringLiteral($name) . ", N" . $this->quoteStringLiteral($value) . ", " .
        "N" . $this->quoteStringLiteral($level0Type) . ", " . $level0Name . ', ' .
        "N" . $this->quoteStringLiteral($level1Type) . ", " . $level1Name . ', ' .
        "N" . $this->quoteStringLiteral($level2Type) . ", " . $level2Name;
    }

    /**
     * {@inheritDoc}
     */
    public function getEmptyIdentityInsertSQL($quotedTableName, $quotedIdentifierColumnName)
    {
        return 'INSERT INTO ' . $quotedTableName . ' DEFAULT VALUES';
    }

    /**
     * {@inheritDoc}
     */
    public function getListTablesSQL()
    {
        // "sysdiagrams" table must be ignored as it's internal SQL Server table for Database Diagrams
        // Category 2 must be ignored as it is "MS SQL Server 'pseudo-system' object[s]" for replication
        return "SELECT name FROM sysobjects WHERE type = 'U' AND name != 'sysdiagrams' AND category != 2 ORDER BY name";
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableColumnsSQL($table, $database = null)
    {
        return "SELECT    col.name,
                          type.name AS type,
                          col.max_length AS length,
                          ~col.is_nullable AS notnull,
                          def.definition AS [default],
                          col.scale,
                          col.precision,
                          col.is_identity AS autoincrement,
                          col.collation_name AS collation,
                          CAST(prop.value AS NVARCHAR(MAX)) AS comment -- CAST avoids driver error for sql_variant type
                FROM      sys.columns AS col
                JOIN      sys.types AS type
                ON        col.user_type_id = type.user_type_id
                JOIN      sys.objects AS obj
                ON        col.object_id = obj.object_id
                JOIN      sys.schemas AS scm
                ON        obj.schema_id = scm.schema_id
                LEFT JOIN sys.default_constraints def
                ON        col.default_object_id = def.object_id
                AND       col.object_id = def.parent_object_id
                LEFT JOIN sys.extended_properties AS prop
                ON        obj.object_id = prop.major_id
                AND       col.column_id = prop.minor_id
                AND       prop.name = 'MS_Description'
                WHERE     obj.type = 'U'
                AND       " . $this->getTableWhereClause($table, 'scm.name', 'obj.name');
    }

    /**
     * {@inheritDoc}
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
                WHERE " .
                $this->getTableWhereClause($table, 'SCHEMA_NAME (f.schema_id)', 'OBJECT_NAME (f.parent_object_id)');
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        return "SELECT idx.name AS key_name,
                       col.name AS column_name,
                       ~idx.is_unique AS non_unique,
                       idx.is_primary_key AS [primary],
                       CASE idx.type
                           WHEN '1' THEN 'clustered'
                           WHEN '2' THEN 'nonclustered'
                           ELSE NULL
                       END AS flags
                FROM sys.tables AS tbl
                JOIN sys.schemas AS scm ON tbl.schema_id = scm.schema_id
                JOIN sys.indexes AS idx ON tbl.object_id = idx.object_id
                JOIN sys.index_columns AS idxcol ON idx.object_id = idxcol.object_id AND idx.index_id = idxcol.index_id
                JOIN sys.columns AS col ON idxcol.object_id = col.object_id AND idxcol.column_id = col.column_id
                WHERE " . $this->getTableWhereClause($table, 'scm.name', 'tbl.name') . "
                ORDER BY idx.index_id ASC, idxcol.index_column_id ASC";
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
    public function getListViewsSQL($database)
    {
        return "SELECT name FROM sysobjects WHERE type = 'V' ORDER BY name";
    }

    /**
     * Returns the where clause to filter schema and table name in a query.
     *
     * @param string $table        The full qualified name of the table.
     * @param string $tableColumn  The name of the column to compare the schema to in the where clause.
     * @param string $schemaColumn The name of the column to compare the table to in the where clause.
     *
     * @return string
     */
    private function getTableWhereClause($table, $schemaColumn, $tableColumn)
    {
        if (strpos($table, ".") !== false) {
            list($schema, $table) = explode(".", $table);
            $schema = "'" . $schema . "'";
        } else {
            $schema = "SCHEMA_NAME()";
        }

        return "({$tableColumn} = '{$table}' AND {$schemaColumn} = {$schema})";
    }

    /**
     * {@inheritDoc}
     */
    public function getDropViewSQL($name)
    {
        return 'DROP VIEW ' . $name;
    }

    /**
     * {@inheritDoc}
     */
    public function getGuidExpression()
    {
        return 'NEWID()';
    }

    /**
     * {@inheritDoc}
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        if ($startPos == false) {
            return 'CHARINDEX(' . $substr . ', ' . $str . ')';
        }

        return 'CHARINDEX(' . $substr . ', ' . $str . ', ' . $startPos . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getModExpression($expression1, $expression2)
    {
        return $expression1 . ' % ' . $expression2;
    }

    /**
     * {@inheritDoc}
     */
    public function getTrimExpression($str, $pos = self::TRIM_UNSPECIFIED, $char = false)
    {
        if ( ! $char) {
            switch ($pos) {
                case self::TRIM_LEADING:
                    $trimFn = 'LTRIM';
                    break;

                case self::TRIM_TRAILING:
                    $trimFn = 'RTRIM';
                    break;

                default:
                    return 'LTRIM(RTRIM(' . $str . '))';
            }

            return $trimFn . '(' . $str . ')';
        }

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
            return 'stuff(' . $str . ', 1, patindex(' . $pattern . ', ' . $str . ') - 1, null)';
        }

        if ($pos == self::TRIM_TRAILING) {
            return 'reverse(stuff(reverse(' . $str . '), 1, patindex(' . $pattern . ', reverse(' . $str . ')) - 1, null))';
        }

        return 'reverse(stuff(reverse(stuff(' . $str . ', 1, patindex(' . $pattern . ', ' . $str . ') - 1, null)), 1, patindex(' . $pattern . ', reverse(stuff(' . $str . ', 1, patindex(' . $pattern . ', ' . $str . ') - 1, null))) - 1, null))';
    }

    /**
     * {@inheritDoc}
     */
    public function getConcatExpression()
    {
        $args = func_get_args();

        return '(' . implode(' + ', $args) . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getListDatabasesSQL()
    {
        return 'SELECT * FROM SYS.DATABASES';
    }

    /**
     * {@inheritDoc}
     */
    public function getListNamespacesSQL()
    {
        return "SELECT name FROM SYS.SCHEMAS WHERE name NOT IN('guest', 'INFORMATION_SCHEMA', 'sys')";
    }

    /**
     * {@inheritDoc}
     */
    public function getSubstringExpression($value, $from, $length = null)
    {
        if (!is_null($length)) {
            return 'SUBSTRING(' . $value . ', ' . $from . ', ' . $length . ')';
        }

        return 'SUBSTRING(' . $value . ', ' . $from . ', LEN(' . $value . ') - ' . $from . ' + 1)';
    }

    /**
     * {@inheritDoc}
     */
    public function getLengthExpression($column)
    {
        return 'LEN(' . $column . ')';
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
    public function getIntegerTypeDeclarationSQL(array $field)
    {
        return 'INT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return 'SMALLINT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getGuidTypeDeclarationSQL(array $field)
    {
        return 'UNIQUEIDENTIFIER';
    }

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? ($length ? 'NCHAR(' . $length . ')' : 'CHAR(255)') : ($length ? 'NVARCHAR(' . $length . ')' : 'NVARCHAR(255)');
    }

    /**
     * {@inheritdoc}
     */
    protected function getBinaryTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? 'BINARY(' . ($length ?: 255) . ')' : 'VARBINARY(' . ($length ?: 255) . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getBinaryMaxLength()
    {
        return 8000;
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
        return 'VARCHAR(MAX)';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        return (!empty($columnDef['autoincrement'])) ? ' IDENTITY' : '';
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
        return 'DATETIME';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATETIME';
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return 'BIT';
    }

    /**
     * {@inheritDoc}
     */
    protected function doModifyLimitQuery($query, $limit, $offset = null)
    {
        if ($limit === null) {
            return $query;
        }

        $start   = $offset + 1;
        $end     = $offset + $limit;

        // Decompose the passed query into parts
        $sqlParts = $this->getSQLParts($query);

        // Get the order by clause from the query parts
        $orderByParts = $this->getOrderByClauseFromSqlPartArray($sqlParts);
        $orderBy = null;

        // Compose the ROW_NUMBER() OVER(ORDER BY ...) AS doctrine_rownum statement so we can limit
        $rowNumberSelectItem = $this->composeRowNumberOverOrderByPart($orderByParts);

        // Strip existing ORDER BY clauses out of the query
        $sqlParts = $this->stripOrderByFromSqlParts($sqlParts);

        // Add the row number statement to the select list of the query
        $sqlParts['selectList'][] = $rowNumberSelectItem;

        // Gentlemen, we can rebuild it. We have the technology. We can make it better than it was.
        // Better, strong, fa.. No, the best we can do is make it not suck so hard.

        // Rebuild the query with the order by clauses stripped and with the new row number select
        $recomposedQuery = $this->rebuildSqlQueryFromParts($sqlParts);

        // Compose the final query to limit the resultset
        $format  = 'SELECT * FROM (%s) AS doctrine_tbl WHERE doctrine_rownum BETWEEN %d AND %d ORDER BY doctrine_rownum';
        $modifiedQuery = sprintf($format, $recomposedQuery, $start, $end);

        return $modifiedQuery;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsLimitOffset()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function convertBooleans($item)
    {
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                if (is_bool($value) || is_numeric($item)) {
                    $item[$key] = ($value) ? 1 : 0;
                }
            }
        } elseif (is_bool($item) || is_numeric($item)) {
            $item = ($item) ? 1 : 0;
        }

        return $item;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTemporaryTableSnippetSQL()
    {
        return "CREATE TABLE";
    }

    /**
     * {@inheritDoc}
     */
    public function getTemporaryTableName($tableName)
    {
        return '#' . $tableName;
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeFormatString()
    {
        return 'Y-m-d H:i:s.000';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateFormatString()
    {
        return 'Y-m-d H:i:s.000';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeFormatString()
    {
        return 'Y-m-d H:i:s.000';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzFormatString()
    {
        return $this->getDateTimeFormatString();
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'mssql';
    }

    /**
     * {@inheritDoc}
     */
    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = array(
            'bigint' => 'bigint',
            'numeric' => 'decimal',
            'bit' => 'boolean',
            'smallint' => 'smallint',
            'decimal' => 'decimal',
            'smallmoney' => 'integer',
            'int' => 'integer',
            'tinyint' => 'smallint',
            'money' => 'integer',
            'float' => 'float',
            'real' => 'float',
            'double' => 'float',
            'double precision' => 'float',
            'smalldatetime' => 'datetime',
            'datetime' => 'datetime',
            'char' => 'string',
            'varchar' => 'string',
            'text' => 'text',
            'nchar' => 'string',
            'nvarchar' => 'string',
            'ntext' => 'text',
            'binary' => 'binary',
            'varbinary' => 'binary',
            'image' => 'blob',
            'uniqueidentifier' => 'guid',
        );
    }

    /**
     * {@inheritDoc}
     */
    public function createSavePoint($savepoint)
    {
        return 'SAVE TRANSACTION ' . $savepoint;
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
    public function rollbackSavePoint($savepoint)
    {
        return 'ROLLBACK TRANSACTION ' . $savepoint;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKeyReferentialActionSQL($action)
    {
        // RESTRICT is not supported, therefore falling back to NO ACTION.
        if (strtoupper($action) === 'RESTRICT') {
            return 'NO ACTION';
        }

        return parent::getForeignKeyReferentialActionSQL($action);
    }

    /**
     * {@inheritDoc}
     */
    public function appendLockHint($fromClause, $lockMode)
    {
        switch (true) {
            case LockMode::NONE === $lockMode:
                return $fromClause . ' WITH (NOLOCK)';

            case LockMode::PESSIMISTIC_READ === $lockMode:
                return $fromClause . ' WITH (HOLDLOCK, ROWLOCK)';

            case LockMode::PESSIMISTIC_WRITE === $lockMode:
                return $fromClause . ' WITH (UPDLOCK, ROWLOCK)';

            default:
                return $fromClause;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getForUpdateSQL()
    {
        return ' ';
    }

    /**
     * {@inheritDoc}
     */
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\SQLServerKeywords';
    }

    /**
     * {@inheritDoc}
     */
    public function quoteSingleIdentifier($str)
    {
        return "[" . str_replace("]", "][", $str) . "]";
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
    public function getBlobTypeDeclarationSQL(array $field)
    {
        return 'VARBINARY(MAX)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultValueDeclarationSQL($field)
    {
        if ( ! isset($field['default'])) {
            return empty($field['notnull']) ? ' NULL' : '';
        }

        if ( ! isset($field['type'])) {
            return " DEFAULT '" . $field['default'] . "'";
        }

        if (in_array((string) $field['type'], array('Integer', 'BigInt', 'SmallInt'))) {
            return " DEFAULT " . $field['default'];
        }

        if (in_array((string) $field['type'], array('DateTime', 'DateTimeTz')) && $field['default'] == $this->getCurrentTimestampSQL()) {
            return " DEFAULT " . $this->getCurrentTimestampSQL();
        }

        if ((string) $field['type'] == 'Boolean') {
            return " DEFAULT '" . $this->convertBooleans($field['default']) . "'";
        }

        return " DEFAULT '" . $field['default'] . "'";
    }

    /**
     * {@inheritdoc}
     *
     * Modifies column declaration order as it differs in Microsoft SQL Server.
     */
    public function getColumnDeclarationSQL($name, array $field)
    {
        if (isset($field['columnDefinition'])) {
            $columnDef = $this->getCustomTypeDeclarationSQL($field);
        } else {
            $collation = (isset($field['collation']) && $field['collation']) ?
                ' ' . $this->getColumnCollationDeclarationSQL($field['collation']) : '';

            $notnull = (isset($field['notnull']) && $field['notnull']) ? ' NOT NULL' : '';

            $unique = (isset($field['unique']) && $field['unique']) ?
                ' ' . $this->getUniqueFieldDeclarationSQL() : '';

            $check = (isset($field['check']) && $field['check']) ?
                ' ' . $field['check'] : '';

            $typeDecl = $field['type']->getSqlDeclaration($field, $this);
            $columnDef = $typeDecl . $collation . $notnull . $unique . $check;
        }

        return $name . ' ' . $columnDef;
    }

    /**
     * Returns a unique default constraint name for a table and column.
     *
     * @param string $table  Name of the table to generate the unique default constraint name for.
     * @param string $column Name of the column in the table to generate the unique default constraint name for.
     *
     * @return string
     */
    private function generateDefaultConstraintName($table, $column)
    {
        return 'DF_' . $this->generateIdentifierName($table) . '_' . $this->generateIdentifierName($column);
    }

    /**
     * Returns a hash value for a given identifier.
     *
     * @param string $identifier Identifier to generate a hash value for.
     *
     * @return string
     */
    private function generateIdentifierName($identifier)
    {
        // Always generate name for unquoted identifiers to ensure consistency.
        $identifier = new Identifier($identifier);

        return strtoupper(dechex(crc32($identifier->getName())));
    }

    /**
     * Breaks down a SQL statement into logical parts
     *
     * @param $sql
     *
     * @return array (
     *  ['selectList'] => an array of items in the select list as returned by the reprocessSelectList method
     *  ['fromTable'] => a table/view name or subquery expression - a subquery expression will have the same array structure as this array.
     *  ['fromAlias'] => an alias for the fromTable expression,
     *  ['fromHint'] => table hints (if any) for the fromTable,
     *  ['joins'] => an integer-indexed array of join clauses,
     *  ['where'] => $where,
     *  ['orderBy'] => $orderBy
     * )
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getSQLParts($sql) {
        // Walk the sql and break it into a hierarchy according to parens.
        $parts = $this->walkSQLByParens($sql);

        $newParts = array();
        $matches = null;
        foreach ($parts as $part) {

            if (is_array($part)) {
                $newParts[] = $part;
                continue;
            }
            $part = trim($part);
            if (strtoupper(substr($part, 0, 6)) == 'SELECT') {
                $newParts[] = 'SELECT';
                $part = trim(substr($part, 6));
            }
            if (!!preg_match("/(.*)(?:^|\s+)from(?:\s+|$)(.*)/i", $part, $matches)) {
                if(!empty($matches[1])) {
                    $newParts[] = trim($matches[1]);
                }
                $newParts[] = 'FROM';
                if (empty($matches[2])) {
                    continue;
                }

                $part = $matches[2];
            }
            if (preg_match("/(.*)(order by .*)/i", $part, $matches)) {
                if(!empty($matches[1])) {
                    $newParts[] = trim($matches[1]);
                }
                if(!empty($matches[2])) {
                    $newParts[] = trim($matches[2]);
                }
                continue;
            }
            $newParts[] = trim($part);
        }

        $selectList = array();
        $fromTable = $fromAlias = $orderBy = $joins = $where = $fromHint = null;
        $buildSelectList = false;
        $foundFromTable = false;
        foreach ($newParts as $part) {
            if ($part == 'SELECT') {
                // Start building select list
                $buildSelectList = true;
                continue;
            }
            if ($part == 'FROM') {
                // End building select list
                $buildSelectList = false;
                continue;
            }
            if ($buildSelectList && $part != 'FROM') {
                // Keep building select list, haven't found FROM yet
                $selectList[] = $part;// " " . (is_array($part)?trim($this->implodeSubqueryRecursive($part)):$part);
                continue;
            }
            if (!is_array($part) && preg_match("/^order by/i", $part)) {
                // Found ORDER BY
                $orderBy = $part;
                continue;
            }
            if(is_array($part)) {
                $fromTable = $this->getSQLParts($part[0]);
                $foundFromTable = true;
                continue;
            }
            if ($foundFromTable) {
                $where = $this->resolveWhereClauseInQueryFragment($part);
                $joins = $this->resolveJoinsInQueryFragment($part);
                $fromAlias = trim($part);
                continue;
            }

            $where = $this->resolveWhereClauseInQueryFragment($part);
            $joins = $this->resolveJoinsInQueryFragment($part);
            $from = explode(" ", trim($part));
            if (count($from) == 1) {
                $fromTable = $from[0];
                continue;
            }
            if (count($from) == 2) {
                $fromTable = $from[0];
                $fromAlias = $from[1];
                continue;
            }
            if (count($from) == 3 && strtolower($from[1]) == "as") {
                $fromTable = $from[0];
                $fromAlias = $from[2];
                continue;
            }
            if (count($from) > 3) {
                $fromTable = array_shift($from);
                $fromAlias = array_shift($from);
                if(strtolower($fromAlias) == 'as') {
                    $fromAlias .= " " . array_shift($from);
                }
                $fromHint = implode(" ", $from);
                continue;
            }

            //Todo should this throw an exception or just accept the part as the from table?
            //throw new DBALException("Could not parse FROM clause: " . $part);
            $fromTable = $part;
        }

        return array(
            "selectList" => $this->reprocessSelectList($selectList),
            "fromTable" => $fromTable,
            "fromAlias" => $fromAlias,
            "fromHint" => $fromHint,
            "joins" => $joins,
            "where" => $where,
            "orderBy" => $orderBy
        );
    }

    /**
     * Processes an array of fragments of a SQL select list into an array of logically
     * broken down select list items.
     *
     * @param $selectList array of fragments of the select list
     *
     * @return array integer-indexed array. each item consists of one of the following:
     *      a string in the case of a modifier such as DISTINCT
     *      an array in the case of a column expression, of the format:
     *          array(
     *              "select" => column to select,
     *              "as" => 'AS' keyword if it exists in the original query, otherwise null,
     *              "alias" => column alias if specified, otherwise null
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function reprocessSelectList($selectList)
    {
        $selectParts = $this->parseListParts($this->rejoinSelectList($selectList), array("DISTINCT" => true, "ALL" => true));
        foreach($selectParts as $i => $part) {
            if(is_array($part)) {
                $newPart = array(
                    "select" => null,
                    "as" => null,
                    "alias" => null
                );
                if(count($part) == 1) {
                    $newPart["select"] = $part[0];
                } elseif (count($part) == 2) {
                    $newPart["select"] = $part[0];
                    $newPart["alias"] = $part[1];
                } elseif (count($part) == 3) {
                    $newPart["select"] = $part[0];
                    $newPart["as"] = $part[1];
                    $newPart["alias"] = $part[2];
                } else {
                    throw new DBALException("Could not parse select list item.");
                }
                $selectParts[$i] = $newPart;
            }
        }

        return $selectParts;
    }

    /**
     * Converts an array of fragments of a select list into a single complete string
     *
     * @param array $selectList fragments of a select list
     * @return string complete select list string
     */
    private function rejoinSelectList($selectList) {
        $return = array();
        foreach($selectList as $item) {
            if(is_array($item)) {
                $item = $this->implodeSubqueryRecursive($item);
            }
            $return[] = $item;
        }
        return implode(" ", $return);
    }

    /**
     * Removes a WHERE clause from the passed-by-reference string, and returns
     * the WHERE clause, if found.
     *
     * @param string $fragment query fragment in which to search
     *
     * @return string|bool WHERE clause if found, otherwise false
     */
    private function resolveWhereClauseInQueryFragment(&$fragment) {
        $pattern = "/(WHERE (?!.* JOIN ).*)/i";
        $matches = null;
        if(preg_match($pattern, $fragment, $matches)) {
            $fragment = trim(str_replace($matches[1], "", $fragment));
            return $matches[1];
        }
        return false;
    }

    /**
     * Removes any JOIN clauses from the passed-by-reference string and returns an array of
     * the JOIN clauses found, each decomposed into component parts.
     *
     * @param string $fragment query fragment in which to look for JOINs
     *
     * @return array an indexed array of join clauses, each decomposed into components.
     *      format is as returned by decomposeJoinClause method
     */
    private function resolveJoinsInQueryFragment(&$fragment) {
        $pattern = "/(?:FULL|)(?:LEFT|RIGHT|INNER) JOIN(?:.(?!(?:FULL|)(?:LEFT|RIGHT|INNER) JOIN))*/i";
        $matches = null;
        preg_match_all($pattern, $fragment, $matches);
        if(!isset($matches[0])) {
            return array();
        }
        $fragment = preg_replace($pattern, "", $fragment);

        return array_map(array($this, "decomposeJoinClause"), $matches[0]);
    }

    /**
     * Decomposes a join clause into its component parts
     *
     * @param string $joinClause JOIN clause
     *
     * @return array an associative array of parts of the join clause. format:
     *  array(
     *    ['join'] => join type as (FULL|)(LEFT|RIGHT|INNER) JOIN,
     *    ['joinTable'] => join table,
     *    ['joinAlias'] => join table alias,
     *    ['on'] => join ON condition
     *  )
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function decomposeJoinClause($joinClause)
    {
        // Todo handle subquery as join table
        $matches = null;
        $pattern = "/((?:FULL|)(?:LEFT|RIGHT|INNER) JOIN) ((?:.(?! on ))*.)\son\s(.*)/i";
        if(!preg_match($pattern, $joinClause, $matches)) {
            throw new DBALException("Unrecognized join fragment: $joinClause");
        }
        $join = $matches[1];
        $joinTable = $matches[2];
        $on = $matches[3];

        $pattern = "/(.*) ([a-zA-Z0-9_]*)/i";
        if (!preg_match($pattern, $joinTable, $matches)) {
            throw new DBALException("Unrecognized table expression in join fragment: $joinClause");
        }
        $joinTable = $matches[1];
        $joinAlias = $matches[2];
        return array(
            "join" => $join,
            "joinTable" => $joinTable,
            "joinAlias" => $joinAlias,
            "on" => $on
        );
    }

    /**
     * Reassembles a join clause from parts
     *
     * @param array $joinClause associative array of the format returned by decomposeJoinClause
     *
     * @return string reassembled join clause string
     */
    private function recomposeJoinClause($joinClause)
    {
        return "{$joinClause['join']} {$joinClause['joinTable']} {$joinClause['joinAlias']} ON {$joinClause['on']}";
    }

    /**
     * Reassembles an indexed hierarchical array of query parts into a string.
     * This is used to reassemble query parts that don't need to be analyzed further.
     *
     * @param array $parts hierarchical indexed array of query parts as separated by parentheses
     *
     * @return string reassembled query fragment
     */
    private function implodeSubqueryRecursive($parts) {
        $sql = "(";
        foreach($parts as $part) {
            if(is_array($part)) {
                $sql .= " " . $this->implodeSubqueryRecursive($part);
            } else {
                $sql .= trim($part);
            }
        }
        return $sql . ") ";
    }

    /**
     * Decomposes a SQL string by parentheses into a hierarchical indexed array.
     * Only decomposes subqueries within FROM clause table expressions.
     *
     * Example transformation:
     * SELECT *, (SELECT * FROM (SELECT * FROM foobar) baz) as qux FROM (SELECT * FROM (SELECT 1, 2, 3) foo) bar
     * Becomes:
     * array(
     *  [0] => 'SELECT *, (SELECT * FROM (SELECT * FROM foobar) baz) as qux FROM ',
     *  [1] => array(
     *      [0] => 'SELECT * FROM ',
     *      [1] => array(
     *          [0] => 'SELECT 1, 2, 3'
     *      ),
     *      [2] => ' foo'
     *  ),
     *  [2] => ' bar'
     * )
     *
     * @param string|array $sql SQL string or char array to decompose
     * @param int $offset integer character offset in the SQL string
     * @return array decomposed array of SQL parts
     */
    private function walkSQLByParens($sql, &$offset = 0) {
        $parts = array();
        $part = "";
        if (is_string($sql)) {
            $sql = str_split($sql, 1);
        }
        $len = count($sql);

        /**
         * If this isn't a subquery in a from clause, we don't need to walk deeper and analyze
         * it to achieve the goals of the doModifyLimitQuery method. So we detect if
         * the subquery directly follows the FROM keyword. If it does not directly follow a
         * FROM keyword, the readToCloseParenOnSameLevel func reads until the matching close paren
         * and returns the read part intact, ignoring parens within the part. This is a fragile
         * method of detecting this, but it correctly covers SQL generated by the queryBuild
         * and the ORM.
         */
        if($offset > 6) {
            $bit = implode(array_slice($sql, $offset-6, 12));
            if (!preg_match("/FROM \(SELECT/i", $bit)) {
                return $this->readToCloseParenOnSameLevel($sql, $offset);
            }
        }

        for($i = &$offset; $i < $len; $i++) {
            $chr = $sql[$i];
            switch ($chr) {
                case "(":
                    // Store the string we just grabbed prior to the open paren
                    $i++;
                    // Recurse
                    $nextPart = $this->walkSQLByParens($sql, $offset);
                    if (is_array($nextPart)) {
                        $parts[] = $part;
                        $parts[] = $nextPart;
                        $part = "";
                    } else {
                        $part .= $nextPart;
                    }
                    break;
                case ")":
                    // Walk up a level
                    $parts[] = $part;
                    return $parts;
                default:
                    // Add to the part
                    $part .= $chr;
                    break;
            }
        }
        if(!empty($part)) {
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * Reads a complete parenthetical statement, ignoring nested parentheticals within it.
     *
     * @param array $sql array of characters
     * @param int $offset current position within the character array
     *
     * @return string complete parenthetical statement, including opening and closing parentheses
     */
    private function readToCloseParenOnSameLevel($sql, &$offset) {
        // If we're in this method, then we've found an open-paren already,
        // and we're logically at a depth of 1.
        $depth = 1;
        $fragment = "(";
        $len = count($sql);

        // Read characters until either the close paren for depth 1 or EOT
        for($i = &$offset; $i < $len; $i++) {
            $chr = $sql[$i];
            switch ($chr) {
                case "(":
                    $depth++;
                    break;
                case ")":
                    $depth--;
                    break;
            }
            $fragment .= $chr;

            if ($depth == 0) {
                break;
            }
        }

        return $fragment;
    }

    /**
     * Gets the ORDER BY clause from the $sqlParts passed, decomposes it, and returns it.
     * If there is no ORDER BY clause in the query, it checks if the FROM table expression
     * is a subquery, and if so, decomposes and reprocesses the ORDER BY clause from that
     * subquery to be valid in the outer query, and returns that. All this so we can stick
     * the ORDER BY clause into the ROW_NUMBER() OVER() statement and do a limit query...
     *
     * @param array $sqlParts associative array of SQL parts as returned by getSQLParts
     *
     * @return array descriptive array of ORDER BY clause parts as returned by resolveOrderByParts
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getOrderByClauseFromSqlPartArray($sqlParts)
    {
        $orderByIsFromSubquery = false;
        if (isset($sqlParts['orderBy']) && !empty($sqlParts['orderBy'])) {
            $orderBy = $sqlParts['orderBy'];
            $selectList = $sqlParts['selectList'];
        } elseif (isset($sqlParts['fromTable']) && is_array($sqlParts['fromTable'])
            && isset($sqlParts['fromTable']['orderBy']) && !empty($sqlParts['fromTable']['orderBy'])) {
            // Todo parse from subquery select list to get aliases.
            $orderByIsFromSubquery = true;
            $orderBy = $sqlParts['fromTable']['orderBy'];
            $selectList = $sqlParts['fromTable']['selectList'];
        } else {
            return array();
        }
        // Build array of descriptions of each part of the found ORDER BY clause.
        $orderByParts = $this->parseOrderByParts($orderBy);
        $orderByParts = $this->resolveOrderByParts($orderByParts);

        /**
         * If the orderBy clause was pulled from a subquery, the orderBy column names
         * need to be changed to use the aliases from the subquery's select list, otherwise
         * we cannot safely rebuild the orderBy clause into a ROW_NUMBER() OVER(ORDER BY ...)
         * select list item in the outer query. If there is no matching alias, but a wildcard
         * is used, then we can safely assume the column will exist in the outer query.
         */
        if($orderByIsFromSubquery) {
            //
            $orderByParts = $this->resolveAliasesInOrderByParts($orderByParts, $selectList);
        }

        return $orderByParts;
    }

    /**
     * converts an array of items in an ORDER BY clause into an array of arrays describing each
     * item in the ORDER BY clause
     *
     * @param array $orderByParts array of items parsed from an ORDER BY clause
     *
     * @return array array of arrays, each describing an item parsed from an ORDER BY clause. Format is:
     * array(
     *  [0] => array(
     *      'column' => column name
     *      'order' => direction, ASC or DESC
     *  ),
     *  [1] => ...
     * )
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function resolveOrderByParts($orderByParts)
    {
        $newParts = array();

        foreach($orderByParts as $part) {
            $newPart = array(
                "column" => null,
                "order" => null
            );
            if(count($part) == 1) {
                $newPart['column'] = $part[0];
            } elseif (count($part) == 2) {
                $newPart['column'] = $part[0];
                if(preg_match("/^(asc|desc)$/i", $part[1])) {
                    $newPart['order'] = $part[1];
                }
            } else {
                throw new DBALException("Cannot parse order by list.");
            }
            $newParts[] = $newPart;
        }
        return $newParts;
    }

    /**
     * Replaces column names in an array of order by parts with the matching column aliases
     * from the select list.
     *
     * @param array $orderByParts array of descriptors of parts of an ORDER BY clause as
     *      returned by resolveOrderByParts
     * @param array $selectList array of select list items in the format returned by reprocessSelectList
     *
     * @return array identical to input array $orderByParts, except the column member of each
     *      item is replaced by the column alias if it is found in the selectlist.
     */
    private function resolveAliasesInOrderByParts($orderByParts, $selectList)
    {
        $newOrderByParts = array();
        foreach($orderByParts as $part) {
            // Try and find column in select list that matches, and grab the alias
            foreach($selectList as $item) {
                if(is_array($item)) {
                    if($item['alias'] == $part['column']) {
                        //Alias already matches.
                        break;
                    }
                    if(!empty($item['alias'])) {
                        if (preg_match("/^(|[^.]*\.)" . preg_quote($part['column']) . "$/", $item['select'])) {
                            // Alias doesn't match the order by column
                            // but the selected column matches exactly, OR the column name on a FQCN matches
                            $part['column'] = $item['alias'];
                            break;
                        }
                    }
                }
            }
            if(preg_match("/\./", $part['column'])) {
                $exploded = explode(".", $part['column']);
                $part['column'] = end($exploded);
            }
            $newOrderByParts[] = $part;
        }
        return $newOrderByParts;
    }

    /**
     * parses a comma-separated list into an array. Handles a list that may contain
     * parenthetical statements with embedded commas. The list may also contain modifiers
     * such as DISTINCT or ALL ($atomicTokens) that will not be treated as part of the list
     * item they modify, but will be returned as individual items in the returned array.
     *
     * @param string $list comma-separated list
     * @param array $atomicTokens strings to be treated as atomic
     *
     * @return array array of items parsed from the list
     */
    private function parseListParts($list, $atomicTokens = array())
    {
        $listParts = array();
        $list = str_split($list, 1);
        $currentPart = array();
        $currentSubpart = "";
        $len = count($list);
        for($i = 0; $i< $len; $i++) {
            $chr = $list[$i];
            switch($chr) {
                case "(":
                    $i++;
                    $currentSubpart .= $this->readToCloseParenOnSameLevel($list, $i);
                    break;
                case ",":
                    $currentPart[] = $currentSubpart;
                    $listParts[] = $currentPart;
                    $currentSubpart = "";
                    $currentPart = array();
                    break;
                case " ":
                    if (!empty($currentSubpart)) {
                        //Check if we want to treat the current token as atomic (like DISTINCT or ALL modifiers in SELECT lists)
                        if (isset($atomicTokens[strtoupper($currentSubpart)])) {
                            if (!empty($currentPart)) {
                                $listParts[] = $currentPart;
                                $currentPart = array();
                            }
                            $listParts[] = $currentSubpart;
                        } else {
                            $currentPart[] = $currentSubpart;
                        }
                        $currentSubpart = "";
                    }
                    break;
                default:
                    $currentSubpart .= $chr;
                    break;
            }
        }
        if(!empty($currentSubpart)) {
            $currentPart[] = $currentSubpart;
        }
        if(!empty($currentPart)) {
            $listParts[] = $currentPart;
        }

        return $listParts;
    }

    /**
     * parses an ORDER BY clause into an array of its component expressions
     *
     * Example transformation:
     * ORDER BY foo ASC, bar
     * returns:
     * array(
     *  [0] => "foo ASC",
     *  [1] => "bar"
     * )
     *
     * @param string $orderBy ORDER BY clause
     *
     * @return array indexed array of component expressions
     */
    private function parseOrderByParts($orderBy)
    {
        $orderBy = preg_replace("/^\s*order by\s*/i", "", $orderBy);
        return $this->parseListParts($orderBy);
    }

    /**
     * removes ORDER BY clauses from an array of SQL parts
     *
     * @param array $sqlParts associative array of SQL parts as returned by getSQLParts
     *
     * @return array same as input, but with ORDER BY clauses stripped
     */
    private function stripOrderByFromSqlParts($sqlParts)
    {
        if(!empty($sqlParts['orderBy'])) {
            $sqlParts['orderBy'] = null;
        }
        if (is_array($sqlParts['fromTable']) && !empty($sqlParts['fromTable']['orderBy'])) {
            $sqlParts['fromTable']['orderBy'] = null;
        }
        return $sqlParts;
    }

    /**
     * Composes a select list item to select a row number using the ORDER BY clause of the query
     * if it exists.
     *
     * @param array|null $orderBy orderBy clause parts as returned by resolveOrderByParts
     *
     * @return array a select list item
     */
    private function composeRowNumberOverOrderByPart($orderBy)
    {
        if (empty($orderBy)) {
            $orderByClause = "(SELECT 0)";
        } else {
            $orderByClause = trim(implode(", ", array_map(function($item) {return implode(" ", $item);}, $orderBy)));
        }
        return array(
            "select" => "ROW_NUMBER() OVER (ORDER BY $orderByClause)",
            "as" => "AS",
            "alias" => "doctrine_rownum"
        );
    }

    /**
     * Reassembles a SQL query from parts
     *
     * @param array $sqlParts associative array of SQL parts as returned by getSQLParts
     *
     * @return string reassembled SQL query
     */
    private function rebuildSqlQueryFromParts($sqlParts)
    {
        $query = "SELECT "
            . $this->recomposeSelectList($sqlParts['selectList'])
            . " FROM ";
        if (is_array($sqlParts['fromTable'])) {
            $query .= "(" . $this->rebuildSqlQueryFromParts($sqlParts['fromTable']) . ") ";
        } else {
            $query .= "{$sqlParts['fromTable']} ";
        }

        if (!empty($sqlParts['fromAlias'])) {
            $query .= "{$sqlParts['fromAlias']} ";
        }

        if (!empty($sqlParts['fromHint'])) {
            $query .= "{$sqlParts['fromHint']} ";
        }

        if (!empty($sqlParts['joins'])) {
            $query .= implode(" ", array_map(array($this, "recomposeJoinClause"), $sqlParts['joins'])) . " ";
        }

        if (!empty($sqlParts['where'])) {
            $query .= "{$sqlParts['where']} ";
        }

        if (!empty($sqlParts['orderBy'])) {
            $query .= $sqlParts['orderBy'];
        }
        return trim($query);
    }

    /**
     * Reassembles a SELECT list from parts
     *
     * @param array $selectList array of select list items as returned by reprocessSelectList
     *
     * @return string reassembled SELECT list
     */
    private function recomposeSelectList($selectList)
    {
        $recomposed = "";
        foreach ($selectList as $item) {
            if(!is_array($item)) {
                $recomposed .= "$item ";
            } else {
                $recomposed .= $this->recomposeSelectListItem($item) . ', ';
            }
        }
        return rtrim($recomposed, ", ");
    }

    /**
     * reassembles a select list item from parts
     *
     * @param array $item array of parts of the select list item. format:
     *  array(
     *      'select' => column name or subquery expression,
     *      'as' => string literal 'AS' or 'as'
     *      'alias' => column alias
     *  )
     *
     * @return string reassembled select list item
     */
    private function recomposeSelectListItem($item)
    {
        $return = $item['select'];
        if(!empty($item['as'])) {
            $return .= " {$item['as']}";
        }
        if(!empty($item['alias'])) {
            $return .= " {$item['alias']}";
        }
        return $return;
    }

}
