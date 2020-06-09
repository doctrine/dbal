<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Constraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types;
use InvalidArgumentException;

use function array_merge;
use function array_unique;
use function array_values;
use function implode;
use function sprintf;
use function sqrt;
use function str_replace;
use function strpos;
use function strtolower;
use function trim;

/**
 * The SqlitePlatform class describes the specifics and dialects of the SQLite
 * database platform.
 *
 * @todo   Rename: SQLitePlatform
 */
class SqlitePlatform extends AbstractPlatform
{
    public function getRegexpExpression(): string
    {
        return 'REGEXP';
    }

    public function getNowExpression(string $type = 'timestamp'): string
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

    public function getTrimExpression(string $str, int $mode = TrimMode::UNSPECIFIED, ?string $char = null): string
    {
        switch ($mode) {
            case TrimMode::UNSPECIFIED:
            case TrimMode::BOTH:
                $trimFn = 'TRIM';
                break;

            case TrimMode::LEADING:
                $trimFn = 'LTRIM';
                break;

            case TrimMode::TRAILING:
                $trimFn = 'RTRIM';
                break;

            default:
                throw new InvalidArgumentException(
                    sprintf(
                        'The value of $mode is expected to be one of the TrimMode constants, %d given.',
                        $mode
                    )
                );
        }

        $arguments = [$str];

        if ($char !== null) {
            $arguments[] = $char;
        }

        return sprintf('%s(%s)', $trimFn, implode(', ', $arguments));
    }

    public function getSubstringExpression(string $string, string $start, ?string $length = null): string
    {
        if ($length === null) {
            return sprintf('SUBSTR(%s, %s)', $string, $start);
        }

        return sprintf('SUBSTR(%s, %s, %s)', $string, $start, $length);
    }

    public function getLocateExpression(string $string, string $substring, ?string $start = null): string
    {
        if ($start === null) {
            return sprintf('LOCATE(%s, %s)', $string, $substring);
        }

        return sprintf('LOCATE(%s, %s, %s)', $string, $substring, $start);
    }

    protected function getDateArithmeticIntervalExpression(string $date, string $operator, string $interval, string $unit): string
    {
        switch ($unit) {
            case DateIntervalUnit::WEEK:
                $interval = $this->multiplyInterval($interval, 7);
                $unit     = DateIntervalUnit::DAY;
                break;

            case DateIntervalUnit::QUARTER:
                $interval = $this->multiplyInterval($interval, 3);
                $unit     = DateIntervalUnit::MONTH;
                break;
        }

        return 'DATETIME(' . $date . ',' . $this->getConcatExpression(
            $this->quoteStringLiteral($operator),
            $interval,
            $this->quoteStringLiteral(' ' . $unit)
        ) . ')';
    }

    public function getDateDiffExpression(string $date1, string $date2): string
    {
        return sprintf("JULIANDAY(%s, 'start of day') - JULIANDAY(%s, 'start of day')", $date1, $date2);
    }

    /**
     * {@inheritDoc}
     *
     * The SQLite platform doesn't support the concept of a database, therefore, it always returns an empty string
     * as an indicator of an implicitly selected database.
     *
     * @see \Doctrine\DBAL\Connection::getDatabase()
     */
    public function getCurrentDatabaseExpression(): string
    {
        return "''";
    }

    protected function _getTransactionIsolationLevelSQL(int $level): string
    {
        switch ($level) {
            case TransactionIsolationLevel::READ_UNCOMMITTED:
                return '0';

            case TransactionIsolationLevel::READ_COMMITTED:
            case TransactionIsolationLevel::REPEATABLE_READ:
            case TransactionIsolationLevel::SERIALIZABLE:
                return '1';

            default:
                return parent::_getTransactionIsolationLevelSQL($level);
        }
    }

