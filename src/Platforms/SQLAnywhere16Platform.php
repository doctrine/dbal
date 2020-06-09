<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Constraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use InvalidArgumentException;
use UnexpectedValueException;

use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function explode;
use function get_class;
use function implode;
use function in_array;
use function is_string;
use function preg_match;
use function sprintf;
use function strlen;
use function strpos;
use function strtoupper;
use function substr;

/**
 * Provides the behavior, features and SQL dialect of the SAP Sybase SQL Anywhere 16 database platform.
 */
class SQLAnywhere16Platform extends AbstractPlatform
{
    public const FOREIGN_KEY_MATCH_SIMPLE        = 1;
    public const FOREIGN_KEY_MATCH_FULL          = 2;
    public const FOREIGN_KEY_MATCH_SIMPLE_UNIQUE = 129;
    public const FOREIGN_KEY_MATCH_FULL_UNIQUE   = 130;

    public function appendLockHint(string $fromClause, ?int $lockMode): string
    {
        switch (true) {
            case $lockMode === LockMode::NONE:
                return $fromClause . ' WITH (NOLOCK)';

            case $lockMode === LockMode::PESSIMISTIC_READ:
                return $fromClause . ' WITH (UPDLOCK)';

            case $lockMode === LockMode::PESSIMISTIC_WRITE:
                return $fromClause . ' WITH (XLOCK)';

            default:
                return $fromClause;
        }
    }

    /**
     * {@inheritdoc}
     *
     * SQL Anywhere supports a maximum length of 128 bytes for identifiers.
     */
    public function fixSchemaElementName(string $schemaElementName): string
    {
        $maxIdentifierLength = $this->getMaxIdentifierLength();

        if (strlen($schemaElementName) > $maxIdentifierLength) {
            return substr($schemaElementName, 0, $maxIdentifierLength);
        }

        return $schemaElementName;
    }

    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        $query = '';

        if ($foreignKey->hasOption('match')) {
            $query = ' MATCH ' . $this->getForeignKeyMatchClauseSQL($foreignKey->getOption('match'));
        }

        $query .= parent::getAdvancedForeignKeyOptionsSQL($foreignKey);

        if ($foreignKey->hasOption('check_on_commit') && (bool) $foreignKey->getOption('check_on_commit')) {
            $query .= ' CHECK ON COMMIT';
        }

        if ($foreignKey->hasOption('clustered') && (bool) $foreignKey->getOption('clustered')) {
            $query .= ' CLUSTERED';
        }

