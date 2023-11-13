<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnLengthRequired;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\SQLServerKeywords;
use Doctrine\DBAL\Platforms\SQLServer\SQL\Builder\SQLServerSelectSQLBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;

use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function is_array;
use function is_bool;
use function is_numeric;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function str_replace;
use function strtoupper;
use function substr_count;

use const PREG_OFFSET_CAPTURE;

/**
 * Provides the behavior, features and SQL dialect of the Microsoft SQL Server database platform
 * of the oldest supported version.
 */
class SQLServerPlatform extends AbstractPlatform
{
    /** @internal Should be used only from within the {@see AbstractSchemaManager} class hierarchy. */
    public const OPTION_DEFAULT_CONSTRAINT_NAME = 'default_constraint_name';

    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return new SQLServerSelectSQLBuilder($this);
    }

    public function getCurrentDateSQL(): string
    {
        return $this->getConvertExpression('date', 'GETDATE()');
    }

    public function getCurrentTimeSQL(): string
    {
        return $this->getConvertExpression('time', 'GETDATE()');
    }

    /**
     * Returns an expression that converts an expression of one data type to another.
     *
     * @param string $dataType   The target native data type. Alias data types cannot be used.
     * @param string $expression The SQL expression to convert.
     */
    private function getConvertExpression(string $dataType, string $expression): string
    {
        return sprintf('CONVERT(%s, %s)', $dataType, $expression);
    }

    protected function getDateArithmeticIntervalExpression(
        string $date,
        string $operator,
        string $interval,
        DateIntervalUnit $unit,
    ): string {
        $factorClause = '';

        if ($operator === '-') {
            $factorClause = '-1 * ';
        }

        return 'DATEADD(' . $unit->value . ', ' . $factorClause . $interval . ', ' . $date . ')';
    }

    public function getDateDiffExpression(string $date1, string $date2): string
    {
        return 'DATEDIFF(day, ' . $date2 . ',' . $date1 . ')';
    }

    /**
     * {@inheritDoc}
     *
     * Microsoft SQL Server supports this through AUTO_INCREMENT columns.
     */
    public function supportsIdentityColumns(): bool
    {
        return true;
    }

    public function supportsReleaseSavepoints(): bool
    {
        return false;
    }

    public function supportsSchemas(): bool
    {
        return true;
    }

    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function supportsColumnCollation(): bool
    {
        return true;
    }

    public function supportsSequences(): bool
    {
        return true;
    }

    public function getAlterSequenceSQL(Sequence $sequence): string
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this) .
            ' INCREMENT BY ' . $sequence->getAllocationSize();
    }

    public function getCreateSequenceSQL(Sequence $sequence): string
    {
        return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this) .
            ' START WITH ' . $sequence->getInitialValue() .
            ' INCREMENT BY ' . $sequence->getAllocationSize() .
            ' MINVALUE ' . $sequence->getInitialValue();
    }

    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function getListSequencesSQL(string $database): string
    {
        return 'SELECT seq.name,
                       CAST(
                           seq.increment AS VARCHAR(MAX)
                       ) AS increment, -- CAST avoids driver error for sql_variant type
                       CAST(
                           seq.start_value AS VARCHAR(MAX)
                       ) AS start_value -- CAST avoids driver error for sql_variant type
                FROM   sys.sequences AS seq';
    }

    public function getSequenceNextValSQL(string $sequence): string
    {
        return 'SELECT NEXT VALUE FOR ' . $sequence;
    }

    public function getDropForeignKeySQL(string $foreignKey, string $table): string
    {
        return $this->getDropConstraintSQL($foreignKey, $table);
    }

    public function getDropIndexSQL(string $name, string $table): string
    {
        return 'DROP INDEX ' . $name . ' ON ' . $table;
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL(string $name, array $columns, array $options = []): array
    {
        $defaultConstraintsSql = [];
        $commentsSql           = [];

        $tableComment = $options['comment'] ?? null;
        if ($tableComment !== null) {
            $commentsSql[] = $this->getCommentOnTableSQL($name, $tableComment);
        }

        // @todo does other code breaks because of this?
        // force primary keys to be not null
        foreach ($columns as &$column) {
            if (! empty($column['primary'])) {
                $column['notnull'] = true;
            }

            // Build default constraints SQL statements.
            if (isset($column['default'])) {
                $defaultConstraintsSql[] = 'ALTER TABLE ' . $name .
                    ' ADD' . $this->getDefaultConstraintDeclarationSQL($column);
            }

            if (empty($column['comment']) && ! is_numeric($column['comment'])) {
                continue;
            }

            $commentsSql[] = $this->getCreateColumnCommentSQL($name, $column['name'], $column['comment']);
        }

        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && ! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($definition);
            }
        }

        if (isset($options['primary']) && ! empty($options['primary'])) {
            $flags = '';
            if (isset($options['primary_index']) && $options['primary_index']->hasFlag('nonclustered')) {
                $flags = ' NONCLUSTERED';
            }

            $columnListSql .= ', PRIMARY KEY' . $flags
                . ' (' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }

        $query = 'CREATE TABLE ' . $name . ' (' . $columnListSql;

        $check = $this->getCheckDeclarationSQL($columns);
        if (! empty($check)) {
            $query .= ', ' . $check;
        }

        $query .= ')';

        $sql = [$query];

        if (isset($options['indexes']) && ! empty($options['indexes'])) {
            foreach ($options['indexes'] as $index) {
                $sql[] = $this->getCreateIndexSQL($index, $name);
            }
        }

        if (isset($options['foreignKeys'])) {
            foreach ($options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $name);
            }
        }

        return array_merge($sql, $commentsSql, $defaultConstraintsSql);
    }

    public function getCreatePrimaryKeySQL(Index $index, string $table): string
    {
        $sql = 'ALTER TABLE ' . $table . ' ADD PRIMARY KEY';

        if ($index->hasFlag('nonclustered')) {
            $sql .= ' NONCLUSTERED';
        }

        return $sql . ' (' . implode(', ', $index->getQuotedColumns($this)) . ')';
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
     */
    protected function getCreateColumnCommentSQL(string $tableName, string $columnName, string $comment): string
    {
        if (str_contains($tableName, '.')) {
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
            $columnName,
        );
    }

    /**
     * Returns the SQL snippet for declaring a default constraint.
     *
     * @param mixed[] $column Column definition.
     */
    protected function getDefaultConstraintDeclarationSQL(array $column): string
    {
        if (! isset($column['default'])) {
            throw new InvalidArgumentException('Incomplete column definition. "default" required.');
        }

        $columnName = new Identifier($column['name']);

        return $this->getDefaultValueDeclarationSQL($column) . ' FOR ' . $columnName->getQuotedName($this);
    }

    public function getCreateIndexSQL(Index $index, string $table): string
    {
        $constraint = parent::getCreateIndexSQL($index, $table);

        if ($index->isUnique() && ! $index->isPrimary()) {
            $constraint = $this->_appendUniqueConstraintDefinition($constraint, $index);
        }

        return $constraint;
    }

    protected function getCreateIndexSQLFlags(Index $index): string
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
    private function _appendUniqueConstraintDefinition(string $sql, Index $index): string
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
    public function getAlterTableSQL(TableDiff $diff): array
    {
        $queryParts  = [];
        $sql         = [];
        $columnSql   = [];
        $commentsSql = [];

        $table = $diff->getOldTable();

        $tableName = $table->getName();

        foreach ($diff->getAddedColumns() as $column) {
            $columnProperties = $column->toArray();

            $addColumnSql = 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnProperties);

            if (isset($columnProperties['default'])) {
                $addColumnSql .= $this->getDefaultValueDeclarationSQL($columnProperties);
            }

            $queryParts[] = $addColumnSql;

            $comment = $column->getComment();

            if ($comment === '') {
                continue;
            }

            $commentsSql[] = $this->getCreateColumnCommentSQL(
                $tableName,
                $column->getQuotedName($this),
                $comment,
            );
        }

        foreach ($diff->getDroppedColumns() as $column) {
            if ($column->getDefault() !== null) {
                $queryParts[] = $this->getAlterTableDropDefaultConstraintClause($column);
            }

            $queryParts[] = 'DROP COLUMN ' . $column->getQuotedName($this);
        }

        foreach ($diff->getModifiedColumns() as $columnDiff) {
            $newColumn     = $columnDiff->getNewColumn();
            $newComment    = $newColumn->getComment();
            $hasNewComment = $newComment !== '';

            $oldColumn     = $columnDiff->getOldColumn();
            $oldComment    = $oldColumn->getComment();
            $hasOldComment = $oldComment !== '';

            if ($hasOldComment && $hasNewComment && $newComment !== $oldComment) {
                $commentsSql[] = $this->getAlterColumnCommentSQL(
                    $tableName,
                    $newColumn->getQuotedName($this),
                    $newComment,
                );
            } elseif ($hasOldComment && ! $hasNewComment) {
                $commentsSql[] = $this->getDropColumnCommentSQL(
                    $tableName,
                    $newColumn->getQuotedName($this),
                );
            } elseif (! $hasOldComment && $hasNewComment) {
                $commentsSql[] = $this->getCreateColumnCommentSQL(
                    $tableName,
                    $newColumn->getQuotedName($this),
                    $newComment,
                );
            }

                $columnNameSQL = $newColumn->getQuotedName($this);

                $oldDeclarationSQL = $this->getColumnDeclarationSQL($columnNameSQL, $oldColumn->toArray());
                $newDeclarationSQL = $this->getColumnDeclarationSQL($columnNameSQL, $newColumn->toArray());

                $declarationSQLChanged = $newDeclarationSQL !== $oldDeclarationSQL;
                $defaultChanged        = $columnDiff->hasDefaultChanged();

            if (! $declarationSQLChanged && ! $defaultChanged) {
                continue;
            }

                $requireDropDefaultConstraint = $this->alterColumnRequiresDropDefaultConstraint($columnDiff);

            if ($requireDropDefaultConstraint) {
                $queryParts[] = $this->getAlterTableDropDefaultConstraintClause($oldColumn);
            }

            if ($declarationSQLChanged) {
                $queryParts[] = 'ALTER COLUMN ' . $newDeclarationSQL;
            }

            if (
                    $newColumn->getDefault() === null
                    || (! $requireDropDefaultConstraint && ! $defaultChanged)
            ) {
                continue;
            }

                $queryParts[] = $this->getAlterTableAddDefaultConstraintClause($tableName, $newColumn);
        }

        $tableNameSQL = $table->getQuotedName($this);

        foreach ($diff->getRenamedColumns() as $oldColumnName => $newColumn) {
            $oldColumnName = new Identifier($oldColumnName);

            $sql[] = sprintf(
                "sp_rename '%s.%s', '%s', 'COLUMN'",
                $tableNameSQL,
                $oldColumnName->getQuotedName($this),
                $newColumn->getQuotedName($this),
            );
        }

        foreach ($queryParts as $query) {
            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
        }

        return array_merge(
            $this->getPreAlterTableIndexForeignKeySQL($diff),
            $sql,
            $commentsSql,
            $this->getPostAlterTableIndexForeignKeySQL($diff),
            $columnSql,
        );
    }

    public function getRenameTableSQL(string $oldName, string $newName): string
    {
        return sprintf(
            'sp_rename %s, %s',
            $this->quoteStringLiteral($oldName),
            $this->quoteStringLiteral($newName),
        );
    }

    /**
     * Returns the SQL clause for adding a default constraint in an ALTER TABLE statement.
     *
     * @param string $tableName The name of the table to generate the clause for.
     * @param Column $column    The column to generate the clause for.
     */
    private function getAlterTableAddDefaultConstraintClause(string $tableName, Column $column): string
    {
        $columnDef         = $column->toArray();
        $columnDef['name'] = $column->getQuotedName($this);

        return 'ADD' . $this->getDefaultConstraintDeclarationSQL($columnDef);
    }

    /**
     * Returns the SQL clause for dropping an existing default constraint in an ALTER TABLE statement.
     */
    private function getAlterTableDropDefaultConstraintClause(Column $column): string
    {
        if (! $column->hasPlatformOption(self::OPTION_DEFAULT_CONSTRAINT_NAME)) {
            throw new InvalidArgumentException(
                'Column ' . $column->getName() . ' was not properly introspected as it has a default value'
                    . ' but does not have the default constraint name.',
            );
        }

        return 'DROP CONSTRAINT ' . $this->quoteIdentifier(
            $column->getPlatformOption(self::OPTION_DEFAULT_CONSTRAINT_NAME),
        );
    }

    /**
     * Checks whether a column alteration requires dropping its default constraint first.
     *
     * Different to other database vendors SQL Server implements column default values
     * as constraints and therefore changes in a column's default value as well as changes
     * in a column's type require dropping the default constraint first before being to
     * alter the particular column to the new definition.
     */
    private function alterColumnRequiresDropDefaultConstraint(ColumnDiff $columnDiff): bool
    {
        // We only need to drop an existing default constraint if we know the
        // column was defined with a default value before.
        if ($columnDiff->getOldColumn()->getDefault() === null) {
            return false;
        }

        // We need to drop an existing default constraint if the column was
        // defined with a default value before and it has changed.
        if ($columnDiff->hasDefaultChanged()) {
            return true;
        }

        // We need to drop an existing default constraint if the column was
        // defined with a default value before and the native column type has changed.
        return $columnDiff->hasTypeChanged() || $columnDiff->hasFixedChanged();
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
     */
    protected function getAlterColumnCommentSQL(string $tableName, string $columnName, string $comment): string
    {
        if (str_contains($tableName, '.')) {
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
            $columnName,
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
    protected function getDropColumnCommentSQL(string $tableName, string $columnName): string
    {
        if (str_contains($tableName, '.')) {
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
            $columnName,
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, string $tableName): array
    {
        return [sprintf(
            "EXEC sp_rename N'%s.%s', N'%s', N'INDEX'",
            $tableName,
            $oldIndexName,
            $index->getQuotedName($this),
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
    protected function getAddExtendedPropertySQL(
        string $name,
        ?string $value = null,
        ?string $level0Type = null,
        ?string $level0Name = null,
        ?string $level1Type = null,
        ?string $level1Name = null,
        ?string $level2Type = null,
        ?string $level2Name = null,
    ): string {
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
    protected function getDropExtendedPropertySQL(
        string $name,
        ?string $level0Type = null,
        ?string $level0Name = null,
        ?string $level1Type = null,
        ?string $level1Name = null,
        ?string $level2Type = null,
        ?string $level2Name = null,
    ): string {
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
    protected function getUpdateExtendedPropertySQL(
        string $name,
        ?string $value = null,
        ?string $level0Type = null,
        ?string $level0Name = null,
        ?string $level1Type = null,
        ?string $level1Name = null,
        ?string $level2Type = null,
        ?string $level2Name = null,
    ): string {
        return 'EXEC sp_updateextendedproperty ' .
            'N' . $this->quoteStringLiteral($name) . ', N' . $this->quoteStringLiteral((string) $value) . ', ' .
            'N' . $this->quoteStringLiteral((string) $level0Type) . ', ' . $level0Name . ', ' .
            'N' . $this->quoteStringLiteral((string) $level1Type) . ', ' . $level1Name . ', ' .
            'N' . $this->quoteStringLiteral((string) $level2Type) . ', ' . $level2Name;
    }

    public function getEmptyIdentityInsertSQL(string $quotedTableName, string $quotedIdentifierColumnName): string
    {
        return 'INSERT INTO ' . $quotedTableName . ' DEFAULT VALUES';
    }

    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function getListViewsSQL(string $database): string
    {
        return "SELECT name, definition FROM sysobjects
                    INNER JOIN sys.sql_modules ON sysobjects.id = sys.sql_modules.object_id
                WHERE type = 'V' ORDER BY name";
    }

    public function getLocateExpression(string $string, string $substring, ?string $start = null): string
    {
        if ($start === null) {
            return sprintf('CHARINDEX(%s, %s)', $substring, $string);
        }

        return sprintf('CHARINDEX(%s, %s, %s)', $substring, $string, $start);
    }

    public function getModExpression(string $dividend, string $divisor): string
    {
        return $dividend . ' % ' . $divisor;
    }

    public function getTrimExpression(
        string $str,
        TrimMode $mode = TrimMode::UNSPECIFIED,
        ?string $char = null,
    ): string {
        if ($char === null) {
            return match ($mode) {
                TrimMode::LEADING => 'LTRIM(' . $str . ')',
                TrimMode::TRAILING => 'RTRIM(' . $str . ')',
                default => 'LTRIM(RTRIM(' . $str . '))',
            };
        }

        $pattern = "'%[^' + " . $char . " + ']%'";

        if ($mode === TrimMode::LEADING) {
            return 'stuff(' . $str . ', 1, patindex(' . $pattern . ', ' . $str . ') - 1, null)';
        }

        if ($mode === TrimMode::TRAILING) {
            return 'reverse(stuff(reverse(' . $str . '), 1, '
                . 'patindex(' . $pattern . ', reverse(' . $str . ')) - 1, null))';
        }

        return 'reverse(stuff(reverse(stuff(' . $str . ', 1, patindex(' . $pattern . ', ' . $str . ') - 1, null)), 1, '
            . 'patindex(' . $pattern . ', reverse(stuff(' . $str . ', 1, patindex(' . $pattern . ', ' . $str
            . ') - 1, null))) - 1, null))';
    }

    public function getConcatExpression(string ...$string): string
    {
        return sprintf('CONCAT(%s)', implode(', ', $string));
    }

    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function getListDatabasesSQL(): string
    {
        return 'SELECT * FROM sys.databases';
    }

    public function getSubstringExpression(string $string, string $start, ?string $length = null): string
    {
        if ($length === null) {
            return sprintf('SUBSTRING(%s, %s, LEN(%s) - %s + 1)', $string, $start, $string, $start);
        }

        return sprintf('SUBSTRING(%s, %s, %s)', $string, $start, $length);
    }

    public function getLengthExpression(string $string): string
    {
        return 'LEN(' . $string . ')';
    }

    public function getCurrentDatabaseExpression(): string
    {
        return 'DB_NAME()';
    }

    public function getSetTransactionIsolationSQL(TransactionIsolationLevel $level): string
    {
        return 'SET TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $column): string
    {
        return 'INT' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $column): string
    {
        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $column): string
    {
        return 'SMALLINT' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getGuidTypeDeclarationSQL(array $column): string
    {
        return 'UNIQUEIDENTIFIER';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzTypeDeclarationSQL(array $column): string
    {
        return 'DATETIMEOFFSET(6)';
    }

    protected function getCharTypeDeclarationSQLSnippet(?int $length): string
    {
        $sql = 'NCHAR';

        if ($length !== null) {
            $sql .= sprintf('(%d)', $length);
        }

        return $sql;
    }

    protected function getVarcharTypeDeclarationSQLSnippet(?int $length): string
    {
        if ($length === null) {
            throw ColumnLengthRequired::new($this, 'NVARCHAR');
        }

        return sprintf('NVARCHAR(%d)', $length);
    }

    /**
     * {@inheritDoc}
     */
    public function getAsciiStringTypeDeclarationSQL(array $column): string
    {
        $length = $column['length'] ?? null;

        if (empty($column['fixed'])) {
            return parent::getVarcharTypeDeclarationSQLSnippet($length);
        }

        return parent::getCharTypeDeclarationSQLSnippet($length);
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $column): string
    {
        return 'VARCHAR(MAX)';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $column): string
    {
        return ! empty($column['autoincrement']) ? ' IDENTITY' : '';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $column): string
    {
        // 3 - microseconds precision length
        // http://msdn.microsoft.com/en-us/library/ms187819.aspx
        return 'DATETIME2(6)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTypeDeclarationSQL(array $column): string
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $column): string
    {
        return 'TIME(0)';
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $column): string
    {
        return 'BIT';
    }

    protected function doModifyLimitQuery(string $query, ?int $limit, int $offset): string
    {
        if ($limit === null && $offset <= 0) {
            return $query;
        }

        if ($this->shouldAddOrderBy($query)) {
            if (preg_match('/^SELECT\s+DISTINCT/im', $query) > 0) {
                // SQL Server won't let us order by a non-selected column in a DISTINCT query,
                // so we have to do this madness. This says, order by the first column in the
                // result. SQL Server's docs say that a nonordered query's result order is non-
                // deterministic anyway, so this won't do anything that a bunch of update and
                // deletes to the table wouldn't do anyway.
                $query .= ' ORDER BY 1';
            } else {
                // In another DBMS, we could do ORDER BY 0, but SQL Server gets angry if you
                // use constant expressions in the order by list.
                $query .= ' ORDER BY (SELECT 0)';
            }
        }

        // This looks somewhat like MYSQL, but limit/offset are in inverse positions
        // Supposedly SQL:2008 core standard.
        // Per TSQL spec, FETCH NEXT n ROWS ONLY is not valid without OFFSET n ROWS.
        $query .= sprintf(' OFFSET %d ROWS', $offset);

        if ($limit !== null) {
            $query .= sprintf(' FETCH NEXT %d ROWS ONLY', $limit);
        }

        return $query;
    }

    public function convertBooleans(mixed $item): mixed
    {
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                if (! is_bool($value) && ! is_numeric($value)) {
                    continue;
                }

                $item[$key] = (int) (bool) $value;
            }
        } elseif (is_bool($item) || is_numeric($item)) {
            $item = (int) (bool) $item;
        }

        return $item;
    }

    public function getCreateTemporaryTableSnippetSQL(): string
    {
        return 'CREATE TABLE';
    }

    public function getTemporaryTableName(string $tableName): string
    {
        return '#' . $tableName;
    }

    public function getDateTimeFormatString(): string
    {
        return 'Y-m-d H:i:s.u';
    }

    public function getDateFormatString(): string
    {
        return 'Y-m-d';
    }

    public function getTimeFormatString(): string
    {
        return 'H:i:s';
    }

    public function getDateTimeTzFormatString(): string
    {
        return 'Y-m-d H:i:s.u P';
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        $this->doctrineTypeMapping = [
            'bigint'           => Types::BIGINT,
            'binary'           => Types::BINARY,
            'bit'              => Types::BOOLEAN,
            'blob'             => Types::BLOB,
            'char'             => Types::STRING,
            'date'             => Types::DATE_MUTABLE,
            'datetime'         => Types::DATETIME_MUTABLE,
            'datetime2'        => Types::DATETIME_MUTABLE,
            'datetimeoffset'   => Types::DATETIMETZ_MUTABLE,
            'decimal'          => Types::DECIMAL,
            'double'           => Types::FLOAT,
            'double precision' => Types::FLOAT,
            'float'            => Types::FLOAT,
            'image'            => Types::BLOB,
            'int'              => Types::INTEGER,
            'money'            => Types::INTEGER,
            'nchar'            => Types::STRING,
            'ntext'            => Types::TEXT,
            'numeric'          => Types::DECIMAL,
            'nvarchar'         => Types::STRING,
            'real'             => Types::FLOAT,
            'smalldatetime'    => Types::DATETIME_MUTABLE,
            'smallint'         => Types::SMALLINT,
            'smallmoney'       => Types::INTEGER,
            'text'             => Types::TEXT,
            'time'             => Types::TIME_MUTABLE,
            'tinyint'          => Types::SMALLINT,
            'uniqueidentifier' => Types::GUID,
            'varbinary'        => Types::BINARY,
            'varchar'          => Types::STRING,
        ];
    }

    public function createSavePoint(string $savepoint): string
    {
        return 'SAVE TRANSACTION ' . $savepoint;
    }

    public function releaseSavePoint(string $savepoint): string
    {
        return '';
    }

    public function rollbackSavePoint(string $savepoint): string
    {
        return 'ROLLBACK TRANSACTION ' . $savepoint;
    }

    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function getForeignKeyReferentialActionSQL(string $action): string
    {
        // RESTRICT is not supported, therefore falling back to NO ACTION.
        if (strtoupper($action) === 'RESTRICT') {
            return 'NO ACTION';
        }

        return parent::getForeignKeyReferentialActionSQL($action);
    }

    public function appendLockHint(string $fromClause, LockMode $lockMode): string
    {
        return match ($lockMode) {
            LockMode::NONE,
            LockMode::OPTIMISTIC => $fromClause,
            LockMode::PESSIMISTIC_READ => $fromClause . ' WITH (HOLDLOCK, ROWLOCK)',
            LockMode::PESSIMISTIC_WRITE => $fromClause . ' WITH (UPDLOCK, ROWLOCK)',
        };
    }

    protected function createReservedKeywordsList(): KeywordList
    {
        return new SQLServerKeywords();
    }

    public function quoteSingleIdentifier(string $str): string
    {
        return '[' . str_replace(']', ']]', $str) . ']';
    }

    public function getTruncateTableSQL(string $tableName, bool $cascade = false): string
    {
        $tableIdentifier = new Identifier($tableName);

        return 'TRUNCATE TABLE ' . $tableIdentifier->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $column): string
    {
        return 'VARBINARY(MAX)';
    }

    /**
     * {@inheritDoc}
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function getColumnDeclarationSQL(string $name, array $column): string
    {
        if (isset($column['columnDefinition'])) {
            $declaration = $column['columnDefinition'];
        } else {
            $collation = ! empty($column['collation']) ?
                ' ' . $this->getColumnCollationDeclarationSQL($column['collation']) : '';

            $notnull = ! empty($column['notnull']) ? ' NOT NULL' : '';

            $typeDecl    = $column['type']->getSQLDeclaration($column, $this);
            $declaration = $typeDecl . $collation . $notnull;
        }

        return $name . ' ' . $declaration;
    }

    /**
     * SQL Server does not support quoting collation identifiers.
     */
    public function getColumnCollationDeclarationSQL(string $collation): string
    {
        return 'COLLATE ' . $collation;
    }

    public function columnsEqual(Column $column1, Column $column2): bool
    {
        if (! parent::columnsEqual($column1, $column2)) {
            return false;
        }

        return $this->getDefaultValueDeclarationSQL($column1->toArray())
            === $this->getDefaultValueDeclarationSQL($column2->toArray());
    }

    protected function getLikeWildcardCharacters(): string
    {
        return parent::getLikeWildcardCharacters() . '[]^';
    }

    protected function getCommentOnTableSQL(string $tableName, string $comment): string
    {
        return sprintf(
            <<<'SQL'
                EXEC sys.sp_addextendedproperty @name=N'MS_Description',
                  @value=N%s, @level0type=N'SCHEMA', @level0name=N'dbo',
                  @level1type=N'TABLE', @level1name=N%s
                SQL
            ,
            $this->quoteStringLiteral($comment),
            $this->quoteStringLiteral($tableName),
        );
    }

    private function shouldAddOrderBy(string $query): bool
    {
        // Find the position of the last instance of ORDER BY and ensure it is not within a parenthetical statement
        // but can be in a newline
        $matches      = [];
        $matchesCount = preg_match_all('/[\\s]+order\\s+by\\s/im', $query, $matches, PREG_OFFSET_CAPTURE);
        if ($matchesCount === 0) {
            return true;
        }

        // ORDER BY instance may be in a subquery after ORDER BY
        // e.g. SELECT col1 FROM test ORDER BY (SELECT col2 from test ORDER BY col2)
        // if in the searched query ORDER BY clause was found where
        // number of open parentheses after the occurrence of the clause is equal to
        // number of closed brackets after the occurrence of the clause,
        // it means that ORDER BY is included in the query being checked
        while ($matchesCount > 0) {
            $orderByPos          = $matches[0][--$matchesCount][1];
            $openBracketsCount   = substr_count($query, '(', $orderByPos);
            $closedBracketsCount = substr_count($query, ')', $orderByPos);
            if ($openBracketsCount === $closedBracketsCount) {
                return false;
            }
        }

        return true;
    }

    public function createSchemaManager(Connection $connection): SQLServerSchemaManager
    {
        return new SQLServerSchemaManager($connection, $this);
    }
}