    public function getSetTransactionIsolationSQL(int $level): string
    {
        return 'PRAGMA read_uncommitted = ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    public function prefersIdentityColumns(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $columnDef): string
    {
        return 'BOOLEAN';
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $columnDef): string
    {
        return 'INTEGER' . $this->_getCommonIntegerTypeDeclarationSQL($columnDef);
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $columnDef): string
    {
        //  SQLite autoincrement is implicit for INTEGER PKs, but not for BIGINT fields.
        if (! empty($columnDef['autoincrement'])) {
            return $this->getIntegerTypeDeclarationSQL($columnDef);
        }

        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($columnDef);
    }

    /**
     * @param array<string, mixed> $field
     */
    public function getTinyIntTypeDeclarationSQL(array $field): string
    {
        //  SQLite autoincrement is implicit for INTEGER PKs, but not for TINYINT fields.
        if (! empty($field['autoincrement'])) {
            return $this->getIntegerTypeDeclarationSQL($field);
        }

        return 'TINYINT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $columnDef): string
    {
        //  SQLite autoincrement is implicit for INTEGER PKs, but not for SMALLINT fields.
        if (! empty($columnDef['autoincrement'])) {
            return $this->getIntegerTypeDeclarationSQL($columnDef);
        }

        return 'SMALLINT' . $this->_getCommonIntegerTypeDeclarationSQL($columnDef);
    }

    /**
     * @param array<string, mixed> $field
     */
    public function getMediumIntTypeDeclarationSQL(array $field): string
    {
        //  SQLite autoincrement is implicit for INTEGER PKs, but not for MEDIUMINT fields.
        if (! empty($field['autoincrement'])) {
            return $this->getIntegerTypeDeclarationSQL($field);
        }

        return 'MEDIUMINT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return 'DATETIME';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return 'TIME';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef): string
    {
        // sqlite autoincrement is only possible for the primary key
        if (! empty($columnDef['autoincrement'])) {
            return ' PRIMARY KEY AUTOINCREMENT';
        }

        return ! empty($columnDef['unsigned']) ? ' UNSIGNED' : '';
    }

    public function getForeignKeyDeclarationSQL(ForeignKeyConstraint $foreignKey): string
    {
        return parent::getForeignKeyDeclarationSQL(new ForeignKeyConstraint(
            $foreignKey->getQuotedLocalColumns($this),
            str_replace('.', '__', $foreignKey->getQuotedForeignTableName($this)),
            $foreignKey->getQuotedForeignColumns($this),
            $foreignKey->getName(),
            $foreignKey->getOptions()
        ));
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL(string $tableName, array $columns, array $options = []): array
    {
        $tableName   = str_replace('.', '__', $tableName);
        $queryFields = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && ! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $constraintName => $definition) {
                $queryFields .= ', ' . $this->getUniqueConstraintDeclarationSQL($constraintName, $definition);
            }
        }

        $queryFields .= $this->getNonAutoincrementPrimaryKeyDefinition($columns, $options);

        if (isset($options['foreignKeys'])) {
            foreach ($options['foreignKeys'] as $foreignKey) {
                $queryFields .= ', ' . $this->getForeignKeyDeclarationSQL($foreignKey);
            }
        }

        $tableComment = '';
        if (isset($options['comment'])) {
            $comment = trim($options['comment'], " '");

            $tableComment = $this->getInlineTableCommentSQL($comment);
        }

        $query = ['CREATE TABLE ' . $tableName . ' ' . $tableComment . '(' . $queryFields . ')'];

        if (isset($options['alter']) && $options['alter'] === true) {
            return $query;
        }

        if (isset($options['indexes']) && ! empty($options['indexes'])) {
            foreach ($options['indexes'] as $indexDef) {
                $query[] = $this->getCreateIndexSQL($indexDef, $tableName);
            }
        }

        if (isset($options['unique']) && ! empty($options['unique'])) {
            foreach ($options['unique'] as $indexDef) {
                $query[] = $this->getCreateIndexSQL($indexDef, $tableName);
            }
        }

        return $query;
    }

    /**
     * Generate a PRIMARY KEY definition if no autoincrement value is used
     *
     * @param mixed[][] $columns
     * @param mixed[]   $options
     */
    private function getNonAutoincrementPrimaryKeyDefinition(array $columns, array $options): string
    {
        if (empty($options['primary'])) {
            return '';
        }

        $keyColumns = array_unique(array_values($options['primary']));

        foreach ($keyColumns as $keyColumn) {
            foreach ($columns as $column) {
                if ($column['name'] === $keyColumn && ! empty($column['autoincrement'])) {
                    return '';
                }
            }
        }

        return ', PRIMARY KEY(' . implode(', ', $keyColumns) . ')';
    }

    protected function getBinaryTypeDeclarationSQLSnippet(?int $length): string
    {
        return 'BLOB';
    }

    protected function getVarcharTypeDeclarationSQLSnippet(?int $length): string
    {
        $sql = 'VARCHAR';

        if ($length !== null) {
            $sql .= sprintf('(%d)', $length);
        }

        return $sql;
    }

    protected function getVarbinaryTypeDeclarationSQLSnippet(?int $length): string
    {
        return 'BLOB';
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field): string
    {
        return 'CLOB';
    }

    public function getListTableConstraintsSQL(string $table): string
    {
        $table = str_replace('.', '__', $table);

        return sprintf(
            "SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name = %s AND sql NOT NULL ORDER BY name",
            $this->quoteStringLiteral($table)
        );
    }

    public function getListTableColumnsSQL(string $table, ?string $database = null): string
    {
        $table = str_replace('.', '__', $table);

        return sprintf('PRAGMA table_info(%s)', $this->quoteStringLiteral($table));
    }

    public function getListTableIndexesSQL(string $table, ?string $currentDatabase = null): string
    {
        $table = str_replace('.', '__', $table);

        return sprintf('PRAGMA index_list(%s)', $this->quoteStringLiteral($table));
    }

    public function getListTablesSQL(): string
    {
        return "SELECT name FROM sqlite_master WHERE type = 'table' AND name != 'sqlite_sequence' AND name != 'geometry_columns' AND name != 'spatial_ref_sys' "
             . 'UNION ALL SELECT name FROM sqlite_temp_master '
             . "WHERE type = 'table' ORDER BY name";
    }

    public function getListViewsSQL(string $database): string
    {
        return "SELECT name, sql FROM sqlite_master WHERE type='view' AND sql NOT NULL";
    }

    public function getCreateViewSQL(string $name, string $sql): string
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    public function getDropViewSQL(string $name): string
    {
        return 'DROP VIEW ' . $name;
    }

    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        $query = parent::getAdvancedForeignKeyOptionsSQL($foreignKey);

        $query .= ($foreignKey->hasOption('deferrable') && $foreignKey->getOption('deferrable') !== false ? ' ' : ' NOT ') . 'DEFERRABLE';
        $query .= ' INITIALLY ' . ($foreignKey->hasOption('deferred') && $foreignKey->getOption('deferred') !== false ? 'DEFERRED' : 'IMMEDIATE');

        return $query;
    }

    public function supportsIdentityColumns(): bool
    {
        return true;
    }

    public function supportsColumnCollation(): bool
    {
        return true;
    }

    public function supportsInlineColumnComments(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'sqlite';
    }

    public function getTruncateTableSQL(string $tableName, bool $cascade = false): string
    {
        $tableIdentifier = new Identifier($tableName);
        $tableName       = str_replace('.', '__', $tableIdentifier->getQuotedName($this));

        return 'DELETE FROM ' . $tableName;
    }

    /**
     * User-defined function for Sqlite that is used with PDO::sqliteCreateFunction().
     *
     * @param int|float $value
     */
    public static function udfSqrt($value): float
    {
        return sqrt($value);
    }

    /**
     * User-defined function for Sqlite that implements MOD(a, b).
     */
    public static function udfMod(int $a, int $b): int
    {
        return $a % $b;
    }

    public static function udfLocate(string $str, string $substr, int $offset = 0): int
    {
        // SQL's LOCATE function works on 1-based positions, while PHP's strpos works on 0-based positions.
        // So we have to make them compatible if an offset is given.
        if ($offset > 0) {
            $offset -= 1;
        }

        $pos = strpos($str, $substr, $offset);

        if ($pos !== false) {
            return $pos + 1;
        }

        return 0;
    }

    public function getForUpdateSQL(): string
    {
        return '';
    }

    public function getInlineColumnCommentSQL(string $comment): string
    {
        if ($comment === '') {
            return '';
        }

        return '--' . str_replace("\n", "\n--", $comment) . "\n";
    }

    private function getInlineTableCommentSQL(string $comment): string
    {
        return $this->getInlineColumnCommentSQL($comment);
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        $this->doctrineTypeMapping = [
            'bigint'           => 'bigint',
            'bigserial'        => 'bigint',
            'blob'             => 'blob',
            'boolean'          => 'boolean',
            'char'             => 'string',
            'clob'             => 'text',
            'date'             => 'date',
            'datetime'         => 'datetime',
            'decimal'          => 'decimal',
            'double'           => 'float',
            'double precision' => 'float',
            'float'            => 'float',
            'image'            => 'string',
            'int'              => 'integer',
            'integer'          => 'integer',
            'longtext'         => 'text',
            'longvarchar'      => 'string',
            'mediumint'        => 'integer',
            'mediumtext'       => 'text',
            'ntext'            => 'string',
            'numeric'          => 'decimal',
            'nvarchar'         => 'string',
            'real'             => 'float',
            'serial'           => 'integer',
            'smallint'         => 'smallint',
            'string'           => 'string',
            'text'             => 'text',
            'time'             => 'time',
            'timestamp'        => 'datetime',
            'tinyint'          => 'boolean',
            'tinytext'         => 'text',
            'varchar'          => 'string',
            'varchar2'         => 'string',
        ];
    }

    protected function getReservedKeywordsClass(): string
    {
        return Keywords\SQLiteKeywords::class;
    }

    /**
     * {@inheritDoc}
     */
    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        if (! $diff->fromTable instanceof Table) {
            throw new DBALException('Sqlite platform requires for alter table the table diff with reference to original table schema.');
        }

        $sql = [];
        foreach ($diff->fromTable->getIndexes() as $index) {
            if ($index->isPrimary()) {
                continue;
            }

            $sql[] = $this->getDropIndexSQL($index, $diff->name);
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        if ($diff->fromTable === null) {
            throw new DBALException('Sqlite platform requires for alter table the table diff with reference to original table schema.');
        }

        $sql       = [];
        $tableName = $diff->getNewName();

        if ($tableName === null) {
            $tableName = $diff->getName($this);
        }

        foreach ($this->getIndexesInAlteredTable($diff, $diff->fromTable) as $index) {
            if ($index->isPrimary()) {
                continue;
            }

            $sql[] = $this->getCreateIndexSQL($index, $tableName->getQuotedName($this));
        }

        return $sql;
    }

    protected function doModifyLimitQuery(string $query, ?int $limit, int $offset): string
    {
        if ($limit === null && $offset > 0) {
            $limit = -1;
        }

        return parent::doModifyLimitQuery($query, $limit, $offset);
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field): string
    {
        return 'BLOB';
    }

    public function getTemporaryTableName(string $tableName): string
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
    public function canEmulateSchemas(): bool
    {
        return true;
    }

    public function supportsForeignKeyConstraints(): bool
    {
        return true;
    }

    public function supportsCreateDropForeignKeyConstraints(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreatePrimaryKeySQL(Index $index, $table): string
    {
        throw new DBALException('Sqlite platform does not support alter primary key.');
    }

    /**
     * {@inheritdoc}
     */
    public function getCreateForeignKeySQL(ForeignKeyConstraint $foreignKey, $table): string
    {
        throw new DBALException('Sqlite platform does not support alter foreign key, the table must be fully recreated using getAlterTableSQL.');
    }

    /**
     * {@inheritdoc}
     */
    public function getDropForeignKeySQL($foreignKey, $table): string
    {
        throw new DBALException('Sqlite platform does not support alter foreign key, the table must be fully recreated using getAlterTableSQL.');
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateConstraintSQL(Constraint $constraint, $table): string
    {
        throw new DBALException('Sqlite platform does not support alter constraint.');
    }

    /**
     * {@inheritDoc}
     *
     * @param int $createFlags
     */
    public function getCreateTableSQL(Table $table, int $createFlags = self::CREATE_INDEXES | self::CREATE_FOREIGNKEYS): array
    {
        return parent::getCreateTableSQL($table, $createFlags);
    }

    public function getListTableForeignKeysSQL(string $table, ?string $database = null): string
    {
        $table = str_replace('.', '__', $table);

        return sprintf('PRAGMA foreign_key_list(%s)', $this->quoteStringLiteral($table));
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff): array
    {
        $sql = $this->getSimpleAlterTableSQL($diff);
        if ($sql !== false) {
            return $sql;
        }

        $fromTable = $diff->fromTable;
        if ($fromTable === null) {
            throw new DBALException('Sqlite platform requires for alter table the table diff with reference to original table schema.');
        }

        $table = clone $fromTable;

        $columns        = [];
        $oldColumnNames = [];
        $newColumnNames = [];
        $columnSql      = [];

        foreach ($table->getColumns() as $columnName => $column) {
            $columnName                  = strtolower($columnName);
            $columns[$columnName]        = $column;
            $oldColumnNames[$columnName] = $newColumnNames[$columnName] = $column->getQuotedName($this);
        }

        foreach ($diff->removedColumns as $columnName => $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $columnName = strtolower($columnName);
            if (! isset($columns[$columnName])) {
                continue;
            }

            unset(
                $columns[$columnName],
                $oldColumnNames[$columnName],
                $newColumnNames[$columnName]
            );
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = strtolower($oldColumnName);
            if (isset($columns[$oldColumnName])) {
                unset($columns[$oldColumnName]);
            }

            $columns[strtolower($column->getName())] = $column;

            if (! isset($newColumnNames[$oldColumnName])) {
                continue;
            }

            $newColumnNames[$oldColumnName] = $column->getQuotedName($this);
        }

        foreach ($diff->changedColumns as $oldColumnName => $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            if (isset($columns[$oldColumnName])) {
                unset($columns[$oldColumnName]);
            }

            $columns[strtolower($columnDiff->column->getName())] = $columnDiff->column;

            if (! isset($newColumnNames[$oldColumnName])) {
                continue;
            }

            $newColumnNames[$oldColumnName] = $columnDiff->column->getQuotedName($this);
        }

        foreach ($diff->addedColumns as $columnName => $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $columns[strtolower($columnName)] = $column;
        }

        $sql      = [];
        $tableSql = [];

        if (! $this->onSchemaAlterTable($diff, $tableSql)) {
            $dataTable = new Table('__temp__' . $table->getName());

            $newTable = new Table($table->getQuotedName($this), $columns, $this->getPrimaryIndexInAlteredTable($diff, $fromTable), [], $this->getForeignKeysInAlteredTable($diff, $fromTable), $table->getOptions());
            $newTable->addOption('alter', true);

            $sql = $this->getPreAlterTableIndexForeignKeySQL($diff);
            //$sql = array_merge($sql, $this->getCreateTableSQL($dataTable, 0));
            $sql[] = sprintf('CREATE TEMPORARY TABLE %s AS SELECT %s FROM %s', $dataTable->getQuotedName($this), implode(', ', $oldColumnNames), $table->getQuotedName($this));
            $sql[] = $this->getDropTableSQL($fromTable);

            $sql   = array_merge($sql, $this->getCreateTableSQL($newTable));
            $sql[] = sprintf('INSERT INTO %s (%s) SELECT %s FROM %s', $newTable->getQuotedName($this), implode(', ', $newColumnNames), implode(', ', $oldColumnNames), $dataTable->getQuotedName($this));
            $sql[] = $this->getDropTableSQL($dataTable);

            $newName = $diff->getNewName();

            if ($newName !== null) {
                $sql[] = sprintf(
                    'ALTER TABLE %s RENAME TO %s',
                    $newTable->getQuotedName($this),
                    $newName->getQuotedName($this)
                );
            }

            $sql = array_merge($sql, $this->getPostAlterTableIndexForeignKeySQL($diff));
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * @return string[]|false
     */
    private function getSimpleAlterTableSQL(TableDiff $diff)
    {
        // Suppress changes on integer type autoincrement columns.
        foreach ($diff->changedColumns as $oldColumnName => $columnDiff) {
            if (
                $columnDiff->fromColumn === null ||
                ! $columnDiff->column->getAutoincrement() ||
                ! $columnDiff->column->getType() instanceof Types\IntegerType
            ) {
                continue;
            }

            if (! $columnDiff->hasChanged('type') && $columnDiff->hasChanged('unsigned')) {
                unset($diff->changedColumns[$oldColumnName]);

                continue;
            }

            $fromColumnType = $columnDiff->fromColumn->getType();

            if (! ($fromColumnType instanceof Types\SmallIntType) && ! ($fromColumnType instanceof Types\BigIntType)) {
                continue;
            }

            unset($diff->changedColumns[$oldColumnName]);
        }

        if (
            ! empty($diff->renamedColumns) || ! empty($diff->addedForeignKeys) || ! empty($diff->addedIndexes)
                || ! empty($diff->changedColumns) || ! empty($diff->changedForeignKeys) || ! empty($diff->changedIndexes)
                || ! empty($diff->removedColumns) || ! empty($diff->removedForeignKeys) || ! empty($diff->removedIndexes)
                || ! empty($diff->renamedIndexes)
        ) {
            return false;
        }

        $table = new Table($diff->name);

        $sql       = [];
        $tableSql  = [];
        $columnSql = [];

        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $field = array_merge(['unique' => null, 'autoincrement' => null, 'default' => null], $column->toArray());
            $type  = $field['type'];
            switch (true) {
                case isset($field['columnDefinition']) || $field['autoincrement'] || $field['unique']:
                case $type instanceof Types\DateTimeType && $field['default'] === $this->getCurrentTimestampSQL():
                case $type instanceof Types\DateType && $field['default'] === $this->getCurrentDateSQL():
                case $type instanceof Types\TimeType && $field['default'] === $this->getCurrentTimeSQL():
                    return false;
            }

            $field['name'] = $column->getQuotedName($this);
            if ($type instanceof Types\StringType && $field['length'] === null) {
                $field['length'] = 255;
            }

            $sql[] = 'ALTER TABLE ' . $table->getQuotedName($this) . ' ADD COLUMN ' . $this->getColumnDeclarationSQL($field['name'], $field);
        }

        if (! $this->onSchemaAlterTable($diff, $tableSql)) {
            if ($diff->newName !== null) {
                $newTable = new Identifier($diff->newName);
                $sql[]    = 'ALTER TABLE ' . $table->getQuotedName($this) . ' RENAME TO ' . $newTable->getQuotedName($this);
            }
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * @return string[]
     */
    private function getColumnNamesInAlteredTable(TableDiff $diff, Table $fromTable): array
    {
        $columns = [];

        foreach ($fromTable->getColumns() as $columnName => $column) {
            $columns[strtolower($columnName)] = $column->getName();
        }

        foreach ($diff->removedColumns as $columnName => $column) {
            $columnName = strtolower($columnName);
            if (! isset($columns[$columnName])) {
                continue;
            }

            unset($columns[$columnName]);
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            $columnName                          = $column->getName();
            $columns[strtolower($oldColumnName)] = $columnName;
            $columns[strtolower($columnName)]    = $columnName;
        }

        foreach ($diff->changedColumns as $oldColumnName => $columnDiff) {
            $columnName                          = $columnDiff->column->getName();
            $columns[strtolower($oldColumnName)] = $columnName;
            $columns[strtolower($columnName)]    = $columnName;
        }

        foreach ($diff->addedColumns as $column) {
            $columnName                       = $column->getName();
            $columns[strtolower($columnName)] = $columnName;
        }

        return $columns;
    }

    /**
     * @return Index[]
     */
    private function getIndexesInAlteredTable(TableDiff $diff, Table $fromTable): array
    {
        $indexes     = $fromTable->getIndexes();
        $columnNames = $this->getColumnNamesInAlteredTable($diff, $fromTable);

        foreach ($indexes as $key => $index) {
            foreach ($diff->renamedIndexes as $oldIndexName => $renamedIndex) {
                if (strtolower($key) !== strtolower($oldIndexName)) {
                    continue;
                }

                unset($indexes[$key]);
            }

            $changed      = false;
            $indexColumns = [];
            foreach ($index->getColumns() as $columnName) {
                $normalizedColumnName = strtolower($columnName);
                if (! isset($columnNames[$normalizedColumnName])) {
                    unset($indexes[$key]);
                    continue 2;
                }

                $indexColumns[] = $columnNames[$normalizedColumnName];
                if ($columnName === $columnNames[$normalizedColumnName]) {
                    continue;
                }

                $changed = true;
            }

            if (! $changed) {
                continue;
            }

            $indexes[$key] = new Index($index->getName(), $indexColumns, $index->isUnique(), $index->isPrimary(), $index->getFlags());
        }

        foreach ($diff->removedIndexes as $index) {
            $indexName = $index->getName();

            if ($indexName === '') {
                continue;
            }

            unset($indexes[strtolower($indexName)]);
        }

        foreach (array_merge($diff->changedIndexes, $diff->addedIndexes, $diff->renamedIndexes) as $index) {
            $indexName = $index->getName();

            if ($indexName !== '') {
                $indexes[strtolower($indexName)] = $index;
            } else {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * @return ForeignKeyConstraint[]
     */
    private function getForeignKeysInAlteredTable(TableDiff $diff, Table $fromTable): array
    {
        $foreignKeys = $fromTable->getForeignKeys();
        $columnNames = $this->getColumnNamesInAlteredTable($diff, $fromTable);

        foreach ($foreignKeys as $key => $constraint) {
            $changed      = false;
            $localColumns = [];
            foreach ($constraint->getLocalColumns() as $columnName) {
                $normalizedColumnName = strtolower($columnName);
                if (! isset($columnNames[$normalizedColumnName])) {
                    unset($foreignKeys[$key]);
                    continue 2;
                }

                $localColumns[] = $columnNames[$normalizedColumnName];
                if ($columnName === $columnNames[$normalizedColumnName]) {
                    continue;
                }

                $changed = true;
            }

            if (! $changed) {
                continue;
            }

            $foreignKeys[$key] = new ForeignKeyConstraint($localColumns, $constraint->getForeignTableName(), $constraint->getForeignColumns(), $constraint->getName(), $constraint->getOptions());
        }

        foreach ($diff->removedForeignKeys as $constraint) {
            $constraintName = $constraint->getName();

            if ($constraintName === '') {
                continue;
            }

            unset($foreignKeys[strtolower($constraintName)]);
        }

        foreach (array_merge($diff->changedForeignKeys, $diff->addedForeignKeys) as $constraint) {
            $constraintName = $constraint->getName();

            if ($constraintName !== '') {
                $foreignKeys[strtolower($constraintName)] = $constraint;
            } else {
                $foreignKeys[] = $constraint;
            }
        }

        return $foreignKeys;
    }

    /**
     * @return Index[]
     */
    private function getPrimaryIndexInAlteredTable(TableDiff $diff, Table $fromTable): array
    {
        $primaryIndex = [];

        foreach ($this->getIndexesInAlteredTable($diff, $fromTable) as $index) {
            if (! $index->isPrimary()) {
                continue;
            }

            $primaryIndex = [$index->getName() => $index];
        }

        return $primaryIndex;
    }
}
