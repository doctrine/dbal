<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\SQLiteKeywords;
use Doctrine\DBAL\Platforms\SQLite\SQLiteMetadataProvider;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression;
use Doctrine\DBAL\Schema\Exception\ColumnDoesNotExist;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types;
use Doctrine\Deprecations\Deprecation;
use InvalidArgumentException;

use function array_combine;
use function array_fill_keys;
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

/**
 * The SQLitePlatform class describes the specifics and dialects of the SQLite
 * database platform.
 *
 * @phpstan-import-type ColumnProperties from Column
 * @phpstan-import-type CreateTableParameters from AbstractPlatform
 */
class SQLitePlatform extends AbstractPlatform
{
    public function __construct()
    {
        parent::__construct(UnquotedIdentifierFolding::NONE);
    }

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
        // SQLite autoincrement is implicit for INTEGER PKs, but not for BIGINT fields.
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
        // SQLite autoincrement is only possible for the primary key
        if (! empty($column['autoincrement'])) {
            return ' PRIMARY KEY AUTOINCREMENT';
        }

        return ! empty($column['unsigned']) ? ' UNSIGNED' : '';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL(string $name, array $columns, array $options = []): array
    {
        $this->validateCreateTableOptions($options, __METHOD__);

        if ($this->hasAutoIncrementColumn($columns, $options)) {
            $options['primary'] = [];
        }

        $queryFields = $this->getColumnDeclarationListSQL($columns);

        if (! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $definition) {
                $queryFields .= ', ' . $this->getUniqueConstraintDeclarationSQL($definition);
            }
        }

        if (! empty($options['primary'])) {
            $keyColumns   = array_unique(array_values($options['primary']));
            $queryFields .= ', PRIMARY KEY (' . implode(', ', $keyColumns) . ')';
        }

        if (isset($options['foreignKeys'])) {
            foreach ($options['foreignKeys'] as $foreignKey) {
                $queryFields .= ', ' . $this->getForeignKeyDeclarationSQL($foreignKey);
            }
        }

        $tableComment = '';
        if (isset($options['comment'])) {
            $tableComment = $this->getInlineCommentSQL($options['comment']);
        }

        $query = ['CREATE TABLE ' . $name . ' ' . $tableComment . '(' . $queryFields . ')'];

        if (isset($options['alter']) && $options['alter'] === true) {
            return $query;
        }

        if (! empty($options['indexes'])) {
            foreach ($options['indexes'] as $indexDef) {
                $query[] = $this->getCreateIndexSQL($indexDef, $name);
            }
        }