        if ($foreignKey->hasOption('for_olap_workload') && (bool) $foreignKey->getOption('for_olap_workload')) {
            $query .= ' FOR OLAP WORKLOAD';
        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function getAlterTableSQL(TableDiff $diff): array
    {
        $sql          = [];
        $columnSql    = [];
        $commentsSQL  = [];
        $tableSql     = [];
        $alterClauses = [];

        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $alterClauses[] = $this->getAlterTableAddColumnClause($column);

            $comment = $this->getColumnComment($column);

            if ($comment === '') {
                continue;
            }

            $commentsSQL[] = $this->getCommentOnColumnSQL(
                $diff->getName($this)->getQuotedName($this),
                $column->getQuotedName($this),
                $comment
            );
        }

        foreach ($diff->removedColumns as $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $alterClauses[] = $this->getAlterTableRemoveColumnClause($column);
        }

        foreach ($diff->changedColumns as $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            $alterClause = $this->getAlterTableChangeColumnClause($columnDiff);

            if ($alterClause !== null) {
                $alterClauses[] = $alterClause;
            }

            if (! $columnDiff->hasChanged('comment')) {
                continue;
            }

            $column = $columnDiff->column;

            $commentsSQL[] = $this->getCommentOnColumnSQL(
                $diff->getName($this)->getQuotedName($this),
                $column->getQuotedName($this),
                $this->getColumnComment($column)
            );
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $sql[] = $this->getAlterTableClause($diff->getName($this)) . ' ' .
                $this->getAlterTableRenameColumnClause($oldColumnName, $column);
        }

        if (! $this->onSchemaAlterTable($diff, $tableSql)) {
            if (! empty($alterClauses)) {
                $sql[] = $this->getAlterTableClause($diff->getName($this)) . ' ' . implode(', ', $alterClauses);
            }

            $sql = array_merge($sql, $commentsSQL);

            $newName = $diff->getNewName();

            if ($newName !== null) {
                $sql[] = $this->getAlterTableClause($diff->getName($this)) . ' ' .
                    $this->getAlterTableRenameTableClause($newName);
            }

            $sql = array_merge(
                $this->getPreAlterTableIndexForeignKeySQL($diff),
                $sql,
                $this->getPostAlterTableIndexForeignKeySQL($diff)
            );
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * Returns the SQL clause for creating a column in a table alteration.
     *
     * @param Column $column The column to add.
     */
    protected function getAlterTableAddColumnClause(Column $column): string
    {
        return 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
    }

    /**
     * Returns the SQL clause for altering a table.
     *
     * @param Identifier $tableName The quoted name of the table to alter.
     */
    protected function getAlterTableClause(Identifier $tableName): string
    {
        return 'ALTER TABLE ' . $tableName->getQuotedName($this);
    }

    /**
     * Returns the SQL clause for dropping a column in a table alteration.
     *
     * @param Column $column The column to drop.
     */
    protected function getAlterTableRemoveColumnClause(Column $column): string
    {
        return 'DROP ' . $column->getQuotedName($this);
    }

    /**
     * Returns the SQL clause for renaming a column in a table alteration.
     *
     * @param string $oldColumnName The quoted name of the column to rename.
     * @param Column $column        The column to rename to.
     */
    protected function getAlterTableRenameColumnClause(string $oldColumnName, Column $column): string
    {
        $oldColumnName = new Identifier($oldColumnName);

        return 'RENAME ' . $oldColumnName->getQuotedName($this) . ' TO ' . $column->getQuotedName($this);
    }

    /**
     * Returns the SQL clause for renaming a table in a table alteration.
     *
     * @param Identifier $newTableName The quoted name of the table to rename to.
     */
    protected function getAlterTableRenameTableClause(Identifier $newTableName): string
    {
        return 'RENAME ' . $newTableName->getQuotedName($this);
    }

    /**
     * Returns the SQL clause for altering a column in a table alteration.
     *
     * This method returns null in case that only the column comment has changed.
     * Changes in column comments have to be handled differently.
     *
     * @param ColumnDiff $columnDiff The diff of the column to alter.
     */
    protected function getAlterTableChangeColumnClause(ColumnDiff $columnDiff): ?string
    {
        $column = $columnDiff->column;

        // Do not return alter clause if only comment has changed.
        if (! ($columnDiff->hasChanged('comment') && count($columnDiff->changedProperties) === 1)) {
            $columnAlterationClause = 'ALTER ' .
                $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());

            if ($columnDiff->hasChanged('default') && $column->getDefault() === null) {
                $columnAlterationClause .= ', ALTER ' . $column->getQuotedName($this) . ' DROP DEFAULT';
            }

            return $columnAlterationClause;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getBigIntTypeDeclarationSQL(array $columnDef): string
    {
        $columnDef['integer_type'] = 'BIGINT';

        return $this->_getCommonIntegerTypeDeclarationSQL($columnDef);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlobTypeDeclarationSQL(array $field): string
    {
        return 'LONG BINARY';
    }

    /**
     * {@inheritdoc}
     *
     * BIT type columns require an explicit NULL declaration
     * in SQL Anywhere if they shall be nullable.
     * Otherwise by just omitting the NOT NULL clause,
     * SQL Anywhere will declare them NOT NULL nonetheless.
     */
    public function getBooleanTypeDeclarationSQL(array $columnDef): string
    {
        $nullClause = isset($columnDef['notnull']) && (bool) $columnDef['notnull'] === false ? ' NULL' : '';

        return 'BIT' . $nullClause;
    }

    /**
     * {@inheritdoc}
     */
    public function getClobTypeDeclarationSQL(array $field): string
    {
        return 'TEXT';
    }

    public function getConcatExpression(string ...$string): string
    {
        return 'STRING(' . implode(', ', $string) . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getCreateConstraintSQL(Constraint $constraint, $table): string
    {
        if ($constraint instanceof ForeignKeyConstraint) {
            return $this->getCreateForeignKeySQL($constraint, $table);
        }

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        return 'ALTER TABLE ' . $table .
               ' ADD ' . $this->getTableConstraintDeclarationSQL($constraint, $constraint->getQuotedName($this));
    }

    public function getCreateDatabaseSQL(string $database): string
    {
        $database = new Identifier($database);

        return "CREATE DATABASE '" . $database->getName() . "'";
    }

    /**
     * {@inheritdoc}
     *
     * Appends SQL Anywhere specific flags if given.
     */
    public function getCreateIndexSQL(Index $index, $table): string
    {
        return parent::getCreateIndexSQL($index, $table) . $this->getAdvancedIndexOptionsSQL($index);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatePrimaryKeySQL(Index $index, $table): string
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        return 'ALTER TABLE ' . $table . ' ADD ' . $this->getPrimaryKeyDeclarationSQL($index);
    }

    public function getCreateTemporaryTableSnippetSQL(): string
    {
        return 'CREATE ' . $this->getTemporaryTableSQL() . ' TABLE';
    }

    public function getCreateViewSQL(string $name, string $sql): string
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    public function getCurrentDateSQL(): string
    {
        return 'CURRENT DATE';
    }

    public function getCurrentTimeSQL(): string
    {
        return 'CURRENT TIME';
    }

    public function getCurrentTimestampSQL(): string
    {
        return 'CURRENT TIMESTAMP';
    }

    protected function getDateArithmeticIntervalExpression(string $date, string $operator, string $interval, string $unit): string
    {
        $factorClause = '';

        if ($operator === '-') {
            $factorClause = '-1 * ';
        }

        return 'DATEADD(' . $unit . ', ' . $factorClause . $interval . ', ' . $date . ')';
    }

    public function getDateDiffExpression(string $date1, string $date2): string
    {
        return 'DATEDIFF(day, ' . $date2 . ', ' . $date1 . ')';
    }

    public function getDateTimeFormatString(): string
    {
        return 'Y-m-d H:i:s.u';
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return 'DATETIME';
    }

    public function getDateTimeTzFormatString(): string
    {
        return 'Y-m-d H:i:s.uP';
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return 'DATE';
    }

    public function getDefaultTransactionIsolationLevel(): int
    {
        return TransactionIsolationLevel::READ_UNCOMMITTED;
    }

    public function getDropDatabaseSQL(string $database): string
    {
        $database = new Identifier($database);

        return "DROP DATABASE '" . $database->getName() . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function getDropIndexSQL($index, $table = null): string
    {
        if ($index instanceof Index) {
            $index = $index->getQuotedName($this);
        }

        if (! is_string($index)) {
            throw new InvalidArgumentException(
                sprintf('SQLAnywherePlatform::getDropIndexSQL() expects $index parameter to be a string or an instance of %s.', Index::class)
            );
        }

        if (! isset($table)) {
            return 'DROP INDEX ' . $index;
        }

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        if (! is_string($table)) {
            throw new InvalidArgumentException(
                sprintf('SQLAnywherePlatform::getDropIndexSQL() expects $table parameter to be a string or an instance of %s.', Index::class)
            );
        }

        return 'DROP INDEX ' . $table . '.' . $index;
    }

    public function getDropViewSQL(string $name): string
    {
        return 'DROP VIEW ' . $name;
    }

    public function getForeignKeyBaseDeclarationSQL(ForeignKeyConstraint $foreignKey): string
    {
        $sql              = '';
        $foreignKeyName   = $foreignKey->getName();
        $localColumns     = $foreignKey->getQuotedLocalColumns($this);
        $foreignColumns   = $foreignKey->getQuotedForeignColumns($this);
        $foreignTableName = $foreignKey->getQuotedForeignTableName($this);

        if (! empty($foreignKeyName)) {
            $sql .= 'CONSTRAINT ' . $foreignKey->getQuotedName($this) . ' ';
        }

        if (empty($localColumns)) {
            throw new InvalidArgumentException('Incomplete definition. "local" required.');
        }

        if (empty($foreignColumns)) {
            throw new InvalidArgumentException('Incomplete definition. "foreign" required.');
        }

        if (empty($foreignTableName)) {
            throw new InvalidArgumentException('Incomplete definition. "foreignTable" required.');
        }

        if ($foreignKey->hasOption('notnull') && (bool) $foreignKey->getOption('notnull')) {
            $sql .= 'NOT NULL ';
        }

        return $sql .
            'FOREIGN KEY (' . $this->getColumnsFieldDeclarationListSQL($localColumns) . ') ' .
            'REFERENCES ' . $foreignKey->getQuotedForeignTableName($this) .
            ' (' . $this->getColumnsFieldDeclarationListSQL($foreignColumns) . ')';
    }

    /**
     * Returns foreign key MATCH clause for given type.
     *
     * @param int $type The foreign key match type
     *
     * @throws InvalidArgumentException If unknown match type given.
     */
    public function getForeignKeyMatchClauseSQL(int $type): string
    {
        switch ($type) {
            case self::FOREIGN_KEY_MATCH_SIMPLE:
                return 'SIMPLE';

            case self::FOREIGN_KEY_MATCH_FULL:
                return 'FULL';

            case self::FOREIGN_KEY_MATCH_SIMPLE_UNIQUE:
                return 'UNIQUE SIMPLE';

            case self::FOREIGN_KEY_MATCH_FULL_UNIQUE:
                return 'UNIQUE FULL';

            default:
                throw new InvalidArgumentException(sprintf('Invalid foreign key match type "%s".', $type));
        }
    }

    public function getForeignKeyReferentialActionSQL(string $action): string
    {
        // NO ACTION is not supported, therefore falling back to RESTRICT.
        if (strtoupper($action) === 'NO ACTION') {
            return 'RESTRICT';
        }

        return parent::getForeignKeyReferentialActionSQL($action);
    }

    public function getForUpdateSQL(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getGuidTypeDeclarationSQL(array $column): string
    {
        return 'UNIQUEIDENTIFIER';
    }

    public function getIndexDeclarationSQL(string $name, Index $index): string
    {
        // Index declaration in statements like CREATE TABLE is not supported.
        throw NotSupported::new(__METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function getIntegerTypeDeclarationSQL(array $columnDef): string
    {
        $columnDef['integer_type'] = 'INT';

        return $this->_getCommonIntegerTypeDeclarationSQL($columnDef);
    }

    public function getListDatabasesSQL(): string
    {
        return 'SELECT db_name(number) AS name FROM sa_db_list()';
    }

    public function getListTableColumnsSQL(string $table, ?string $database = null): string
    {
        $user = 'USER_NAME()';

        if (strpos($table, '.') !== false) {
            [$user, $table] = explode('.', $table);
            $user           = $this->quoteStringLiteral($user);
        }

        return sprintf(
            <<<'SQL'
SELECT    col.column_name,
          COALESCE(def.user_type_name, def.domain_name) AS 'type',
          def.declared_width AS 'length',
          def.scale,
          CHARINDEX('unsigned', def.domain_name) AS 'unsigned',
          IF col.nulls = 'Y' THEN 0 ELSE 1 ENDIF AS 'notnull',
          col."default",
          def.is_autoincrement AS 'autoincrement',
          rem.remarks AS 'comment'
FROM      sa_describe_query('SELECT * FROM "%s"') AS def
JOIN      SYS.SYSTABCOL AS col
ON        col.table_id = def.base_table_id AND col.column_id = def.base_column_id
LEFT JOIN SYS.SYSREMARK AS rem
ON        col.object_id = rem.object_id
WHERE     def.base_owner_name = %s
ORDER BY  def.base_column_id ASC
SQL
            ,
            $table,
            $user
        );
    }

    /**
     * {@inheritdoc}
     *
     * @todo Where is this used? Which information should be retrieved?
     */
    public function getListTableConstraintsSQL(string $table): string
    {
        $user = '';

        if (strpos($table, '.') !== false) {
            [$user, $table] = explode('.', $table);
            $user           = $this->quoteStringLiteral($user);
            $table          = $this->quoteStringLiteral($table);
        } else {
            $table = $this->quoteStringLiteral($table);
        }

        return sprintf(
            <<<'SQL'
SELECT con.*
FROM   SYS.SYSCONSTRAINT AS con
JOIN   SYS.SYSTAB AS tab ON con.table_object_id = tab.object_id
WHERE  tab.table_name = %s
AND    tab.creator = USER_ID(%s)
SQL
            ,
            $table,
            $user
        );
    }

    public function getListTableForeignKeysSQL(string $table, ?string $database = null): string
    {
        $user = '';

        if (strpos($table, '.') !== false) {
            [$user, $table] = explode('.', $table);
            $user           = $this->quoteStringLiteral($user);
            $table          = $this->quoteStringLiteral($table);
        } else {
            $table = $this->quoteStringLiteral($table);
        }

        return sprintf(
            <<<'SQL'
SELECT    fcol.column_name AS local_column,
          ptbl.table_name AS foreign_table,
          pcol.column_name AS foreign_column,
          idx.index_name,
          IF fk.nulls = 'N'
              THEN 1
              ELSE NULL
          ENDIF AS notnull,
          CASE ut.referential_action
              WHEN 'C' THEN 'CASCADE'
              WHEN 'D' THEN 'SET DEFAULT'
              WHEN 'N' THEN 'SET NULL'
              WHEN 'R' THEN 'RESTRICT'
              ELSE NULL
          END AS  on_update,
          CASE dt.referential_action
              WHEN 'C' THEN 'CASCADE'
              WHEN 'D' THEN 'SET DEFAULT'
              WHEN 'N' THEN 'SET NULL'
              WHEN 'R' THEN 'RESTRICT'
              ELSE NULL
          END AS on_delete,
          IF fk.check_on_commit = 'Y'
              THEN 1
              ELSE NULL
          ENDIF AS check_on_commit, -- check_on_commit flag
          IF ftbl.clustered_index_id = idx.index_id
              THEN 1
              ELSE NULL
          ENDIF AS 'clustered', -- clustered flag
          IF fk.match_type = 0
              THEN NULL
              ELSE fk.match_type
          ENDIF AS 'match', -- match option
          IF pidx.max_key_distance = 1
              THEN 1
              ELSE NULL
          ENDIF AS for_olap_workload -- for_olap_workload flag
FROM      SYS.SYSFKEY AS fk
JOIN      SYS.SYSIDX AS idx
ON        fk.foreign_table_id = idx.table_id
AND       fk.foreign_index_id = idx.index_id
JOIN      SYS.SYSPHYSIDX pidx
ON        idx.table_id = pidx.table_id
AND       idx.phys_index_id = pidx.phys_index_id
JOIN      SYS.SYSTAB AS ptbl
ON        fk.primary_table_id = ptbl.table_id
JOIN      SYS.SYSTAB AS ftbl
ON        fk.foreign_table_id = ftbl.table_id
JOIN      SYS.SYSIDXCOL AS idxcol
ON        idx.table_id = idxcol.table_id
AND       idx.index_id = idxcol.index_id
JOIN      SYS.SYSTABCOL AS pcol
ON        ptbl.table_id = pcol.table_id
AND       idxcol.primary_column_id = pcol.column_id
JOIN      SYS.SYSTABCOL AS fcol
ON        ftbl.table_id = fcol.table_id
AND       idxcol.column_id = fcol.column_id
LEFT JOIN SYS.SYSTRIGGER ut
ON        fk.foreign_table_id = ut.foreign_table_id
AND       fk.foreign_index_id = ut.foreign_key_id
AND       ut.event = 'C'
LEFT JOIN SYS.SYSTRIGGER dt
ON        fk.foreign_table_id = dt.foreign_table_id
AND       fk.foreign_index_id = dt.foreign_key_id
AND       dt.event = 'D'
WHERE     ftbl.table_name = %s
AND       ftbl.creator = USER_ID(%s)
ORDER BY  fk.foreign_index_id ASC, idxcol.sequence ASC
SQL
            ,
            $table,
            $user
        );
    }

    public function getListTableIndexesSQL(string $table, ?string $currentDatabase = null): string
    {
        $user = '';

        if (strpos($table, '.') !== false) {
            [$user, $table] = explode('.', $table);
            $user           = $this->quoteStringLiteral($user);
            $table          = $this->quoteStringLiteral($table);
        } else {
            $table = $this->quoteStringLiteral($table);
        }

        return sprintf(
            <<<'SQL'
SELECT   idx.index_name AS key_name,
         IF idx.index_category = 1
             THEN 1
             ELSE 0
         ENDIF AS 'primary',
         col.column_name,
         IF idx."unique" IN(1, 2, 5)
             THEN 0
             ELSE 1
         ENDIF AS non_unique,
         IF tbl.clustered_index_id = idx.index_id
             THEN 1
             ELSE NULL
         ENDIF AS 'clustered', -- clustered flag
         IF idx."unique" = 5
             THEN 1
             ELSE NULL
         ENDIF AS with_nulls_not_distinct, -- with_nulls_not_distinct flag
         IF pidx.max_key_distance = 1
              THEN 1
              ELSE NULL
          ENDIF AS for_olap_workload -- for_olap_workload flag
FROM     SYS.SYSIDX AS idx
JOIN     SYS.SYSPHYSIDX pidx
ON       idx.table_id = pidx.table_id
AND      idx.phys_index_id = pidx.phys_index_id
JOIN     SYS.SYSIDXCOL AS idxcol
ON       idx.table_id = idxcol.table_id AND idx.index_id = idxcol.index_id
JOIN     SYS.SYSTABCOL AS col
ON       idxcol.table_id = col.table_id AND idxcol.column_id = col.column_id
JOIN     SYS.SYSTAB AS tbl
ON       idx.table_id = tbl.table_id
WHERE    tbl.table_name = %s
AND      tbl.creator = USER_ID(%s)
AND      idx.index_category != 2 -- exclude indexes implicitly created by foreign key constraints
ORDER BY idx.index_id ASC, idxcol.sequence ASC
SQL
            ,
            $table,
            $user
        );
    }

    public function getListTablesSQL(): string
    {
        return "SELECT   tbl.table_name
                FROM     SYS.SYSTAB AS tbl
                JOIN     SYS.SYSUSER AS usr ON tbl.creator = usr.user_id
                JOIN     dbo.SYSOBJECTS AS obj ON tbl.object_id = obj.id
                WHERE    tbl.table_type IN(1, 3) -- 'BASE', 'GBL TEMP'
                AND      usr.user_name NOT IN('SYS', 'dbo', 'rs_systabgroup') -- exclude system users
                AND      obj.type = 'U' -- user created tables only
                ORDER BY tbl.table_name ASC";
    }

    /**
     * {@inheritdoc}
     *
     * @todo Where is this used? Which information should be retrieved?
     */
    public function getListUsersSQL(): string
    {
        return 'SELECT * FROM SYS.SYSUSER ORDER BY user_name ASC';
    }

    public function getListViewsSQL(string $database): string
    {
        return "SELECT   tbl.table_name, v.view_def
                FROM     SYS.SYSVIEW v
                JOIN     SYS.SYSTAB tbl ON v.view_object_id = tbl.object_id
                JOIN     SYS.SYSUSER usr ON tbl.creator = usr.user_id
                JOIN     dbo.SYSOBJECTS obj ON tbl.object_id = obj.id
                WHERE    usr.user_name NOT IN('SYS', 'dbo', 'rs_systabgroup') -- exclude system users
                ORDER BY tbl.table_name ASC";
    }

    public function getLocateExpression(string $string, string $substring, ?string $start = null): string
    {
        if ($start === null) {
            return sprintf('LOCATE(%s, %s)', $string, $substring);
        }

        return sprintf('LOCATE(%s, %s, %s)', $string, $substring, $start);
    }

    public function getMaxIdentifierLength(): int
    {
        return 128;
    }

    public function getMd5Expression(string $string): string
    {
        return 'HASH(' . $string . ", 'MD5')";
    }

    public function getRegexpExpression(): string
    {
        return 'REGEXP';
    }

    public function getName(): string
    {
        return 'sqlanywhere';
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set a primary key
     * declaration to be used in statements like ALTER TABLE.
     *
     * @param Index  $index Index definition
     * @param string $name  Name of the primary key
     *
     * @return string DBMS specific SQL code portion needed to set a primary key
     *
     * @throws InvalidArgumentException If the given index is not a primary key.
     */
    public function getPrimaryKeyDeclarationSQL(Index $index, ?string $name = null): string
    {
        if (! $index->isPrimary()) {
            throw new InvalidArgumentException(
                'Can only create primary key declarations with getPrimaryKeyDeclarationSQL()'
            );
        }

        return $this->getTableConstraintDeclarationSQL($index, $name);
    }

    public function getSetTransactionIsolationSQL(int $level): string
    {
        return 'SET TEMPORARY OPTION isolation_level = ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    /**
     * {@inheritdoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $columnDef): string
    {
        $columnDef['integer_type'] = 'SMALLINT';

        return $this->_getCommonIntegerTypeDeclarationSQL($columnDef);
    }

    /**
     * Returns the SQL statement for starting an existing database.
     *
     * In SQL Anywhere you can start and stop databases on a
     * database server instance.
     * This is a required statement after having created a new database
     * as it has to be explicitly started to be usable.
     * SQL Anywhere does not automatically start a database after creation!
     *
     * @param string $database Name of the database to start.
     */
    public function getStartDatabaseSQL(string $database): string
    {
        $database = new Identifier($database);

        return "START DATABASE '" . $database->getName() . "' AUTOSTOP OFF";
    }

    /**
     * Returns the SQL statement for stopping a running database.
     *
     * In SQL Anywhere you can start and stop databases on a
     * database server instance.
     * This is a required statement before dropping an existing database
     * as it has to be explicitly stopped before it can be dropped.
     *
     * @param string $database Name of the database to stop.
     */
    public function getStopDatabaseSQL(string $database): string
    {
        $database = new Identifier($database);

        return 'STOP DATABASE "' . $database->getName() . '" UNCONDITIONALLY';
    }

    public function getSubstringExpression(string $string, string $start, ?string $length = null): string
    {
        if ($length === null) {
            return sprintf('SUBSTRING(%s, %s)', $string, $start);
        }

        return sprintf('SUBSTRING(%s, %s, %s)', $string, $start, $length);
    }

    public function getTemporaryTableSQL(): string
    {
        return 'GLOBAL TEMPORARY';
    }

    public function getTimeFormatString(): string
    {
        return 'H:i:s.u';
    }

    /**
     * {@inheritdoc}
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return 'TIME';
    }

    public function getTrimExpression(string $str, int $mode = TrimMode::UNSPECIFIED, ?string $char = null): string
    {
        if (! in_array($mode, [TrimMode::UNSPECIFIED, TrimMode::LEADING, TrimMode::TRAILING, TrimMode::BOTH], true)) {
            throw new InvalidArgumentException(
                sprintf('The value of $mode is expected to be one of the TrimMode constants, %d given', $mode)
            );
        }

        if ($char === null) {
            switch ($mode) {
                case TrimMode::LEADING:
                    return $this->getLtrimExpression($str);

                case TrimMode::TRAILING:
                    return $this->getRtrimExpression($str);

                default:
                    return 'TRIM(' . $str . ')';
            }
        }

        $pattern = "'%[^' + " . $char . " + ']%'";

        switch ($mode) {
            case TrimMode::LEADING:
                return 'SUBSTR(' . $str . ', PATINDEX(' . $pattern . ', ' . $str . '))';

            case TrimMode::TRAILING:
                return 'REVERSE(SUBSTR(REVERSE(' . $str . '), PATINDEX(' . $pattern . ', REVERSE(' . $str . '))))';

            default:
                return 'REVERSE(SUBSTR(REVERSE(SUBSTR(' . $str . ', PATINDEX(' . $pattern . ', ' . $str . '))), ' .
                    'PATINDEX(' . $pattern . ', REVERSE(SUBSTR(' . $str . ', PATINDEX(' . $pattern . ', ' . $str . '))))))';
        }
    }

    public function getCurrentDatabaseExpression(): string
    {
        return 'DB_NAME()';
    }

    public function getTruncateTableSQL(string $tableName, bool $cascade = false): string
    {
        $tableIdentifier = new Identifier($tableName);

        return 'TRUNCATE TABLE ' . $tableIdentifier->getQuotedName($this);
    }

    public function getCreateSequenceSQL(Sequence $sequence): string
    {
        return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this) .
            ' INCREMENT BY ' . $sequence->getAllocationSize() .
            ' START WITH ' . $sequence->getInitialValue() .
            ' MINVALUE ' . $sequence->getInitialValue();
    }

    public function getAlterSequenceSQL(Sequence $sequence): string
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this) .
            ' INCREMENT BY ' . $sequence->getAllocationSize();
    }

    /**
     * {@inheritdoc}
     */
    public function getDropSequenceSQL($sequence): string
    {
        if ($sequence instanceof Sequence) {
            $sequence = $sequence->getQuotedName($this);
        }

        return 'DROP SEQUENCE ' . $sequence;
    }

    public function getListSequencesSQL(string $database): string
    {
        return 'SELECT sequence_name, increment_by, start_with, min_value FROM SYS.SYSSEQUENCE';
    }

    public function getSequenceNextValSQL(string $sequenceName): string
    {
        return 'SELECT ' . $sequenceName . '.NEXTVAL';
    }

    public function supportsSequences(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTimeTzTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return 'TIMESTAMP WITH TIME ZONE';
    }

    public function hasNativeGuidType(): bool
    {
        return true;
    }

    public function prefersIdentityColumns(): bool
    {
        return true;
    }

    public function supportsCommentOnStatement(): bool
    {
        return true;
    }

    public function supportsIdentityColumns(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef): string
    {
        $unsigned      = ! empty($columnDef['unsigned']) ? 'UNSIGNED ' : '';
        $autoincrement = ! empty($columnDef['autoincrement']) ? ' IDENTITY' : '';

        return $unsigned . $columnDef['integer_type'] . $autoincrement;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getCreateTableSQL(string $tableName, array $columns, array $options = []): array
    {
        $columnListSql = $this->getColumnDeclarationListSQL($columns);
        $indexSql      = [];

        if (! empty($options['uniqueConstraints'])) {
            foreach ((array) $options['uniqueConstraints'] as $name => $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if (! empty($options['indexes'])) {
            foreach ((array) $options['indexes'] as $index) {
                assert($index instanceof Index);
                $indexSql[] = $this->getCreateIndexSQL($index, $tableName);
            }
        }

        if (! empty($options['primary'])) {
            $flags = '';

            if (isset($options['primary_index']) && $options['primary_index']->hasFlag('clustered')) {
                $flags = ' CLUSTERED ';
            }

            $columnListSql .= ', PRIMARY KEY' . $flags . ' (' . implode(', ', array_unique(array_values((array) $options['primary']))) . ')';
        }

        if (! empty($options['foreignKeys'])) {
            foreach ((array) $options['foreignKeys'] as $definition) {
                $columnListSql .= ', ' . $this->getForeignKeyDeclarationSQL($definition);
            }
        }

        $query = 'CREATE TABLE ' . $tableName . ' (' . $columnListSql;
        $check = $this->getCheckDeclarationSQL($columns);

        if (! empty($check)) {
            $query .= ', ' . $check;
        }

        $query .= ')';

        return array_merge([$query], $indexSql);
    }

    protected function _getTransactionIsolationLevelSQL(int $level): string
    {
        switch ($level) {
            case TransactionIsolationLevel::READ_UNCOMMITTED:
                return '0';

            case TransactionIsolationLevel::READ_COMMITTED:
                return '1';

            case TransactionIsolationLevel::REPEATABLE_READ:
                return '2';

            case TransactionIsolationLevel::SERIALIZABLE:
                return '3';

            default:
                throw new InvalidArgumentException(sprintf('Invalid isolation level %d.', $level));
        }
    }

    protected function doModifyLimitQuery(string $query, ?int $limit, int $offset): string
    {
        $limitOffsetClause = $this->getTopClauseSQL($limit, $offset);

        if ($limitOffsetClause === '') {
            return $query;
        }

        if (preg_match('/^\s*(SELECT\s+(DISTINCT\s+)?)(.*)/i', $query, $matches) === 0) {
            return $query;
        }

        return $matches[1] . $limitOffsetClause . ' ' . $matches[3];
    }

    private function getTopClauseSQL(?int $limit, ?int $offset): string
    {
        if ($offset > 0) {
            return sprintf('TOP %s START AT %d', $limit ?? 'ALL', $offset + 1);
        }

        return $limit === null ? '' : 'TOP ' . $limit;
    }

    /**
     * Return the INDEX query section dealing with non-standard
     * SQL Anywhere options.
     *
     * @param Index $index Index definition
     */
    protected function getAdvancedIndexOptionsSQL(Index $index): string
    {
        if ($index->hasFlag('with_nulls_distinct') && $index->hasFlag('with_nulls_not_distinct')) {
            throw new UnexpectedValueException(
                'An Index can either have a "with_nulls_distinct" or "with_nulls_not_distinct" flag but not both.'
            );
        }

        $sql = '';

        if (! $index->isPrimary() && $index->hasFlag('for_olap_workload')) {
            $sql .= ' FOR OLAP WORKLOAD';
        }

        if (! $index->isPrimary() && $index->isUnique() && $index->hasFlag('with_nulls_not_distinct')) {
            return ' WITH NULLS NOT DISTINCT' . $sql;
        }

        if (! $index->isPrimary() && $index->isUnique() && $index->hasFlag('with_nulls_distinct')) {
            return ' WITH NULLS DISTINCT' . $sql;
        }

        return $sql;
    }

    /**
     * Returns the SQL snippet for creating a table constraint.
     *
     * @param Constraint  $constraint The table constraint to create the SQL snippet for.
     * @param string|null $name       The table constraint name to use if any.
     *
     * @throws InvalidArgumentException If the given table constraint type is not supported by this method.
     */
    protected function getTableConstraintDeclarationSQL(Constraint $constraint, ?string $name = null): string
    {
        if ($constraint instanceof ForeignKeyConstraint) {
            return $this->getForeignKeyDeclarationSQL($constraint);
        }

        if (! $constraint instanceof Index) {
            throw new InvalidArgumentException(sprintf('Unsupported constraint type %s.', get_class($constraint)));
        }

        if (! $constraint->isPrimary() && ! $constraint->isUnique()) {
            throw new InvalidArgumentException(
                'Can only create primary, unique or foreign key constraint declarations, no common index declarations ' .
                'with getTableConstraintDeclarationSQL().'
            );
        }

        $constraintColumns = $constraint->getQuotedColumns($this);

        if (empty($constraintColumns)) {
            throw new InvalidArgumentException('Incomplete definition. "columns" required.');
        }

        $sql   = '';
        $flags = '';

        if (! empty($name)) {
            $name = new Identifier($name);
            $sql .= 'CONSTRAINT ' . $name->getQuotedName($this) . ' ';
        }

        if ($constraint->hasFlag('clustered')) {
            $flags = 'CLUSTERED ';
        }

        if ($constraint->isPrimary()) {
            return $sql . 'PRIMARY KEY ' . $flags . '(' . $this->getColumnsFieldDeclarationListSQL($constraintColumns) . ')';
        }

        return $sql . 'UNIQUE ' . $flags . '(' . $this->getColumnsFieldDeclarationListSQL($constraintColumns) . ')';
    }

    protected function getCreateIndexSQLFlags(Index $index): string
    {
        $type = '';
        if ($index->hasFlag('virtual')) {
            $type .= 'VIRTUAL ';
        }

        if ($index->isUnique()) {
            $type .= 'UNIQUE ';
        }

        if ($index->hasFlag('clustered')) {
            $type .= 'CLUSTERED ';
        }

        return $type;
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, string $tableName): array
    {
        return ['ALTER INDEX ' . $oldIndexName . ' ON ' . $tableName . ' RENAME TO ' . $index->getQuotedName($this)];
    }

    protected function getReservedKeywordsClass(): string
    {
        return Keywords\SQLAnywhere16Keywords::class;
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        $this->doctrineTypeMapping = [
            'bigint'                   => 'bigint',
            'binary'                   => 'binary',
            'bit'                      => 'boolean',
            'char'                     => 'string',
            'decimal'                  => 'decimal',
            'date'                     => 'date',
            'datetime'                 => 'datetime',
            'double'                   => 'float',
            'float'                    => 'float',
            'image'                    => 'blob',
            'int'                      => 'integer',
            'integer'                  => 'integer',
            'long binary'              => 'blob',
            'long nvarchar'            => 'text',
            'long varbit'              => 'text',
            'long varchar'             => 'text',
            'money'                    => 'decimal',
            'nchar'                    => 'string',
            'ntext'                    => 'text',
            'numeric'                  => 'decimal',
            'nvarchar'                 => 'string',
            'smalldatetime'            => 'datetime',
            'smallint'                 => 'smallint',
            'smallmoney'               => 'decimal',
            'text'                     => 'text',
            'time'                     => 'time',
            'timestamp'                => 'datetime',
            'timestamp with time zone' => 'datetime',
            'tinyint'                  => 'smallint',
            'uniqueidentifier'         => 'guid',
            'uniqueidentifierstr'      => 'guid',
            'unsigned bigint'          => 'bigint',
            'unsigned int'             => 'integer',
            'unsigned smallint'        => 'smallint',
            'unsigned tinyint'         => 'smallint',
            'varbinary'                => 'binary',
            'varbit'                   => 'string',
            'varchar'                  => 'string',
            'xml'                      => 'text',
        ];
    }
}
