<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnLengthRequired;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLServer\SQL\Builder\SQLServerSelectSQLBuilder;
use Doctrine\DBAL\Platforms\SQLServer\SQLServerDerivedObjectProvider;
use Doctrine\DBAL\Platforms\SQLServer\SQLServerMetadataProvider;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Exception\UnspecifiedConstraintName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use Override;

use function array_map;
use function array_merge;
use function implode;
use function is_array;
use function is_bool;
use function is_numeric;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_replace;
use function substr_count;

use const PREG_OFFSET_CAPTURE;

/**
 * Provides the behavior, features and SQL dialect of the Microsoft SQL Server database platform
 * of the oldest supported version.
 *
 * @phpstan-import-type ColumnProperties from Column
 */
class SQLServerPlatform extends AbstractPlatform
{
    /** @internal Should be used only from within the {@see AbstractSchemaManager} class hierarchy. */
    public const string OPTION_DEFAULT_CONSTRAINT_NAME = 'default_constraint_name';

    public function __construct()
    {
        parent::__construct(UnquotedIdentifierFolding::NONE);
    }

    #[Override]
    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return new SQLServerSelectSQLBuilder($this);
    }

    #[Override]
    public function getCurrentDateSQL(): string
    {
        return $this->getConvertExpression('date', 'GETDATE()');
    }

    #[Override]
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

    #[Override]
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

    #[Override]
    public function getDateDiffExpression(string $date1, string $date2): string
    {
        return 'DATEDIFF(day, ' . $date2 . ',' . $date1 . ')';
    }

    /** Microsoft SQL Server supports this through AUTO_INCREMENT columns. */
    #[Override]
    public function supportsIdentityColumns(): bool
    {
        return true;
    }

    #[Override]
    public function supportsReleaseSavepoints(): bool
    {
        return false;
    }

    #[Override]
    public function supportsSchemas(): bool
    {
        return true;
    }

    #[Override]
    protected function supportsColumnCollation(): bool
    {
        return true;
    }

    #[Override]
    public function supportsSequences(): bool
    {
        return true;
    }

    #[Override]
    public function getAlterSequenceSQL(Sequence $sequence): string
    {
        return 'ALTER SEQUENCE ' . $sequence->getObjectName()->toSQL($this) .
            ' INCREMENT BY ' . $sequence->getAllocationSize();
    }

    #[Override]
    public function getCreateSequenceSQL(Sequence $sequence): string
    {
        return 'CREATE SEQUENCE ' . $sequence->getObjectName()->toSQL($this) .
            ' START WITH ' . $sequence->getInitialValue() .
            ' INCREMENT BY ' . $sequence->getAllocationSize() .
            ' MINVALUE ' . $sequence->getInitialValue();
    }

    #[Override]
    public function getSequenceNextValSQL(string $sequenceName): string
    {
        $parsedName = $this->parseUnqualifiedName($sequenceName);

        return sprintf('SELECT NEXT VALUE FOR %s', $parsedName->toSQL($this));
    }

    #[Override]
    public function getDropForeignKeySQL(string $constraintName, string $tableName): string
    {
        return $this->getDropConstraintSQL($constraintName, $tableName);
    }

    #[Override]
    public function getDropIndexSQL(string $indexName, string $tableName): string
    {
        $parsedIndexName = $this->parseUnqualifiedName($indexName);
        $parsedTableName = $this->parseOptionallyQualifiedName($tableName);

        return 'DROP INDEX ' . $parsedIndexName->toSQL($this) . ' ON ' . $parsedTableName->toSQL($this);
    }

    #[Override]
    protected function _getCreateTableSQL(OptionallyQualifiedName $tableName, array $columns, array $parameters): array
    {
        $defaultConstraintsSql = [];
        $commentsSql           = [];

        $tableNameSQL = $tableName->toSQL($this);
        $tableComment = $parameters['comment'] ?? null;
        if ($tableComment !== null) {
            $commentsSql[] = $this->getCommentOnTableSQL($tableNameSQL, $tableComment);
        }

        foreach ($columns as $column) {
            if (isset($column['default'])) {
                $defaultConstraintsSql[] = sprintf(
                    'ALTER TABLE %s ADD %s',
                    $tableNameSQL,
                    $this->getDefaultConstraintDeclarationSQL($column),
                );
            }

            if ($column['comment'] !== '') {
                $commentsSql[] = $this->getCreateColumnCommentSQL($tableName, $column['name'], $column['comment']);
            }
        }

        $elements = [];

        foreach ($columns as $column) {
            $elements[] = $this->getColumnDeclarationSQL($column);
        }

        foreach ($parameters['uniqueConstraints'] as $definition) {
            $elements[] = $this->getUniqueConstraintDeclarationSQL($definition);
        }

        if (isset($parameters['primaryKey'])) {
            $elements[] = $this->getPrimaryKeyConstraintDeclarationSQL($parameters['primaryKey']);
        }

        $elements = array_merge($elements, $this->getCheckDeclarationSQL($columns));

        $query = 'CREATE TABLE ' . $tableName->toSQL($this) . ' (' . implode(', ', $elements) . ')';

        $sql = [$query];

        foreach ($parameters['indexes'] as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $tableNameSQL);
        }

        foreach ($parameters['foreignKeys'] as $definition) {
            $sql[] = $this->getCreateForeignKeySQL($definition, $tableNameSQL);
        }

        return array_merge($sql, $commentsSql, $defaultConstraintsSql);
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
     * @link https://learn.microsoft.com/en-us/sql/relational-databases/system-stored-procedures/sp-addextendedproperty-transact-sql
     *
     * @param OptionallyQualifiedName $tableName  The name of the table to which the column belongs.
     * @param UnqualifiedName         $columnName The name of the column to create the comment for.
     * @param string                  $comment    The column's comment.
     */
    private function getCreateColumnCommentSQL(
        OptionallyQualifiedName $tableName,
        UnqualifiedName $columnName,
        string $comment,
    ): string {
        return $this->getExecSQL(
            'sp_addextendedproperty',
            $this->quoteNationalStringLiteral('MS_Description'),
            $this->quoteNationalStringLiteral($comment),
            ...$this->getArgumentsForExtendedProperties([
                ...$this->getExtendedPropertiesForTable($tableName),
                'COLUMN' => $this->quoteStringLiteral(
                    $columnName->getIdentifier()->toNormalizedValue(
                        $this->getUnquotedIdentifierFolding(),
                    ),
                ),
            ]),
        );
    }

    /**
     * Returns the SQL snippet for declaring a default constraint.
     *
     * @param ColumnProperties $column
     */
    private function getDefaultConstraintDeclarationSQL(array $column): string
    {
        if (! isset($column['default'])) {
            throw new InvalidArgumentException('Incomplete column definition. "default" required.');
        }

        return $this->getDefaultValueDeclarationSQL($column) . ' FOR ' . $column['name']->toSQL($this);
    }

    #[Override]
    public function getCreateIndexSQL(Index $index, string $tableName): string
    {
        $this->ensureIndexHasNoColumnLengths($index);
        $this->ensureIndexIsNotFulltext($index);
        $this->ensureIndexIsNotSpatial($index);
        $this->ensureIndexIsNotPartial($index);

        if ($index->getType() === IndexType::UNIQUE) {
            // Redefine the index as a partial index that excludes NULL values. This compensates for SQL Server's
            // handling of NULLs, where they are considered equal in unique indexes. According to the SQL standard,
            // NULL values should be treated as distinct.
            $columnPredicates = [];

            foreach ($index->getIndexedColumns() as $indexedColumn) {
                $columnPredicates[] = $indexedColumn->getColumnName()->toSQL($this) . ' IS NOT NULL';
            }

            $index = $index->edit()
                ->setPredicate(implode(' AND ', $columnPredicates))
                ->create();
        }

        return parent::getCreateIndexSQL($index, $tableName);
    }

    #[Override]
    public function getAlterTableSQL(TableDiff $diff): array
    {
        $queryParts  = [];
        $sql         = [];
        $commentsSql = [];

        $table     = $diff->getOldTable();
        $tableName = $table->getObjectName();

        $tableNameSQL = $tableName->toSQL($this);

        foreach ($diff->getDroppedForeignKeyConstraintNames() as $constraintName) {
            $sql[] = $this->getDropForeignKeySQL($constraintName->toSQL($this), $tableNameSQL);
        }

        foreach ($diff->getDroppedUniqueConstraintNames() as $constraintName) {
            $sql[] = $this->getDropUniqueConstraintSQL($constraintName->toSQL($this), $tableNameSQL);
        }

        foreach ($diff->getDroppedIndexes() as $index) {
            $sql[] = $this->getDropIndexSQL($index->getObjectName()->toSQL($this), $tableNameSQL);
        }

        $droppedPrimaryKeyConstraint = $diff->getDroppedPrimaryKeyConstraint();

        if ($droppedPrimaryKeyConstraint !== null) {
            $constraintName = $droppedPrimaryKeyConstraint->getObjectName();

            if ($constraintName === null) {
                throw UnspecifiedConstraintName::forPrimaryKeyConstraint();
            }

            $sql[] = $this->getDropConstraintSQL($constraintName->toSQL($this), $tableNameSQL);
        }

        foreach ($diff->getAddedColumns() as $column) {
            $columnProperties = $column->toArray();

            $addColumnSql = 'ADD ' . $this->getColumnDeclarationSQL($columnProperties);

            if (isset($columnProperties['default'])) {
                $addColumnSql .= $this->getDefaultValueDeclarationSQL($columnProperties);
            }

            $queryParts[] = $addColumnSql;

            $comment = $column->getComment();

            if ($comment !== '') {
                $commentsSql[] = $this->getCreateColumnCommentSQL(
                    $tableName,
                    $column->getObjectName(),
                    $comment,
                );
            }
        }

        foreach ($diff->getDroppedColumns() as $column) {
            if ($column->getDefault() !== null) {
                $queryParts[] = $this->getAlterTableDropDefaultConstraintClause($column);
            }

            $queryParts[] = 'DROP COLUMN ' . $column->getObjectName()->toSQL($this);
        }

        foreach ($diff->getChangedColumns() as $columnDiff) {
            $newColumn   = $columnDiff->getNewColumn();
            $oldColumn   = $columnDiff->getOldColumn();
            $nameChanged = $columnDiff->hasNameChanged();

            if ($nameChanged) {
                $sql = array_merge(
                    $sql,
                    $this->getRenameColumnSQL(
                        $tableNameSQL,
                        $oldColumn->getObjectName()->toSQL($this),
                        $newColumn->getObjectName()
                            ->getIdentifier()
                            ->toNormalizedValue(
                                $this->getUnquotedIdentifierFolding(),
                            ),
                    ),
                );
            }

            $newComment    = $newColumn->getComment();
            $hasNewComment = $newComment !== '';

            $oldComment    = $oldColumn->getComment();
            $hasOldComment = $oldComment !== '';

            if ($hasOldComment && $hasNewComment && $oldComment !== $newComment) {
                $commentsSql[] = $this->getAlterColumnCommentSQL(
                    $tableName,
                    $newColumn->getObjectName(),
                    $newComment,
                );
            } elseif ($hasOldComment && ! $hasNewComment) {
                $commentsSql[] = $this->getDropColumnCommentSQL(
                    $tableName,
                    $newColumn->getObjectName(),
                );
            } elseif (! $hasOldComment && $hasNewComment) {
                $commentsSql[] = $this->getCreateColumnCommentSQL(
                    $tableName,
                    $newColumn->getObjectName(),
                    $newComment,
                );
            }

            $newDeclarationSQL     = $this->getColumnDeclarationSQL($newColumn->toArray());
            $oldDeclarationSQL     = $this->getColumnDeclarationSQL($oldColumn->toArray());
            $declarationSQLChanged = $newDeclarationSQL !== $oldDeclarationSQL;

            $defaultChanged = $columnDiff->hasDefaultChanged();

            if (! $declarationSQLChanged && ! $defaultChanged && ! $nameChanged) {
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
                $newColumn->getDefault() !== null
                && ($requireDropDefaultConstraint || $defaultChanged)
            ) {
                $queryParts[] = $this->getAlterTableAddDefaultConstraintClause($newColumn);
            }
        }

        $addedPrimaryKeyConstraint = $diff->getAddedPrimaryKeyConstraint();

        if ($addedPrimaryKeyConstraint !== null) {
            $queryParts[] = 'ADD ' . $this->getPrimaryKeyConstraintDeclarationSQL($addedPrimaryKeyConstraint);
        }

        foreach ($queryParts as $query) {
            $sql[] = sprintf('ALTER TABLE %s %s', $tableName->toSQL($this), $query);
        }

        foreach ($diff->getAddedForeignKeys() as $foreignKey) {
            $sql[] = $this->getCreateForeignKeySQL($foreignKey, $tableNameSQL);
        }

        foreach ($diff->getAddedUniqueConstraints() as $uniqueConstraint) {
            $sql[] = $this->getCreateUniqueConstraintSQL($uniqueConstraint, $tableNameSQL);
        }

        foreach ($diff->getAddedIndexes() as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $tableNameSQL);
        }

        foreach ($diff->getIndexRenames() as $rename) {
            $sql[] = $this->getRenameSQL(
                $tableNameSQL . '.' . $rename->getOldName()->toSQL($this),
                $rename->getNewIndex()->getObjectName()
                    ->getIdentifier()
                    ->toNormalizedValue(
                        $this->getUnquotedIdentifierFolding(),
                    ),
                'INDEX',
            );
        }

        return array_merge($sql, $commentsSql);
    }

    #[Override]
    public function getRenameTableSQL(string $oldName, string $newName): string
    {
        $parsedOldName = $this->parseOptionallyQualifiedName($oldName);

        return $this->getRenameSQL($parsedOldName->toSQL($this), $newName);
    }

    /**
     * Returns the SQL clause for adding a default constraint in an ALTER TABLE statement.
     *
     * @param Column $column The column to generate the clause for.
     */
    private function getAlterTableAddDefaultConstraintClause(Column $column): string
    {
        $columnDef         = $column->toArray();
        $columnDef['name'] = $column->getObjectName();

        return 'ADD' . $this->getDefaultConstraintDeclarationSQL($columnDef);
    }

    /**
     * Returns the SQL clause for dropping an existing default constraint in an ALTER TABLE statement.
     */
    private function getAlterTableDropDefaultConstraintClause(Column $column): string
    {
        $constraintName = $column->getDefaultConstraintName();

        if ($constraintName === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Column %s was not properly introspected as it has a default value'
                        . ' but does not have the default constraint name.',
                    $column->getObjectName()->toString(),
                ),
            );
        }

        return 'DROP CONSTRAINT ' . $this->quoteSingleIdentifier($constraintName);
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
     * @link https://learn.microsoft.com/en-us/sql/relational-databases/system-stored-procedures/sp-updateextendedproperty-transact-sql
     *
     * @param OptionallyQualifiedName $tableName  The name of the table to which the column belongs.
     * @param UnqualifiedName         $columnName The name of the column to alter the comment for.
     * @param string                  $comment    The column's comment.
     */
    private function getAlterColumnCommentSQL(
        OptionallyQualifiedName $tableName,
        UnqualifiedName $columnName,
        string $comment,
    ): string {
        return $this->getExecSQL(
            'sp_updateextendedproperty',
            $this->quoteNationalStringLiteral('MS_Description'),
            $this->quoteNationalStringLiteral($comment),
            ...$this->getArgumentsForExtendedProperties([
                ...$this->getExtendedPropertiesForTable($tableName),
                'COLUMN' => $this->quoteStringLiteral(
                    $columnName->getIdentifier()->toNormalizedValue(
                        $this->getUnquotedIdentifierFolding(),
                    ),
                ),
            ]),
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
     * @param OptionallyQualifiedName $tableName  The name of the table to which the column belongs.
     * @param UnqualifiedName         $columnName The name of the column to drop the comment for.
     *
     * https://learn.microsoft.com/en-us/sql/relational-databases/system-stored-procedures/sp-dropextendedproperty-transact-sql
     */
    private function getDropColumnCommentSQL(OptionallyQualifiedName $tableName, UnqualifiedName $columnName): string
    {
        return $this->getExecSQL(
            'sp_dropextendedproperty',
            $this->quoteNationalStringLiteral('MS_Description'),
            ...$this->getArgumentsForExtendedProperties([
                ...$this->getExtendedPropertiesForTable($tableName),
                'COLUMN' => $this->quoteStringLiteral(
                    $columnName->getIdentifier()->toNormalizedValue(
                        $this->getUnquotedIdentifierFolding(),
                    ),
                ),
            ]),
        );
    }

    #[Override]
    protected function getRenameColumnSQL(string $tableName, string $oldColumnName, string $newColumnName): array
    {
        $parsedTableName     = $this->parseOptionallyQualifiedName($tableName);
        $parsedOldColumnName = $this->parseUnqualifiedName($oldColumnName);

        return [
            $this->getRenameSQL(
                $parsedTableName->toSQL($this) . '.' . $parsedOldColumnName->toSQL($this),
                $newColumnName,
            ),
        ];
    }

    /**
     * Returns the SQL statement that will execute sp_rename with the given arguments.
     *
     * @param string ...$arguments The literal values of the arguments to pass to the <code>sp_rename</code> procedure.
     */
    private function getRenameSQL(string ...$arguments): string
    {
        return $this->getExecSQL('sp_rename', ...array_map(function (string $argument): string {
            return $this->quoteNationalStringLiteral($argument);
        }, $arguments));
    }

    /**
     * Returns the SQL statement that will execute the given stored procedure with the given arguments.
     *
     * @param string $procedureName The name of the stored procedure to execute.
     * @param string ...$arguments  The SQL fragments representing the arguments to pass to the stored procedure.
     */
    private function getExecSQL(string $procedureName, string ...$arguments): string
    {
        return 'EXEC ' . $this->quoteSingleIdentifier($procedureName) . ' ' . implode(', ', $arguments);
    }

    private function quoteNationalStringLiteral(string $value): string
    {
        return 'N' . $this->quoteStringLiteral($value);
    }

    /**
     * Returns the stored procedure arguments for the extended properties.
     *
     * The keys of the properties array are property name literals, and the values are SQL fragments representing the
     * values.
     *
     * @param array<string,string> $properties
     *
     * @return list<string>
     */
    private function getArgumentsForExtendedProperties(array $properties): array
    {
        $arguments = [];

        foreach ($properties as $name => $value) {
            $arguments[] = $this->quoteNationalStringLiteral($name);
            $arguments[] = $value;
        }

        return $arguments;
    }

    /**
     * Returns extended properties representing the given table.
     *
     * The keys of the returned array are property name literals, and the values are SQL fragments representing the
     * values.
     *
     * @return array<string,string>
     */
    private function getExtendedPropertiesForTable(OptionallyQualifiedName $tableName): array
    {
        $folding = $this->getUnquotedIdentifierFolding();

        $unqualifiedName = $tableName->getUnqualifiedName()
            ->toNormalizedValue($folding);

        $qualifier = $tableName->getQualifier()
            ?->toNormalizedValue($folding)
            ?? 'dbo';

        return [
            'SCHEMA' => $this->quoteStringLiteral($qualifier),
            'TABLE' => $this->quoteStringLiteral($unqualifiedName),
        ];
    }

    #[Override]
    public function getEmptyIdentityInsertSQL(string $quotedTableName, string $quotedIdentifierColumnName): string
    {
        return 'INSERT INTO ' . $quotedTableName . ' DEFAULT VALUES';
    }

    #[Override]
    public function getLocateExpression(string $string, string $substring, ?string $start = null): string
    {
        if ($start === null) {
            return sprintf('CHARINDEX(%s, %s)', $substring, $string);
        }

        return sprintf('CHARINDEX(%s, %s, %s)', $substring, $string, $start);
    }

    #[Override]
    public function getModExpression(string $dividend, string $divisor): string
    {
        return $dividend . ' % ' . $divisor;
    }

    #[Override]
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

    #[Override]
    public function getConcatExpression(string ...$string): string
    {
        return sprintf('CONCAT(%s)', implode(', ', $string));
    }

    #[Override]
    public function getSubstringExpression(string $string, string $start, ?string $length = null): string
    {
        if ($length === null) {
            return sprintf('SUBSTRING(%s, %s, LEN(%s) - %s + 1)', $string, $start, $string, $start);
        }

        return sprintf('SUBSTRING(%s, %s, %s)', $string, $start, $length);
    }

    #[Override]
    public function getLengthExpression(string $string): string
    {
        return 'LEN(' . $string . ')';
    }

    #[Override]
    public function getCurrentDatabaseExpression(): string
    {
        return 'DB_NAME()';
    }

    #[Override]
    public function getSetTransactionIsolationSQL(TransactionIsolationLevel $level): string
    {
        return 'SET TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    #[Override]
    public function getIntegerTypeDeclarationSQL(array $column): string
    {
        return 'INT' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    #[Override]
    public function getBigIntTypeDeclarationSQL(array $column): string
    {
        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    #[Override]
    public function getSmallIntTypeDeclarationSQL(array $column): string
    {
        return 'SMALLINT' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    #[Override]
    public function getGuidTypeDeclarationSQL(array $column): string
    {
        return 'UNIQUEIDENTIFIER';
    }

    #[Override]
    public function getDateTimeTzTypeDeclarationSQL(array $column): string
    {
        return 'DATETIMEOFFSET(6)';
    }

    #[Override]
    protected function getCharTypeDeclarationSQLSnippet(?int $length): string
    {
        $sql = 'NCHAR';

        if ($length !== null) {
            $sql .= sprintf('(%d)', $length);
        }

        return $sql;
    }

    #[Override]
    protected function getVarcharTypeDeclarationSQLSnippet(?int $length): string
    {
        if ($length === null) {
            throw ColumnLengthRequired::new($this, 'NVARCHAR');
        }

        return sprintf('NVARCHAR(%d)', $length);
    }

    #[Override]
    public function getAsciiStringTypeDeclarationSQL(array $column): string
    {
        $length = $column['length'] ?? null;

        if (empty($column['fixed'])) {
            return parent::getVarcharTypeDeclarationSQLSnippet($length);
        }

        return parent::getCharTypeDeclarationSQLSnippet($length);
    }

    #[Override]
    public function getClobTypeDeclarationSQL(array $column): string
    {
        return 'VARCHAR(MAX)';
    }

    #[Override]
    protected function _getCommonIntegerTypeDeclarationSQL(array $column): string
    {
        if (! empty($column['autoincrement'])) {
            return ' IDENTITY';
        }

        return '';
    }

    #[Override]
    public function getDateTimeTypeDeclarationSQL(array $column): string
    {
        // 3 - microseconds precision length
        // http://msdn.microsoft.com/en-us/library/ms187819.aspx
        return 'DATETIME2(6)';
    }

    #[Override]
    public function getDateTypeDeclarationSQL(array $column): string
    {
        return 'DATE';
    }

    #[Override]
    public function getTimeTypeDeclarationSQL(array $column): string
    {
        return 'TIME(0)';
    }

    #[Override]
    public function getBooleanTypeDeclarationSQL(array $column): string
    {
        return 'BIT';
    }

    #[Override]
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

    #[Override]
    public function convertBooleans(mixed $item): mixed
    {
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                if (is_bool($value) || is_numeric($value)) {
                    $item[$key] = (int) (bool) $value;
                }
            }
        } elseif (is_bool($item) || is_numeric($item)) {
            $item = (int) (bool) $item;
        }

        return $item;
    }

    #[Override]
    public function getCreateTemporaryTableSnippetSQL(): string
    {
        return 'CREATE TABLE';
    }

    #[Override]
    public function getTemporaryTableName(string $tableName): string
    {
        return '#' . $tableName;
    }

    #[Override]
    public function getDateTimeFormatString(): string
    {
        return 'Y-m-d H:i:s.u';
    }

    #[Override]
    public function getDateFormatString(): string
    {
        return 'Y-m-d';
    }

    #[Override]
    public function getTimeFormatString(): string
    {
        return 'H:i:s';
    }

    #[Override]
    public function getDateTimeTzFormatString(): string
    {
        return 'Y-m-d H:i:s.u P';
    }

    #[Override]
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
            'real'             => Types::SMALLFLOAT,
            'smalldatetime'    => Types::DATETIME_MUTABLE,
            'smallint'         => Types::SMALLINT,
            'smallmoney'       => Types::INTEGER,
            'sysname'          => Types::STRING,
            'text'             => Types::TEXT,
            'time'             => Types::TIME_MUTABLE,
            'tinyint'          => Types::SMALLINT,
            'uniqueidentifier' => Types::GUID,
            'varbinary'        => Types::BINARY,
            'varchar'          => Types::STRING,
            'xml'              => Types::TEXT,
        ];
    }

    #[Override]
    public function createSavePoint(string $savepoint): string
    {
        return 'SAVE TRANSACTION ' . $savepoint;
    }

    #[Override]
    public function rollbackSavePoint(string $savepoint): string
    {
        return 'ROLLBACK TRANSACTION ' . $savepoint;
    }

    #[Override]
    protected function getForeignKeyReferentialActionSQL(ReferentialAction $action): string
    {
        if ($action === ReferentialAction::RESTRICT) {
            throw new InvalidArgumentException(
                sprintf('Unsupported foreign key action "%s".', $action->value),
            );
        }

        return parent::getForeignKeyReferentialActionSQL($action);
    }

    #[Override]
    public function appendLockHint(string $fromClause, LockMode $lockMode): string
    {
        return match ($lockMode) {
            LockMode::NONE,
            LockMode::OPTIMISTIC => $fromClause,
            LockMode::PESSIMISTIC_READ => $fromClause . ' WITH (HOLDLOCK, ROWLOCK)',
            LockMode::PESSIMISTIC_WRITE => $fromClause . ' WITH (UPDLOCK, ROWLOCK)',
        };
    }

    #[Override]
    public function quoteSingleIdentifier(string $identifier): string
    {
        return '[' . str_replace(']', ']]', $identifier) . ']';
    }

    #[Override]
    public function getTruncateTableSQL(string $tableName, bool $cascade = false): string
    {
        $parsedName = $this->parseOptionallyQualifiedName($tableName);

        return sprintf('TRUNCATE TABLE %s', $parsedName->toSQL($this));
    }

    #[Override]
    public function getBlobTypeDeclarationSQL(array $column): string
    {
        return 'VARBINARY(MAX)';
    }

    #[Override]
    protected function getColumnDeclarationSQL(array $column): string
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

        return $column['name']->toSQL($this) . ' ' . $declaration;
    }

    /**
     * SQL Server does not support quoting collation identifiers.
     */
    #[Override]
    protected function getColumnCollationDeclarationSQL(string $collation): string
    {
        return 'COLLATE ' . $collation;
    }

    #[Override]
    public function columnsEqual(Column $column1, Column $column2): bool
    {
        if (! parent::columnsEqual($column1, $column2)) {
            return false;
        }

        return $this->getDefaultValueDeclarationSQL($column1->toArray())
            === $this->getDefaultValueDeclarationSQL($column2->toArray());
    }

    /**
     * The <code>[</code> character is used in SQL Server's extended pattern syntax to define character ranges or sets.
     *
     * @link https://learn.microsoft.com/en-us/sql/t-sql/language-elements/like-transact-sql#pattern
     */
    #[Override]
    protected function getLikeWildcardCharacters(): string
    {
        return parent::getLikeWildcardCharacters() . '[';
    }

    /** @link https://learn.microsoft.com/en-us/sql/relational-databases/system-stored-procedures/sp-addextendedproperty-transact-sql */
    private function getCommentOnTableSQL(string $tableName, string $comment): string
    {
        $parsedName = $this->parseOptionallyQualifiedName($tableName);

        return $this->getExecSQL(
            'sp_addextendedproperty',
            $this->quoteNationalStringLiteral('MS_Description'),
            $this->quoteNationalStringLiteral($comment),
            ...$this->getArgumentsForExtendedProperties(
                $this->getExtendedPropertiesForTable($parsedName),
            ),
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

    #[Override]
    public function createMetadataProvider(Connection $connection): SQLServerMetadataProvider
    {
        return new SQLServerMetadataProvider($connection, $this);
    }

    #[Override]
    public function createDerivedObjectProvider(): SQLServerDerivedObjectProvider
    {
        return new SQLServerDerivedObjectProvider();
    }

    #[Override]
    public function createSchemaManager(Connection $connection): SQLServerSchemaManager
    {
        return new SQLServerSchemaManager($connection, $this);
    }
}
