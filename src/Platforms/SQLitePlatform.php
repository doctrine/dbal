<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\SQLiteKeywords;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\ColumnDoesNotExist;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types;
use InvalidArgumentException;

use function array_combine;
use function array_keys;
use function array_merge;
use function array_search;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function sprintf;
use function str_replace;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * The SQLitePlatform class describes the specifics and dialects of the SQLite
 * database platform.
 */
class SQLitePlatform extends AbstractPlatform
{
    public function getCreateDatabaseSQL(string $name): string
    {
        throw NotSupported::new(__METHOD__);
    }

    public function getDropDatabaseSQL(string $name): string
    {
        throw NotSupported::new(__METHOD__);
    }

    public function getRegexpExpression(): string
    {
        return 'REGEXP';
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
        $trimFn = match ($mode) {
            TrimMode::UNSPECIFIED,
            TrimMode::BOTH => 'TRIM',
            TrimMode::LEADING => 'LTRIM',
            TrimMode::TRAILING => 'RTRIM',
        };

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
        if ($start === null || $start === '1') {
            return sprintf('INSTR(%s, %s)', $string, $substring);
        }

        return sprintf(
            'CASE WHEN INSTR(SUBSTR(%1$s, %3$s), %2$s) > 0 THEN INSTR(SUBSTR(%1$s, %3$s), %2$s) + %3$s - 1 ELSE 0 END',
            $string,
            $substring,
            $start,
        );
    }

