<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Exception\ColumnLengthRequired;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use InvalidArgumentException;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function crc32;
use function dechex;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function preg_match;
use function sprintf;
use function str_replace;
use function stripos;
use function stristr;
use function strlen;
use function strpos;
use function strtoupper;
use function substr;
use function substr_count;

/**
 * The SQLServerPlatform provides the behavior, features and SQL dialect of the
 * Microsoft SQL Server database platform.
 */
class SQLServerPlatform extends AbstractPlatform
{
    /**
     * {@inheritdoc}
     */
    public function getCurrentDateSQL() : string
    {
        return $this->getConvertExpression('date', 'GETDATE()');
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentTimeSQL() : string
    {
        return $this->getConvertExpression('time', 'GETDATE()');
    }

    /**
     * Returns an expression that converts an expression of one data type to another.
     *
     * @param string $dataType   The target native data type. Alias data types cannot be used.
     * @param string $expression The SQL expression to convert.
     */
    private function getConvertExpression(string $dataType, string $expression) : string
    {
        return sprintf('CONVERT(%s, %s)', $dataType, $expression);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateArithmeticIntervalExpression(string $date, string $operator, string $interval, string $unit) : string
    {
        $factorClause = '';

        if ($operator === '-') {
            $factorClause = '-1 * ';
        }

        return 'DATEADD(' . $unit . ', ' . $factorClause . $interval . ', ' . $date . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateDiffExpression(string $date1, string $date2) : string
    {
        return 'DATEDIFF(day, ' . $date2 . ',' . $date1 . ')';
    }

    /**
     * {@inheritDoc}
     *
     * Microsoft SQL Server prefers "autoincrement" identity columns
     * since sequences can only be emulated with a table.
     */
    public function prefersIdentityColumns() : bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * Microsoft SQL Server supports this through AUTO_INCREMENT columns.
     */
    public function supportsIdentityColumns() : bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsReleaseSavepoints() : bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsSchemas() : bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultSchemaName() : string
    {
        return 'dbo';
    }

    /**
     * {@inheritDoc}
     */
    public function supportsColumnCollation() : bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function hasNativeGuidType() : bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateDatabaseSQL(string $database) : string
    {
        return 'CREATE DATABASE ' . $database;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropDatabaseSQL(string $database) : string
    {
        return 'DROP DATABASE ' . $database;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsCreateDropDatabase() : bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateSchemaSQL(string $schemaName) : string
    {
        return 'CREATE SCHEMA ' . $schemaName;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropForeignKeySQL($foreignKey, $table) : string
    {
        if (! $foreignKey instanceof ForeignKeyConstraint) {
            $foreignKey = new Identifier($foreignKey);
        }

        if (! $table instanceof Table) {
            $table = new Identifier($table);
        }

        $foreignKey = $foreignKey->getQuotedName($this);
        $table      = $table->getQuotedName($this);

        return 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $foreignKey;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropIndexSQL($index, $table = null) : string
    {
        if ($index instanceof Index) {
            $index = $index->getQuotedName($this);
        } elseif (! is_string($index)) {
            throw new InvalidArgumentException(sprintf(
                'AbstractPlatform::getDropIndexSQL() expects $index parameter to be a string or an instanceof %s.',
                Index::class
            ));
        }

        if (! isset($table)) {
            return 'DROP INDEX ' . $index;
        }

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        return sprintf(
            <<<SQL
IF EXISTS (SELECT * FROM sysobjects WHERE name = '%s')
    ALTER TABLE %s DROP CONSTRAINT %s
ELSE
    DROP INDEX %s ON %s
SQL
            ,
            $index,
            $table,
            $index,
            $index,
            $table
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL(string $tableName, array $columns, array $options = []) : array
    {
        $defaultConstraintsSql = [];
        $commentsSql           = [];

        $tableComment = $options['comment'] ?? null;
        if ($tableComment !== null) {
            $commentsSql[] = $this->getCommentOnTableSQL($tableName, $tableComment);
        }

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

            if (empty($column['comment']) && ! is_numeric($column['comment'])) {
                continue;
            }

            $commentsSql[] = $this->getCreateColumnCommentSQL($tableName, $column['name'], $column['comment']);
        }

        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && ! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $name => $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if (isset($options['primary']) && ! empty($options['primary'])) {
            $flags = '';
            if (isset($options['primary_index']) && $options['primary_index']->hasFlag('nonclustered')) {
                $flags = ' NONCLUSTERED';
            }
            $columnListSql .= ', PRIMARY KEY' . $flags . ' (' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }

        $query = 'CREATE TABLE ' . $tableName . ' (' . $columnListSql;

        $check = $this->getCheckDeclarationSQL($columns);
        if (! empty($check)) {
            $query .= ', ' . $check;
        }
        $query .= ')';

        $sql = [$query];

        if (isset($options['indexes']) && ! empty($options['indexes'])) {
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
    public function getCreatePrimaryKeySQL(Index $index, $table) : string
    {
        if ($table instanceof Table) {
            $identifier = $table->getQuotedName($this);
        } else {
            $identifier = $table;
        }

        $sql = 'ALTER TABLE ' . $identifier . ' ADD PRIMARY KEY';

        if ($index->hasFlag('nonclustered')) {
            $sql .= ' NONCLUSTERED';
        }

        return $sql . ' (' . $this->getIndexFieldDeclarationListSQL($index) . ')';
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
     * @param string      $tableName  The quoted table name to which the column belongs.
     * @param string      $columnName The quoted column name to create the comment for.
     * @param string|null $comment    The column's comment.
     */
    protected function getCreateColumnCommentSQL(string $tableName, string $columnName, ?string $comment) : string
    {
        if (strpos($tableName, '.') !== false) {
            [$schemaSQL, $tableSQL] = explode('.', $tableName);
            $schemaSQL              = $this->quoteStringLiteral($schemaSQL);
            $tableSQL               = $this->quoteStringLiteral($tableSQL);
        } else {
            $schemaSQL = "'dbo'";
            $tableSQL  = $this->quoteStringLiteral($tableName);
        }

        return $this->getAddExtendedPropertySQL(
            'MS_Description',
            $comment,
            'SCHEMA',
            $schemaSQL,
            'TABLE',
            $tableSQL,
            'COLUMN',
            $columnName
        );
    }

    /**
     * Returns the SQL snippet for declaring a default constraint.
     *
     * @param string  $table  Name of the table to return the default constraint declaration for.
     * @param mixed[] $column Column definition.
     *
     * @throws InvalidArgumentException
     */
    public function getDefaultConstraintDeclarationSQL(string $table, array $column) : string
    {
        if (! isset($column['default'])) {
            throw new InvalidArgumentException('Incomplete column definition. "default" required.');
        }

        $columnName = new Identifier($column['name']);

        return ' CONSTRAINT ' .
            $this->generateDefaultConstraintName($table, $column['name']) .
            $this->getDefaultValueDeclarationSQL($column) .
            ' FOR ' . $columnName->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateIndexSQL(Index $index, $table) : string
    {
        $constraint = parent::getCreateIndexSQL($index, $table);

        if ($index->isUnique() && ! $index->isPrimary()) {
            $constraint = $this->_appendUniqueConstraintDefinition($constraint, $index);
        }

        return $constraint;
    }

    /**
     * {@inheritDoc}
     */
    protected function getCreateIndexSQLFlags(Index $index) : string
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
     */
    private function _appendUniqueConstraintDefinition(string $sql, Index $index) : string
    {
        $fields = [];

        foreach ($index->getQuotedColumns($this) as $field) {
            $fields[] = $field . ' IS NOT NULL';
        }

        return $sql . ' WHERE ' . implode(' AND ', $fields);
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff) : array
    {
        $queryParts  = [];
        $sql         = [];
        $columnSql   = [];
        $commentsSql = [];

        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $columnDef    = $column->toArray();
            $queryParts[] = 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnDef);

            if (isset($columnDef['default'])) {
                $queryParts[] = $this->getAlterTableAddDefaultConstraintClause($diff->name, $column);
            }

            $comment = $this->getColumnComment($column);

            if (empty($comment) && ! is_numeric($comment)) {
                continue;
            }

            $commentsSql[] = $this->getCreateColumnCommentSQL(
                $diff->name,
                $column->getQuotedName($this),
                $comment
            );
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

            $column     = $columnDiff->column;
            $comment    = $this->getColumnComment($column);
            $hasComment = ! empty($comment) || is_numeric($comment);

            if ($columnDiff->fromColumn instanceof Column) {
                $fromComment    = $this->getColumnComment($columnDiff->fromColumn);
                $hasFromComment = ! empty($fromComment) || is_numeric($fromComment);

                if ($hasFromComment && $hasComment && $fromComment !== $comment) {
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

            if (! isset($columnDef['default']) || (! $requireDropDefaultConstraint && ! $columnDiff->hasChanged('default'))) {
                continue;
            }

            $queryParts[] = $this->getAlterTableAddDefaultConstraintClause($diff->name, $column);
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = new Identifier($oldColumnName);

            $sql[] = "sp_RENAME '" .
                $diff->getName($this)->getQuotedName($this) . '.' . $oldColumnName->getQuotedName($this) .
                "', '" . $column->getQuotedName($this) . "', 'COLUMN'";

            // Recreate default constraint with new column name if necessary (for future reference).
            if ($column->getDefault() === null) {
                continue;
            }

            $queryParts[] = $this->getAlterTableDropDefaultConstraintClause(
                $diff->name,
                $oldColumnName->getQuotedName($this)
            );
            $queryParts[] = $this->getAlterTableAddDefaultConstraintClause($diff->name, $column);
        }

        $tableSql = [];

        if ($this->onSchemaAlterTable($diff, $tableSql)) {
            return array_merge($tableSql, $columnSql);
        }

        foreach ($queryParts as $query) {
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
        }

        $sql = array_merge($sql, $commentsSql);

        $newName = $diff->getNewName();

        if ($newName !== null) {
            $sql[] = "sp_RENAME '" . $diff->getName($this)->getQuotedName($this) . "', '" . $newName->getName() . "'";

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
                "'" . $this->generateIdentifierName($newName->getName()) . "') + ''', ''OBJECT'';' " .
                'FROM sys.default_constraints dc ' .
                'JOIN sys.tables tbl ON dc.parent_object_id = tbl.object_id ' .
                "WHERE tbl.name = '" . $newName->getName() . "';" .
                'EXEC sp_executesql @sql';
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
     * @param string $tableName The name of the table to generate the clause for.
     * @param Column $column    The column to generate the clause for.
     */
    private function getAlterTableAddDefaultConstraintClause(string $tableName, Column $column) : string
    {
        $columnDef         = $column->toArray();
        $columnDef['name'] = $column->getQuotedName($this);

        return 'ADD' . $this->getDefaultConstraintDeclarationSQL($tableName, $columnDef);
    }

    /**
     * Returns the SQL clause for dropping an existing default constraint in an ALTER TABLE statement.
     *
     * @param string $tableName  The name of the table to generate the clause for.
     * @param string $columnName The name of the column to generate the clause for.
     */
    private function getAlterTableDropDefaultConstraintClause(string $tableName, string $columnName) : string
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
     * @param ColumnDiff $columnDiff The column diff to evaluate.
     *
     * @return bool True if the column alteration requires dropping its default constraint first, false otherwise.
     */
    private function alterColumnRequiresDropDefaultConstraint(ColumnDiff $columnDiff) : bool
    {
        // We can only decide whether to drop an existing default constraint
        // if we know the original default value.
        if (! $columnDiff->fromColumn instanceof Column) {
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
        return $columnDiff->hasChanged('type') || $columnDiff->hasChanged('fixed');
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
     * @param string      $tableName  The quoted table name to which the column belongs.
     * @param string      $columnName The quoted column name to alter the comment for.
     * @param string|null $comment    The column's comment.
     */
    protected function getAlterColumnCommentSQL(string $tableName, string $columnName, ?string $comment) : string
    {
        if (strpos($tableName, '.') !== false) {
            [$schemaSQL, $tableSQL] = explode('.', $tableName);
            $schemaSQL              = $this->quoteStringLiteral($schemaSQL);
            $tableSQL               = $this->quoteStringLiteral($tableSQL);
        } else {
            $schemaSQL = "'dbo'";
            $tableSQL  = $this->quoteStringLiteral($tableName);
        }

        return $this->getUpdateExtendedPropertySQL(
            'MS_Description',
            $comment,
            'SCHEMA',
            $schemaSQL,
            'TABLE',
            $tableSQL,
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
     */
    protected function getDropColumnCommentSQL(string $tableName, string $columnName) : string
    {
        if (strpos($tableName, '.') !== false) {
            [$schemaSQL, $tableSQL] = explode('.', $tableName);
            $schemaSQL              = $this->quoteStringLiteral($schemaSQL);
            $tableSQL               = $this->quoteStringLiteral($tableSQL);
        } else {
            $schemaSQL = "'dbo'";
            $tableSQL  = $this->quoteStringLiteral($tableName);
        }

        return $this->getDropExtendedPropertySQL(
            'MS_Description',
            'SCHEMA',
            $schemaSQL,
            'TABLE',
            $tableSQL,
            'COLUMN',
            $columnName
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, string $tableName) : array
    {
        return [sprintf(
            "EXEC sp_RENAME N'%s.%s', N'%s', N'INDEX'",
            $tableName,
            $oldIndexName,
            $index->getQuotedName($this)
        ),
        ];
    }

    /**
     * Returns the SQL statement for adding an extended property to a database object.
     *
     * @link http://msdn.microsoft.com/en-us/library/ms180047%28v=sql.90%29.aspx
     *
     * @param string      $name       The name of the property to add.
     * @param string|null $value      The value of the property to add.
     * @param string|null $level0Type The type of the object at level 0 the property belongs to.
     * @param string|null $level0Name The name of the object at level 0 the property belongs to.
     * @param string|null $level1Type The type of the object at level 1 the property belongs to.
     * @param string|null $level1Name The name of the object at level 1 the property belongs to.
     * @param string|null $level2Type The type of the object at level 2 the property belongs to.
     * @param string|null $level2Name The name of the object at level 2 the property belongs to.
     */
    public function getAddExtendedPropertySQL(
        string $name,
        ?string $value = null,
        ?string $level0Type = null,
        ?string $level0Name = null,
        ?string $level1Type = null,
        ?string $level1Name = null,
        ?string $level2Type = null,
        ?string $level2Name = null
    ) : string {
        return 'EXEC sp_addextendedproperty ' .
            'N' . $this->quoteStringLiteral($name) . ', N' . $this->quoteStringLiteral((string) $value) . ', ' .
            'N' . $this->quoteStringLiteral((string) $level0Type) . ', ' . $level0Name . ', ' .
            'N' . $this->quoteStringLiteral((string) $level1Type) . ', ' . $level1Name . ', ' .
            'N' . $this->quoteStringLiteral((string) $level2Type) . ', ' . $level2Name;
    }

    /**
     * Returns the SQL statement for dropping an extended property from a database object.
     *
     * @link http://technet.microsoft.com/en-gb/library/ms178595%28v=sql.90%29.aspx
     *
     * @param string      $name       The name of the property to drop.
     * @param string|null $level0Type The type of the object at level 0 the property belongs to.
     * @param string|null $level0Name The name of the object at level 0 the property belongs to.
     * @param string|null $level1Type The type of the object at level 1 the property belongs to.
     * @param string|null $level1Name The name of the object at level 1 the property belongs to.
     * @param string|null $level2Type The type of the object at level 2 the property belongs to.
     * @param string|null $level2Name The name of the object at level 2 the property belongs to.
     */
    public function getDropExtendedPropertySQL(
        string $name,
        ?string $level0Type = null,
        ?string $level0Name = null,
        ?string $level1Type = null,
        ?string $level1Name = null,
        ?string $level2Type = null,
        ?string $level2Name = null
    ) : string {
        return 'EXEC sp_dropextendedproperty ' .
            'N' . $this->quoteStringLiteral($name) . ', ' .
            'N' . $this->quoteStringLiteral((string) $level0Type) . ', ' . $level0Name . ', ' .
            'N' . $this->quoteStringLiteral((string) $level1Type) . ', ' . $level1Name . ', ' .
            'N' . $this->quoteStringLiteral((string) $level2Type) . ', ' . $level2Name;
    }

    /**
     * Returns the SQL statement for updating an extended property of a database object.
     *
     * @link http://msdn.microsoft.com/en-us/library/ms186885%28v=sql.90%29.aspx
     *
     * @param string      $name       The name of the property to update.
     * @param string|null $value      The value of the property to update.
     * @param string|null $level0Type The type of the object at level 0 the property belongs to.
     * @param string|null $level0Name The name of the object at level 0 the property belongs to.
     * @param string|null $level1Type The type of the object at level 1 the property belongs to.
     * @param string|null $level1Name The name of the object at level 1 the property belongs to.
     * @param string|null $level2Type The type of the object at level 2 the property belongs to.
     * @param string|null $level2Name The name of the object at level 2 the property belongs to.
     */
    public function getUpdateExtendedPropertySQL(
        string $name,
        ?string $value = null,
        ?string $level0Type = null,
        ?string $level0Name = null,
        ?string $level1Type = null,
        ?string $level1Name = null,
        ?string $level2Type = null,
        ?string $level2Name = null
    ) : string {
        return 'EXEC sp_updateextendedproperty ' .
            'N' . $this->quoteStringLiteral($name) . ', N' . $this->quoteStringLiteral((string) $value) . ', ' .
            'N' . $this->quoteStringLiteral((string) $level0Type) . ', ' . $level0Name . ', ' .
            'N' . $this->quoteStringLiteral((string) $level1Type) . ', ' . $level1Name . ', ' .
            'N' . $this->quoteStringLiteral((string) $level2Type) . ', ' . $level2Name;
    }

    /**
     * {@inheritDoc}
     */
    public function getEmptyIdentityInsertSQL(string $tableName, string $identifierColumnName) : string
    {
        return 'INSERT INTO ' . $tableName . ' DEFAULT VALUES';
    }

    /**
     * {@inheritDoc}
     */
    public function getListTablesSQL() : string
    {
        // "sysdiagrams" table must be ignored as it's internal SQL Server table for Database Diagrams
        // Category 2 must be ignored as it is "MS SQL Server 'pseudo-system' object[s]" for replication
        return "SELECT name, SCHEMA_NAME (uid) AS schema_name FROM sysobjects WHERE type = 'U' AND name != 'sysdiagrams' AND category != 2 ORDER BY name";
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableColumnsSQL(string $table, ?string $database = null) : string
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
    public function getListTableForeignKeysSQL(string $table, ?string $database = null) : string
    {
        return 'SELECT f.name AS ForeignKey,
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
                WHERE ' .
                $this->getTableWhereClause($table, 'SCHEMA_NAME (f.schema_id)', 'OBJECT_NAME (f.parent_object_id)');
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableIndexesSQL(string $table, ?string $currentDatabase = null) : string
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
                WHERE " . $this->getTableWhereClause($table, 'scm.name', 'tbl.name') . '
                ORDER BY idx.index_id ASC, idxcol.key_ordinal ASC';
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateViewSQL(string $name, string $sql) : string
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function getListViewsSQL(string $database) : string
    {
        return "SELECT name FROM sysobjects WHERE type = 'V' ORDER BY name";
    }

    /**
     * Returns the where clause to filter schema and table name in a query.
     *
     * @param string $table        The full qualified name of the table.
     * @param string $schemaColumn The name of the column to compare the schema to in the where clause.
     * @param string $tableColumn  The name of the column to compare the table to in the where clause.
     */
    private function getTableWhereClause(string $table, string $schemaColumn, string $tableColumn) : string
    {
        if (strpos($table, '.') !== false) {
            [$schema, $table] = explode('.', $table);
            $schema           = $this->quoteStringLiteral($schema);
            $table            = $this->quoteStringLiteral($table);
        } else {
            $schema = 'SCHEMA_NAME()';
            $table  = $this->quoteStringLiteral($table);
        }

        return sprintf('(%s = %s AND %s = %s)', $tableColumn, $table, $schemaColumn, $schema);
    }

    /**
     * {@inheritDoc}
     */
    public function getDropViewSQL(string $name) : string
    {
        return 'DROP VIEW ' . $name;
    }

    /**
     * {@inheritDoc}
     */
    public function getLocateExpression(string $string, string $substring, ?string $start = null) : string
    {
        if ($start === null) {
            return sprintf('CHARINDEX(%s, %s)', $substring, $string);
        }

        return sprintf('CHARINDEX(%s, %s, %s)', $substring, $string, $start);
    }

    /**
     * {@inheritDoc}
     */
    public function getModExpression(string $dividend, string $divisor) : string
    {
        return $dividend . ' % ' . $divisor;
    }

    /**
     * {@inheritDoc}
     */
    public function getTrimExpression(string $str, int $mode = TrimMode::UNSPECIFIED, ?string $char = null) : string
    {
        if (! in_array($mode, [TrimMode::UNSPECIFIED, TrimMode::LEADING, TrimMode::TRAILING, TrimMode::BOTH], true)) {
            throw new InvalidArgumentException(
                sprintf('The value of $mode is expected to be one of the TrimMode constants, %d given', $mode)
            );
        }

        if ($char === null) {
            switch ($mode) {
                case TrimMode::LEADING:
                    return 'LTRIM(' . $str . ')';

                case TrimMode::TRAILING:
                    return 'RTRIM(' . $str . ')';

                default:
                    return 'LTRIM(RTRIM(' . $str . '))';
            }
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
        $pattern = "'%[^' + " . $char . " + ']%'";

        if ($mode === TrimMode::LEADING) {
            return 'stuff(' . $str . ', 1, patindex(' . $pattern . ', ' . $str . ') - 1, null)';
        }

        if ($mode === TrimMode::TRAILING) {
            return 'reverse(stuff(reverse(' . $str . '), 1, patindex(' . $pattern . ', reverse(' . $str . ')) - 1, null))';
        }

        return 'reverse(stuff(reverse(stuff(' . $str . ', 1, patindex(' . $pattern . ', ' . $str . ') - 1, null)), 1, patindex(' . $pattern . ', reverse(stuff(' . $str . ', 1, patindex(' . $pattern . ', ' . $str . ') - 1, null))) - 1, null))';
    }

    /**
     * {@inheritDoc}
     */
    public function getConcatExpression(string ...$string) : string
    {
        return '(' . implode(' + ', $string) . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getListDatabasesSQL() : string
    {
        return 'SELECT * FROM sys.databases';
    }

    /**
     * {@inheritDoc}
     */
    public function getListNamespacesSQL() : string
    {
        return "SELECT name FROM sys.schemas WHERE name NOT IN('guest', 'INFORMATION_SCHEMA', 'sys')";
    }

    /**
     * {@inheritDoc}
     */
    public function getSubstringExpression(string $string, string $start, ?string $length = null) : string
    {
        if ($length === null) {
            return sprintf('SUBSTRING(%s, %s, LEN(%s) - %s + 1)', $string, $start, $string, $start);
        }

        return sprintf('SUBSTRING(%s, %s, %s)', $string, $start, $length);
    }

    /**
     * {@inheritDoc}
     */
    public function getLengthExpression(string $string) : string
    {
        return 'LEN(' . $string . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentDatabaseExpression() : string
    {
        return 'DB_NAME()';
    }

    /**
     * {@inheritDoc}
     */
    public function getSetTransactionIsolationSQL(int $level) : string
    {
        return 'SET TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $columnDef) : string
    {
        return 'INT' . $this->_getCommonIntegerTypeDeclarationSQL($columnDef);
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $columnDef) : string
    {
        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($columnDef);
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $columnDef) : string
    {
        return 'SMALLINT' . $this->_getCommonIntegerTypeDeclarationSQL($columnDef);
    }

    /**
     * {@inheritDoc}
     */
    public function getGuidTypeDeclarationSQL(array $column) : string
    {
        return 'UNIQUEIDENTIFIER';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzTypeDeclarationSQL(array $fieldDeclaration) : string
    {
        return 'DATETIMEOFFSET(6)';
    }

    /**
     * {@inheritDoc}
     */
    protected function getCharTypeDeclarationSQLSnippet(?int $length) : string
    {
        $sql = 'NCHAR';

        if ($length !== null) {
            $sql .= sprintf('(%d)', $length);
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet(?int $length) : string
    {
        if ($length === null) {
            throw ColumnLengthRequired::new($this, 'NVARCHAR');
        }

        return sprintf('NVARCHAR(%d)', $length);
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field) : string
    {
        return 'VARCHAR(MAX)';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef) : string
    {
        return ! empty($columnDef['autoincrement']) ? ' IDENTITY' : '';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration) : string
    {
        // 3 - microseconds precision length
        // http://msdn.microsoft.com/en-us/library/ms187819.aspx
        return 'DATETIME2(6)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration) : string
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration) : string
    {
        return 'TIME(0)';
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $columnDef) : string
    {
        return 'BIT';
    }

    /**
     * {@inheritDoc}
     */
    protected function doModifyLimitQuery(string $query, ?int $limit, int $offset) : string
    {
        $where = [];

        if ($offset > 0) {
            $where[] = sprintf('doctrine_rownum >= %d', $offset + 1);
        }

        if ($limit !== null) {
            $where[] = sprintf('doctrine_rownum <= %d', $offset + $limit);
            $top     = sprintf('TOP %d', $offset + $limit);
        } else {
            $top = 'TOP 9223372036854775807';
        }

        if (empty($where)) {
            return $query;
        }

        // We'll find a SELECT or SELECT distinct and prepend TOP n to it
        // Even if the TOP n is very large, the use of a CTE will
        // allow the SQL Server query planner to optimize it so it doesn't
        // actually scan the entire range covered by the TOP clause.
        if (! preg_match('/^(\s*SELECT\s+(?:DISTINCT\s+)?)(.*)$/is', $query, $matches)) {
            return $query;
        }

        $query = $matches[1] . $top . ' ' . $matches[2];

        if (stristr($query, 'ORDER BY')) {
            // Inner order by is not valid in SQL Server for our purposes
            // unless it's in a TOP N subquery.
            $query = $this->scrubInnerOrderBy($query);
        }

        // Build a new limited query around the original, using a CTE
        return sprintf(
            'WITH dctrn_cte AS (%s) '
            . 'SELECT * FROM ('
            . 'SELECT *, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM dctrn_cte'
            . ') AS doctrine_tbl '
            . 'WHERE %s ORDER BY doctrine_rownum ASC',
            $query,
            implode(' AND ', $where)
        );
    }

    /**
     * Remove ORDER BY clauses in subqueries - they're not supported by SQL Server.
     * Caveat: will leave ORDER BY in TOP N subqueries.
     */
    private function scrubInnerOrderBy(string $query) : string
    {
        $count  = substr_count(strtoupper($query), 'ORDER BY');
        $offset = 0;

        while ($count-- > 0) {
            $orderByPos = stripos($query, ' ORDER BY', $offset);
            if ($orderByPos === false) {
                break;
            }

            $qLen            = strlen($query);
            $parenCount      = 0;
            $currentPosition = $orderByPos;

            while ($parenCount >= 0 && $currentPosition < $qLen) {
                if ($query[$currentPosition] === '(') {
                    $parenCount++;
                } elseif ($query[$currentPosition] === ')') {
                    $parenCount--;
                }

                $currentPosition++;
            }

            if ($this->isOrderByInTopNSubquery($query, $orderByPos)) {
                // If the order by clause is in a TOP N subquery, do not remove
                // it and continue iteration from the current position.
                $offset = $currentPosition;
                continue;
            }

            if ($currentPosition >= $qLen - 1) {
                continue;
            }

            $query  = substr($query, 0, $orderByPos) . substr($query, $currentPosition - 1);
            $offset = $orderByPos;
        }

        return $query;
    }

    /**
     * Check an ORDER BY clause to see if it is in a TOP N query or subquery.
     *
     * @param string $query           The query
     * @param int    $currentPosition Start position of ORDER BY clause
     *
     * @return bool true if ORDER BY is in a TOP N query, false otherwise
     */
    private function isOrderByInTopNSubquery(string $query, int $currentPosition) : bool
    {
        // Grab query text on the same nesting level as the ORDER BY clause we're examining.
        $subQueryBuffer = '';
        $parenCount     = 0;

        // If $parenCount goes negative, we've exited the subquery we're examining.
        // If $currentPosition goes negative, we've reached the beginning of the query.
        while ($parenCount >= 0 && $currentPosition >= 0) {
            if ($query[$currentPosition] === '(') {
                $parenCount--;
            } elseif ($query[$currentPosition] === ')') {
                $parenCount++;
            }

            // Only yank query text on the same nesting level as the ORDER BY clause.
            $subQueryBuffer = ($parenCount === 0 ? $query[$currentPosition] : ' ') . $subQueryBuffer;

            $currentPosition--;
        }

        return (bool) preg_match('/SELECT\s+(DISTINCT\s+)?TOP\s/i', $subQueryBuffer);
    }

    /**
     * {@inheritDoc}
     */
    public function supportsLimitOffset() : bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function convertBooleans($item)
    {
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                if (! is_bool($value) && ! is_numeric($value)) {
                    continue;
                }

                $item[$key] = $value ? 1 : 0;
            }
        } elseif (is_bool($item) || is_numeric($item)) {
            $item = $item ? 1 : 0;
        }

        return $item;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTemporaryTableSnippetSQL() : string
    {
        return 'CREATE TABLE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTemporaryTableName(string $tableName) : string
    {
        return '#' . $tableName;
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeFormatString() : string
    {
        return 'Y-m-d H:i:s.u';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateFormatString() : string
    {
        return 'Y-m-d';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeFormatString() : string
    {
        return 'H:i:s';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzFormatString() : string
    {
        return 'Y-m-d H:i:s.u P';
    }

    /**
     * {@inheritDoc}
     */
    public function getName() : string
    {
        return 'mssql';
    }

    /**
     * {@inheritDoc}
     */
    protected function initializeDoctrineTypeMappings() : void
    {
        $this->doctrineTypeMapping = [
            'bigint'           => 'bigint',
            'binary'           => 'binary',
            'bit'              => 'boolean',
            'char'             => 'string',
            'date'             => 'date',
            'datetime'         => 'datetime',
            'datetime2'        => 'datetime',
            'datetimeoffset'   => 'datetimetz',
            'decimal'          => 'decimal',
            'double'           => 'float',
            'double precision' => 'float',
            'float'            => 'float',
            'image'            => 'blob',
            'int'              => 'integer',
            'money'            => 'integer',
            'nchar'            => 'string',
            'ntext'            => 'text',
            'numeric'          => 'decimal',
            'nvarchar'         => 'string',
            'real'             => 'float',
            'smalldatetime'    => 'datetime',
            'smallint'         => 'smallint',
            'smallmoney'       => 'integer',
            'text'             => 'text',
            'time'             => 'time',
            'tinyint'          => 'smallint',
            'uniqueidentifier' => 'guid',
            'varbinary'        => 'binary',
            'varchar'          => 'string',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createSavePoint(string $savepoint) : string
    {
        return 'SAVE TRANSACTION ' . $savepoint;
    }

    /**
     * {@inheritDoc}
     */
    public function releaseSavePoint(string $savepoint) : string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function rollbackSavePoint(string $savepoint) : string
    {
        return 'ROLLBACK TRANSACTION ' . $savepoint;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKeyReferentialActionSQL(string $action) : string
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
    public function appendLockHint(string $fromClause, ?int $lockMode) : string
    {
        switch (true) {
            case $lockMode === LockMode::NONE:
                return $fromClause . ' WITH (NOLOCK)';

            case $lockMode === LockMode::PESSIMISTIC_READ:
                return $fromClause . ' WITH (HOLDLOCK, ROWLOCK)';

            case $lockMode === LockMode::PESSIMISTIC_WRITE:
                return $fromClause . ' WITH (UPDLOCK, ROWLOCK)';

            default:
                return $fromClause;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getForUpdateSQL() : string
    {
        return ' ';
    }

    /**
     * {@inheritDoc}
     */
    protected function getReservedKeywordsClass() : string
    {
        return Keywords\SQLServerKeywords::class;
    }

    /**
     * {@inheritDoc}
     */
    public function quoteSingleIdentifier(string $str) : string
    {
        return '[' . str_replace(']', '][', $str) . ']';
    }

    /**
     * {@inheritDoc}
     */
    public function getTruncateTableSQL(string $tableName, bool $cascade = false) : string
    {
        $tableIdentifier = new Identifier($tableName);

        return 'TRUNCATE TABLE ' . $tableIdentifier->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field) : string
    {
        return 'VARBINARY(MAX)';
    }

    /**
     * {@inheritdoc}
     *
     * Modifies column declaration order as it differs in Microsoft SQL Server.
     */
    public function getColumnDeclarationSQL(string $name, array $field) : string
    {
        if (isset($field['columnDefinition'])) {
            $columnDef = $this->getCustomTypeDeclarationSQL($field);
        } else {
            $collation = isset($field['collation']) && $field['collation'] ?
                ' ' . $this->getColumnCollationDeclarationSQL($field['collation']) : '';

            $notnull = isset($field['notnull']) && $field['notnull'] ? ' NOT NULL' : '';

            $unique = isset($field['unique']) && $field['unique'] ?
                ' ' . $this->getUniqueFieldDeclarationSQL() : '';

            $check = isset($field['check']) && $field['check'] ?
                ' ' . $field['check'] : '';

            $typeDecl  = $field['type']->getSQLDeclaration($field, $this);
            $columnDef = $typeDecl . $collation . $notnull . $unique . $check;
        }

        return $name . ' ' . $columnDef;
    }

    /**
     * {@inheritdoc}
     */
    protected function getLikeWildcardCharacters() : string
    {
        return parent::getLikeWildcardCharacters() . '[]^';
    }

    /**
     * Returns a unique default constraint name for a table and column.
     *
     * @param string $table  Name of the table to generate the unique default constraint name for.
     * @param string $column Name of the column in the table to generate the unique default constraint name for.
     */
    private function generateDefaultConstraintName(string $table, string $column) : string
    {
        return 'DF_' . $this->generateIdentifierName($table) . '_' . $this->generateIdentifierName($column);
    }

    /**
     * Returns a hash value for a given identifier.
     *
     * @param string $identifier Identifier to generate a hash value for.
     */
    private function generateIdentifierName(string $identifier) : string
    {
        // Always generate name for unquoted identifiers to ensure consistency.
        $identifier = new Identifier($identifier);

        return strtoupper(dechex(crc32($identifier->getName())));
    }

    protected function getCommentOnTableSQL(string $tableName, ?string $comment) : string
    {
        return sprintf(
            <<<'SQL'
EXEC sys.sp_addextendedproperty @name=N'MS_Description', 
  @value=N%s, @level0type=N'SCHEMA', @level0name=N'dbo', 
  @level1type=N'TABLE', @level1name=N%s
SQL
            ,
            $this->quoteStringLiteral((string) $comment),
            $this->quoteStringLiteral($tableName)
        );
    }

    public function getListTableMetadataSQL(string $table) : string
    {
        return sprintf(
            <<<'SQL'
SELECT
  p.value AS [table_comment]
FROM
  sys.tables AS tbl
  INNER JOIN sys.extended_properties AS p ON p.major_id=tbl.object_id AND p.minor_id=0 AND p.class=1
WHERE
  (tbl.name=N%s and SCHEMA_NAME(tbl.schema_id)=N'dbo' and p.name=N'MS_Description')
SQL
            ,
            $this->quoteStringLiteral($table)
        );
    }
}