        return $query;
    }

    /**
     * @param list<ColumnProperties> $columns
     * @param CreateTableParameters  $options
     */
    private function hasAutoIncrementColumn(array $columns, array $options): bool
    {
        $primaryKeyColumnNames = array_fill_keys($options['primary'] ?? [], true);

        foreach ($columns as $column) {
            if (empty($column['autoincrement'])) {
                continue;
            }

            if (! isset($primaryKeyColumnNames[$column['name']])) {
                Deprecation::trigger(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/6849',
                    'Declaring a column that is not part of the primary key as auto-increment is deprecated.',
                );
            } elseif (count($primaryKeyColumnNames) > 1) {
                Deprecation::trigger(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/6849',
                    'Declaring a column that is part of a composite primary key as auto-increment is deprecated.',
                );
            }

            return true;
        }

        return false;
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
        return $this->getInlineCommentSQL($comment);
    }

    private function getInlineCommentSQL(string $comment): string
    {
        if ($comment === '') {
            return '';
        }

        return '--' . str_replace("\n", "\n--", $comment) . "\n";
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
            'real'             => 'smallfloat',
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

    /** @deprecated */
    protected function createReservedKeywordsList(): KeywordList
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6607',
            '%s is deprecated.',
            __METHOD__,
        );

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

        foreach ($this->getIndexesInAlteredTable($diff) as $index) {
            if (! $index->isPrimary()) {
                $sql[] = $this->getCreateIndexSQL($index, $table->getQuotedName($this));
            }
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

    /** @deprecated */
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

        foreach ($diff->getChangedColumns() as $columnDiff) {
            $oldColumnName = strtolower($columnDiff->getOldColumn()->getName());
            $newColumn     = $columnDiff->getNewColumn();

            $columns = $this->replaceColumn(
                $table->getName(),
                $columns,
                $oldColumnName,
                $newColumn,
            );

            if (isset($newColumnNames[$oldColumnName])) {
                $newColumnNames[$oldColumnName] = $newColumn->getQuotedName($this);
            }
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
            $this->getPrimaryIndexInAlteredTable($diff),
            [],
            $this->getForeignKeysInAlteredTable($diff),
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

        return array_merge($sql, $this->getPostAlterTableIndexForeignKeySQL($diff));
    }

    /**
     * Replace the column with the given name with the new column.
     *
     * @param array<string,Column> $columns
     *
     * @return array<string,Column>
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

    /** @return list<string>|false */
    private function getSimpleAlterTableSQL(TableDiff $diff): array|false
    {
        if (
            count($diff->getChangedColumns()) > 0
            || count($diff->getDroppedColumns()) > 0
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

        $sql = [];

        foreach ($diff->getAddedColumns() as $column) {
            $definition = $column->toArray();

            $type = $definition['type'];

            switch (true) {
                case isset($definition['columnDefinition']):
                case $definition['autoincrement']:
                case $definition['comment'] !== '':
                // A non-constant default expression (e.g. CURRENT_TIMESTAMP) cannot be used with
                // ALTER TABLE ... ADD COLUMN on a non-empty table, so fall back to a table rebuild.
                case $definition['default'] instanceof DefaultExpression:
                case $type instanceof Types\PhpDateTimeMappingType
                    && $definition['default'] === $this->getCurrentTimestampSQL():
                case $type instanceof Types\PhpDateMappingType && $definition['default'] === $this->getCurrentDateSQL():
                case $type instanceof Types\PhpTimeMappingType && $definition['default'] === $this->getCurrentTimeSQL():
                    return false;
            }

            $definition['name'] = $column->getQuotedName($this);

            $sql[] = 'ALTER TABLE ' . $table->getQuotedName($this) . ' ADD COLUMN '
                . $this->getColumnDeclarationSQL($definition['name'], $definition);
        }

        return $sql;
    }

    /**
     * Based on the table diff, returns a map where the keys are the lower-case old column names and the values are the
     * new column names. If the column was dropped, it is not present in the map.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    private function getDiffColumnNameMap(TableDiff $diff): array
    {
        $oldTable = $diff->getOldTable();

        $map = [];

        foreach ($oldTable->getColumns() as $column) {
            $columnName                   = $column->getName();
            $map[strtolower($columnName)] = $columnName;
        }

        foreach ($diff->getDroppedColumns() as $column) {
            unset($map[strtolower($column->getName())]);
        }

        foreach ($diff->getChangedColumns() as $columnDiff) {
            $columnName                   = $columnDiff->getOldColumn()->getName();
            $map[strtolower($columnName)] = $columnDiff->getNewColumn()->getName();
        }

        foreach ($diff->getAddedColumns() as $column) {
            $columnName                   = $column->getName();
            $map[strtolower($columnName)] = $columnName;
        }

        // @phpstan-ignore return.type
        return $map;
    }

    /** @return array<Index> */
    private function getIndexesInAlteredTable(TableDiff $diff): array
    {
        $oldTable = $diff->getOldTable();
        $indexes  = $oldTable->getIndexes();
        $nameMap  = $this->getDiffColumnNameMap($diff);

        foreach ($indexes as $key => $index) {
            $indexName = $index->getName();
            foreach ($diff->getRenamedIndexes() as $oldIndexName => $renamedIndex) {
                if (strtolower($indexName) === strtolower($oldIndexName)) {
                    unset($indexes[$key]);
                }
            }

            $changed      = false;
            $indexColumns = [];
            // Use the unquoted column names so the lookup is agnostic of whether the index
            // was introspected (which marks its column names as quoted) or built in memory.
            foreach ($index->getUnquotedColumns() as $columnName) {
                $normalizedColumnName = strtolower($columnName);
                if (! isset($nameMap[$normalizedColumnName])) {
                    unset($indexes[$key]);
                    continue 2;
                }

                $indexColumns[] = $nameMap[$normalizedColumnName];
                if ($columnName !== $nameMap[$normalizedColumnName]) {
                    $changed = true;
                }
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

            if ($indexName !== '') {
                unset($indexes[strtolower($indexName)]);
            }
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

    /** @return array<ForeignKeyConstraint> */
    private function getForeignKeysInAlteredTable(TableDiff $diff): array
    {
        $oldTable    = $diff->getOldTable();
        $foreignKeys = $oldTable->getForeignKeys();
        $nameMap     = $this->getDiffColumnNameMap($diff);

        foreach ($foreignKeys as $key => $constraint) {
            $changed      = false;
            $localColumns = [];
            // Use the unquoted column names so the lookup is agnostic of whether the constraint
            // was introspected (which marks its column names as quoted) or built in memory.
            foreach ($constraint->getUnquotedLocalColumns() as $columnName) {
                $normalizedColumnName = strtolower($columnName);
                if (! isset($nameMap[$normalizedColumnName])) {
                    unset($foreignKeys[$key]);
                    continue 2;
                }

                $localColumns[] = $nameMap[$normalizedColumnName];
                if ($columnName !== $nameMap[$normalizedColumnName]) {
                    $changed = true;
                }
            }

            if (! $changed) {
                continue;
            }

            $foreignKeys[$key] = new ForeignKeyConstraint(
                $localColumns, // @phpstan-ignore argument.type
                $constraint->getForeignTableName(),
                $constraint->getForeignColumns(), // @phpstan-ignore argument.type
                $constraint->getName(),
                $constraint->getOptions(),
            );
        }

        foreach ($diff->getDroppedForeignKeys() as $constraint) {
            $constraintName = $constraint->getName();

            if ($constraintName !== '') {
                unset($foreignKeys[strtolower($constraintName)]);
            }
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

    /** @return array<string, Index> */
    private function getPrimaryIndexInAlteredTable(TableDiff $diff): array
    {
        $primaryIndex = [];

        foreach ($this->getIndexesInAlteredTable($diff) as $index) {
            if ($index->isPrimary()) {
                $primaryIndex = [$index->getName() => $index];
            }
        }

        return $primaryIndex;
    }

    public function createMetadataProvider(Connection $connection): SQLiteMetadataProvider
    {
        return new SQLiteMetadataProvider($connection, $this);
    }

    public function createSchemaManager(Connection $connection): SQLiteSchemaManager
    {
        return new SQLiteSchemaManager($connection, $this);
    }

    /**
     * Returns the union select query part surrounded by parenthesis if possible for platform.
     */
    public function getUnionSelectPartSQL(string $subQuery): string
    {
        return $subQuery;
    }
}