    protected function getDateArithmeticIntervalExpression(
        string $date,
        string $operator,
        string $interval,
        DateIntervalUnit $unit,
    ): string {
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
            $this->quoteStringLiteral(' ' . $unit->value),
        ) . ')';
    }

    public function getDateDiffExpression(string $date1, string $date2): string
    {
        return sprintf("JULIANDAY(%s, 'start of day') - JULIANDAY(%s, 'start of day')", $date1, $date2);
    }

    /**
     * {@inheritDoc}
     *
     * The DBAL doesn't support databases on the SQLite platform. The expression here always returns a fixed string
     * as an indicator of an implicitly selected database.
     *
     * @link https://www.sqlite.org/lang_select.html
     * @see Connection::getDatabase()
     */
    public function getCurrentDatabaseExpression(): string
    {
        return "'main'";
    }

    /** @link https://www2.sqlite.org/cvstrac/wiki?p=UnsupportedSql */
    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return new DefaultSelectSQLBuilder($this, null, null);
    }

    protected function _getTransactionIsolationLevelSQL(TransactionIsolationLevel $level): string
    {
        return match ($level) {
            TransactionIsolationLevel::READ_UNCOMMITTED => '0',
            TransactionIsolationLevel::READ_COMMITTED,
            TransactionIsolationLevel::REPEATABLE_READ,
            TransactionIsolationLevel::SERIALIZABLE => '1',
        };
    }

    public function getSetTransactionIsolationSQL(TransactionIsolationLevel $level): string
    {
        return 'PRAGMA read_uncommitted = ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $column): string
    {
        return 'BOOLEAN';
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $column): string
    {
        return 'INTEGER' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $column): string
    {
        //  SQLite autoincrement is implicit for INTEGER PKs, but not for BIGINT fields.
        if (! empty($column['autoincrement'])) {
            return $this->getIntegerTypeDeclarationSQL($column);
        }

        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $column): string
    {
        // SQLite autoincrement is implicit for INTEGER PKs, but not for SMALLINT fields.
        if (! empty($column['autoincrement'])) {
            return $this->getIntegerTypeDeclarationSQL($column);
        }

        return 'SMALLINT' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $column): string
    {
        return 'DATETIME';
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
        return 'TIME';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $column): string
    {
        // sqlite autoincrement is only possible for the primary key
        if (! empty($column['autoincrement'])) {
            return ' PRIMARY KEY AUTOINCREMENT';
        }

        return ! empty($column['unsigned']) ? ' UNSIGNED' : '';
    }

    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function getForeignKeyDeclarationSQL(ForeignKeyConstraint $foreignKey): string
    {
        return parent::getForeignKeyDeclarationSQL(new ForeignKeyConstraint(
            $foreignKey->getQuotedLocalColumns($this),
            $foreignKey->getQuotedForeignTableName($this),
            $foreignKey->getQuotedForeignColumns($this),
            $foreignKey->getName(),
            $foreignKey->getOptions(),
        ));
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL(string $name, array $columns, array $options = []): array
    {
        $queryFields = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && ! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $definition) {
                $queryFields .= ', ' . $this->getUniqueConstraintDeclarationSQL($definition);
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

        $query = ['CREATE TABLE ' . $name . ' ' . $tableComment . '(' . $queryFields . ')'];

        if (isset($options['alter']) && $options['alter'] === true) {
            return $query;
        }

        if (isset($options['indexes']) && ! empty($options['indexes'])) {
            foreach ($options['indexes'] as $indexDef) {
                $query[] = $this->getCreateIndexSQL($indexDef, $name);
            }
        }

        if (isset($options['unique']) && ! empty($options['unique'])) {
            foreach ($options['unique'] as $indexDef) {
                $query[] = $this->getCreateIndexSQL($indexDef, $name);
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
    public function getClobTypeDeclarationSQL(array $column): string
    {
        return 'CLOB';
    }

    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function getListViewsSQL(string $database): string
    {
        return "SELECT name, sql FROM sqlite_master WHERE type='view' AND sql NOT NULL";
    }

    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        $query = parent::getAdvancedForeignKeyOptionsSQL($foreignKey);

        if (! $foreignKey->hasOption('deferrable') || $foreignKey->getOption('deferrable') === false) {
            $query .= ' NOT';
        }

        $query .= ' DEFERRABLE';
        $query .= ' INITIALLY';

        if ($foreignKey->hasOption('deferred') && $foreignKey->getOption('deferred') !== false) {
            $query .= ' DEFERRED';
        } else {
            $query .= ' IMMEDIATE';
        }

        return $query;
    }

    public function supportsIdentityColumns(): bool
    {
        return true;
    }

    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function supportsColumnCollation(): bool
    {
        return true;
    }

    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function supportsInlineColumnComments(): bool
    {
        return true;
    }

    public function getTruncateTableSQL(string $tableName, bool $cascade = false): string
    {
        $tableIdentifier = new Identifier($tableName);

        return 'DELETE FROM ' . $tableIdentifier->getQuotedName($this);
    }

    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
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

    protected function createReservedKeywordsList(): KeywordList
    {
        return new SQLiteKeywords();
    }

    /**
     * {@inheritDoc}
     */
    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        $table = $diff->getOldTable();

        $sql = [];

        foreach ($this->getIndexesInAlteredTable($diff, $table) as $index) {
            if ($index->isPrimary()) {
                continue;
            }

            $sql[] = $this->getCreateIndexSQL($index, $table->getQuotedName($this));
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
    public function getBlobTypeDeclarationSQL(array $column): string
    {
        return 'BLOB';
    }

    public function getTemporaryTableName(string $tableName): string
    {
        return $tableName;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTablesSQL(array $tables): array
    {
        $sql = [];

        foreach ($tables as $table) {
            $sql = array_merge($sql, $this->getCreateTableSQL($table));
        }

        return $sql;
    }

    /** {@inheritDoc} */
    public function getCreateIndexSQL(Index $index, string $table): string
    {
        $name    = $index->getQuotedName($this);
        $columns = $index->getColumns();

        if (count($columns) === 0) {
            throw new InvalidArgumentException(sprintf(
                'Incomplete or invalid index definition %s on table %s',
                $name,
                $table,
            ));
        }

        if ($index->isPrimary()) {
            return $this->getCreatePrimaryKeySQL($index, $table);
        }

        if (strpos($table, '.') !== false) {
            [$schema, $table] = explode('.', $table);
            $name             = $schema . '.' . $name;
        }

        $query  = 'CREATE ' . $this->getCreateIndexSQLFlags($index) . 'INDEX ' . $name . ' ON ' . $table;
        $query .= ' (' . implode(', ', $index->getQuotedColumns($this)) . ')' . $this->getPartialIndexSQL($index);

        return $query;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropTablesSQL(array $tables): array
    {
        $sql = [];

        foreach ($tables as $table) {
            $sql[] = $this->getDropTableSQL($table->getQuotedName($this));
        }

        return $sql;
    }

    public function getCreatePrimaryKeySQL(Index $index, string $table): string
    {
        throw NotSupported::new(__METHOD__);
    }

    public function getCreateForeignKeySQL(ForeignKeyConstraint $foreignKey, string $table): string
    {
        throw NotSupported::new(__METHOD__);
    }

    public function getDropForeignKeySQL(string $foreignKey, string $table): string
    {
        throw NotSupported::new(__METHOD__);
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

        $table = $diff->getOldTable();

        $columns        = [];
        $oldColumnNames = [];
        $newColumnNames = [];
        $columnSql      = [];

        foreach ($table->getColumns() as $column) {
            $columnName                  = strtolower($column->getName());
            $columns[$columnName]        = $column;
            $oldColumnNames[$columnName] = $newColumnNames[$columnName] = $column->getQuotedName($this);
        }

        foreach ($diff->getDroppedColumns() as $column) {
            $columnName = strtolower($column->getName());
            if (! isset($columns[$columnName])) {
                continue;
            }

            unset(
                $columns[$columnName],
                $oldColumnNames[$columnName],
                $newColumnNames[$columnName],
            );
        }

        foreach ($diff->getRenamedColumns() as $oldColumnName => $column) {
            $oldColumnName = strtolower($oldColumnName);

            $columns = $this->replaceColumn(
                $table->getName(),
                $columns,
                $oldColumnName,
                $column,
            );

            if (! isset($newColumnNames[$oldColumnName])) {
                continue;
            }

            $newColumnNames[$oldColumnName] = $column->getQuotedName($this);
        }

        foreach ($diff->getModifiedColumns() as $columnDiff) {
            $oldColumnName = strtolower($columnDiff->getOldColumn()->getName());
            $newColumn     = $columnDiff->getNewColumn();

            $columns = $this->replaceColumn(
                $table->getName(),
                $columns,
                $oldColumnName,
                $newColumn,
            );

            if (! isset($newColumnNames[$oldColumnName])) {
                continue;
            }

            $newColumnNames[$oldColumnName] = $newColumn->getQuotedName($this);
        }

        foreach ($diff->getAddedColumns() as $column) {
            $columns[strtolower($column->getName())] = $column;
        }

        $tableName = $table->getName();
        $pos       = strpos($tableName, '.');
        if ($pos !== false) {
            $tableName = substr($tableName, $pos + 1);
        }

        $dataTable = new Table('__temp__' . $tableName);

        $newTable = new Table(
            $table->getQuotedName($this),
            $columns,
            $this->getPrimaryIndexInAlteredTable($diff, $table),
            [],
            $this->getForeignKeysInAlteredTable($diff, $table),
            $table->getOptions(),
        );

        $newTable->addOption('alter', true);

        $sql = $this->getPreAlterTableIndexForeignKeySQL($diff);

        $sql[] = sprintf(
            'CREATE TEMPORARY TABLE %s AS SELECT %s FROM %s',
            $dataTable->getQuotedName($this),
            implode(', ', $oldColumnNames),
            $table->getQuotedName($this),
        );
        $sql[] = $this->getDropTableSQL($table->getQuotedName($this));

        $sql   = array_merge($sql, $this->getCreateTableSQL($newTable));
        $sql[] = sprintf(
            'INSERT INTO %s (%s) SELECT %s FROM %s',
            $newTable->getQuotedName($this),
            implode(', ', $newColumnNames),
            implode(', ', $oldColumnNames),
            $dataTable->getQuotedName($this),
        );
        $sql[] = $this->getDropTableSQL($dataTable->getQuotedName($this));

        return array_merge($sql, $this->getPostAlterTableIndexForeignKeySQL($diff), $columnSql);
    }

    /**
     * Replace the column with the given name with the new column.
     *
     * @param array<string,Column> $columns
     *
     * @return array<string,Column>
     *
     * @throws Exception
     */
    private function replaceColumn(string $tableName, array $columns, string $columnName, Column $column): array
    {
        $keys  = array_keys($columns);
        $index = array_search($columnName, $keys, true);

        if ($index === false) {
            throw ColumnDoesNotExist::new($columnName, $tableName);
        }

        $values = array_values($columns);

        $keys[$index]   = strtolower($column->getName());
        $values[$index] = $column;

        return array_combine($keys, $values);
    }

    /**
     * @return list<string>|false
     *
     * @throws Exception
     */
    private function getSimpleAlterTableSQL(TableDiff $diff): array|false
    {
        if (
            count($diff->getModifiedColumns()) > 0
            || count($diff->getDroppedColumns()) > 0
            || count($diff->getRenamedColumns()) > 0
            || count($diff->getAddedIndexes()) > 0
            || count($diff->getModifiedIndexes()) > 0
            || count($diff->getDroppedIndexes()) > 0
            || count($diff->getRenamedIndexes()) > 0
            || count($diff->getAddedForeignKeys()) > 0
            || count($diff->getModifiedForeignKeys()) > 0
            || count($diff->getDroppedForeignKeys()) > 0
        ) {
            return false;
        }

        $table = $diff->getOldTable();

        $sql       = [];
        $columnSql = [];

        foreach ($diff->getAddedColumns() as $column) {
            $definition = array_merge([
                'unique' => null,
                'autoincrement' => null,
                'default' => null,
            ], $column->toArray());

            $type = $definition['type'];

            /** @psalm-suppress RiskyTruthyFalsyComparison */
            switch (true) {
                case isset($definition['columnDefinition']) || $definition['autoincrement'] || $definition['unique']:
                case $type instanceof Types\DateTimeType && $definition['default'] === $this->getCurrentTimestampSQL():
                case $type instanceof Types\DateType && $definition['default'] === $this->getCurrentDateSQL():
                case $type instanceof Types\TimeType && $definition['default'] === $this->getCurrentTimeSQL():
                    return false;
            }

            $definition['name'] = $column->getQuotedName($this);

            $sql[] = 'ALTER TABLE ' . $table->getQuotedName($this) . ' ADD COLUMN '
                . $this->getColumnDeclarationSQL($definition['name'], $definition);
        }

        return array_merge($sql, $columnSql);
    }

    /** @return string[] */
    private function getColumnNamesInAlteredTable(TableDiff $diff, Table $oldTable): array
    {
        $columns = [];

        foreach ($oldTable->getColumns() as $column) {
            $columnName                       = $column->getName();
            $columns[strtolower($columnName)] = $columnName;
        }

        foreach ($diff->getDroppedColumns() as $column) {
            $columnName = strtolower($column->getName());
            if (! isset($columns[$columnName])) {
                continue;
            }

            unset($columns[$columnName]);
        }

        foreach ($diff->getRenamedColumns() as $oldColumnName => $column) {
            $columnName                          = $column->getName();
            $columns[strtolower($oldColumnName)] = $columnName;
            $columns[strtolower($columnName)]    = $columnName;
        }

        foreach ($diff->getModifiedColumns() as $columnDiff) {
            $oldColumnName                       = $columnDiff->getOldColumn()->getName();
            $newColumnName                       = $columnDiff->getNewColumn()->getName();
            $columns[strtolower($oldColumnName)] = $newColumnName;
            $columns[strtolower($newColumnName)] = $newColumnName;
        }

        foreach ($diff->getAddedColumns() as $column) {
            $columnName                       = $column->getName();
            $columns[strtolower($columnName)] = $columnName;
        }

        return $columns;
    }

    /** @return Index[] */
    private function getIndexesInAlteredTable(TableDiff $diff, Table $oldTable): array
    {
        $indexes     = $oldTable->getIndexes();
        $columnNames = $this->getColumnNamesInAlteredTable($diff, $oldTable);

        foreach ($indexes as $key => $index) {
            foreach ($diff->getRenamedIndexes() as $oldIndexName => $renamedIndex) {
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

            $indexes[$key] = new Index(
                $index->getName(),
                $indexColumns,
                $index->isUnique(),
                $index->isPrimary(),
                $index->getFlags(),
            );
        }

        foreach ($diff->getDroppedIndexes() as $index) {
            $indexName = $index->getName();

            if ($indexName === '') {
                continue;
            }

            unset($indexes[strtolower($indexName)]);
        }

        foreach (
            array_merge(
                $diff->getModifiedIndexes(),
                $diff->getAddedIndexes(),
                $diff->getRenamedIndexes(),
            ) as $index
        ) {
            $indexName = $index->getName();

            if ($indexName !== '') {
                $indexes[strtolower($indexName)] = $index;
            } else {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /** @return ForeignKeyConstraint[] */
    private function getForeignKeysInAlteredTable(TableDiff $diff, Table $oldTable): array
    {
        $foreignKeys = $oldTable->getForeignKeys();
        $columnNames = $this->getColumnNamesInAlteredTable($diff, $oldTable);

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

            $foreignKeys[$key] = new ForeignKeyConstraint(
                $localColumns,
                $constraint->getForeignTableName(),
                $constraint->getForeignColumns(),
                $constraint->getName(),
                $constraint->getOptions(),
            );
        }

        foreach ($diff->getDroppedForeignKeys() as $constraint) {
            $constraintName = $constraint->getName();

            if ($constraintName === '') {
                continue;
            }

            unset($foreignKeys[strtolower($constraintName)]);
        }

        foreach (array_merge($diff->getModifiedForeignKeys(), $diff->getAddedForeignKeys()) as $constraint) {
            $constraintName = $constraint->getName();

            if ($constraintName !== '') {
                $foreignKeys[strtolower($constraintName)] = $constraint;
            } else {
                $foreignKeys[] = $constraint;
            }
        }

        return $foreignKeys;
    }

    /** @return Index[] */
    private function getPrimaryIndexInAlteredTable(TableDiff $diff, Table $oldTable): array
    {
        $primaryIndex = [];

        foreach ($this->getIndexesInAlteredTable($diff, $oldTable) as $index) {
            if (! $index->isPrimary()) {
                continue;
            }

            $primaryIndex = [$index->getName() => $index];
        }

        return $primaryIndex;
    }

    public function createSchemaManager(Connection $connection): SQLiteSchemaManager
    {
        return new SQLiteSchemaManager($connection, $this);
    }
}
