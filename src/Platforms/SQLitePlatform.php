<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\Exception\UnsupportedTableDefinition;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\ColumnDoesNotExist;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types;

use function array_combine;
use function array_keys;
use function array_merge;
use function array_search;
use function array_values;
use function count;
use function explode;
use function implode;
use function sprintf;
use function str_contains;
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
    protected function _getCreateTableSQL(OptionallyQualifiedName $tableName, array $columns, array $parameters): array
    {
        if ($this->hasAutoIncrementColumn($columns, $parameters)) {
            unset($parameters['primaryKey']);
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

        foreach ($parameters['foreignKeys'] as $foreignKey) {
            $elements[] = $this->getForeignKeyDeclarationSQL($foreignKey);
        }

        $tableComment = '';
        if (isset($parameters['comment'])) {
            $tableComment = $this->getInlineTableCommentSQL($parameters['comment']);
        }

        $query = [
            'CREATE TABLE ' . $tableName->toSQL($this) . ' ' . $tableComment . '(' . implode(', ', $elements) . ')',
        ];

        if (isset($parameters['alter']) && $parameters['alter'] === true) {
            return $query;
        }

        foreach ($parameters['indexes'] as $indexDef) {
            $query[] = $this->getCreateIndexSQL($indexDef, $tableName->toSQL($this));
        }

        return $query;
    }

    /**
     * @param list<ColumnProperties> $columns
     * @param CreateTableParameters  $parameters
     */
    private function hasAutoIncrementColumn(array $columns, array $parameters): bool
    {
        $primaryKeyColumnNames = [];

        $folding = $this->getUnquotedIdentifierFolding();

        if (isset($parameters['primaryKey'])) {
            foreach ($parameters['primaryKey']->getColumnNames() as $columnName) {
                $columnName = $columnName->getIdentifier()
                    ->toNormalizedValue($folding);

                $primaryKeyColumnNames[$columnName] = true;
            }
        }

        foreach ($columns as $column) {
            if (empty($column['autoincrement'])) {
                continue;
            }

            $columnName = $column['name']->getIdentifier()
                ->toNormalizedValue($folding);

            if (! isset($primaryKeyColumnNames[$columnName])) {
                throw UnsupportedTableDefinition::autoIncrementColumnNotPartOfPrimaryKey($column['name']);
            }

            if (count($primaryKeyColumnNames) > 1) {
                throw UnsupportedTableDefinition::autoIncrementColumnPartOfCompositePrimaryKey($column['name']);
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

    protected function getPrimaryKeyConstraintDeclarationSQL(PrimaryKeyConstraint $constraint): string
    {
        $this->ensurePrimaryKeyConstraintIsNotNamed($constraint);
        $this->ensurePrimaryKeyConstraintIsClustered($constraint);

        return parent::getPrimaryKeyConstraintDeclarationSQL($constraint);
    }

    protected function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        $query = parent::getAdvancedForeignKeyOptionsSQL($foreignKey);

        $deferrability = $foreignKey->getDeferrability();

        return $query . match ($deferrability) {
            Deferrability::NOT_DEFERRABLE => '',
            Deferrability::DEFERRABLE => ' ' . $deferrability->toSQL(),
            Deferrability::DEFERRED => ' DEFERRABLE INITIALLY DEFERRED',
        };
    }

    public function supportsIdentityColumns(): bool
    {
        return true;
    }

    protected function supportsColumnCollation(): bool
    {
        return true;
    }

    protected function supportsInlineColumnComments(): bool
    {
        return true;
    }

    public function getTruncateTableSQL(string $tableName, bool $cascade = false): string
    {
        $tableIdentifier = new Identifier($tableName);

        return 'DELETE FROM ' . $tableIdentifier->getObjectName()->toSQL($this);
    }

    protected function getInlineColumnCommentSQL(string $comment): string
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
            $sql[] = $this->getCreateIndexSQL($index, $table->getObjectName()->toSQL($this));
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

    /**
     * {@inheritDoc}
     *
     * Unlike other database platforms, SQLite requires the schema name to be specified as part of the index name, not
     * the table name.
     *
     * @link https://www.sqlite.org/lang_createindex.html
     */
    public function getCreateIndexSQL(Index $index, string $table): string
    {
        $this->ensureIndexHasNoColumnLengths($index);
        $this->ensureIndexIsNotFulltext($index);
        $this->ensureIndexIsNotSpatial($index);
        $this->ensureIndexIsNotClustered($index);
        $this->ensureIndexIsNotPartial($index);

        $name = $index->getObjectName()->toSQL($this);

        if (str_contains($table, '.')) {
            [$schema, $table] = explode('.', $table);
            $name             = $schema . '.' . $name;
        }

        $chunks = ['CREATE'];
        $type   = $index->getType();

        if ($type === IndexType::UNIQUE) {
            $chunks[] = 'UNIQUE';
        }

        $chunks[] = 'INDEX';
        $chunks[] = $name;
        $chunks[] = 'ON';
        $chunks[] = $table;
        $chunks[] = $this->buildIndexedColumnListSQL($index->getIndexedColumns());

        return implode(' ', $chunks);
    }

    /**
     * {@inheritDoc}
     */
    public function getDropTablesSQL(array $tables): array
    {
        $sql = [];

        foreach ($tables as $table) {
            $sql[] = $this->getDropTableSQL($table->getObjectName()->toSQL($this));
        }

        return $sql;
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
            $oldColumnNames[$columnName] = $newColumnNames[$columnName] = $column->getObjectName()->toSQL($this);
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

            if (! isset($newColumnNames[$oldColumnName])) {
                continue;
            }

            $newColumnNames[$oldColumnName] = $newColumn->getObjectName()->toSQL($this);
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
            $table->getObjectName()->toSQL($this),
            $columns,
            [],
            [],
            $this->getForeignKeysInAlteredTable($diff),
            $table->getOptions(),
            null,
            $this->getPrimaryKeyConstraintInAlteredTable($diff, $table),
        );

        $newTable->addOption('alter', true);

        $sql = $this->getPreAlterTableIndexForeignKeySQL($diff);

        $sql[] = sprintf(
            'CREATE TEMPORARY TABLE %s AS SELECT %s FROM %s',
            $dataTable->getObjectName()->toSQL($this),
            implode(', ', $oldColumnNames),
            $table->getObjectName()->toSQL($this),
        );
        $sql[] = $this->getDropTableSQL($table->getObjectName()->toSQL($this));

        $sql   = array_merge($sql, $this->getCreateTableSQL($newTable));
        $sql[] = sprintf(
            'INSERT INTO %s (%s) SELECT %s FROM %s',
            $newTable->getObjectName()->toSQL($this),
            implode(', ', $newColumnNames),
            implode(', ', $oldColumnNames),
            $dataTable->getObjectName()->toSQL($this),
        );
        $sql[] = $this->getDropTableSQL($dataTable->getObjectName()->toSQL($this));

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
            || count($diff->getDroppedIndexes()) > 0
            || count($diff->getRenamedIndexes()) > 0
            || count($diff->getAddedForeignKeys()) > 0
            || count($diff->getDroppedForeignKeys()) > 0
            || $diff->getDroppedPrimaryKeyConstraint() !== null
            || $diff->getAddedPrimaryKeyConstraint() !== null
        ) {
            return false;
        }

        $table = $diff->getOldTable();

        $sql = [];

        foreach ($diff->getAddedColumns() as $column) {
            $definition = $column->toArray();

            $type = $definition['type'];

            switch (true) {
                case isset($definition['columnDefinition']) || $definition['autoincrement']:
                case $type instanceof Types\DateTimeType && $definition['default'] === $this->getCurrentTimestampSQL():
                case $type instanceof Types\DateType && $definition['default'] === $this->getCurrentDateSQL():
                case $type instanceof Types\TimeType && $definition['default'] === $this->getCurrentTimeSQL():
                    return false;
            }

            $definition['name'] = $column->getObjectName();

            $sql[] = 'ALTER TABLE ' . $table->getObjectName()->toSQL($this) . ' ADD COLUMN '
                . $this->getColumnDeclarationSQL($definition);
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
            $name = $column->getObjectName()->getIdentifier()->getValue();

            $map[$name] = $name;
        }

        foreach ($diff->getDroppedColumns() as $column) {
            $name = $column->getObjectName()->getIdentifier()->getValue();

            unset($map[$name]);
        }

        foreach ($diff->getChangedColumns() as $columnDiff) {
            $oldName = $columnDiff->getOldColumn()->getObjectName()->getIdentifier()->getValue();
            $newName = $columnDiff->getNewColumn()->getObjectName()->getIdentifier()->getValue();

            $map[$oldName] = $newName;
        }

        foreach ($diff->getAddedColumns() as $column) {
            $name = $column->getObjectName()->getIdentifier()->getValue();

            $map[$name] = $name;
        }

        return $map;
    }

    /** @return array<Index> */
    private function getIndexesInAlteredTable(TableDiff $diff): array
    {
        $oldTable = $diff->getOldTable();
        $indexes  = $oldTable->getIndexes();
        $nameMap  = $this->getDiffColumnNameMap($diff);

        foreach ($indexes as $key => $index) {
            foreach ($diff->getRenamedIndexes() as $oldIndexName => $renamedIndex) {
                if (strtolower($key) !== strtolower($oldIndexName)) {
                    continue;
                }

                unset($indexes[$key]);
            }

            $changed     = false;
            $columnNames = [];
            foreach ($index->getIndexedColumns() as $column) {
                $name = $column->getColumnName()->getIdentifier()->getValue();
                if (! isset($nameMap[$name])) {
                    unset($indexes[$key]);
                    continue 2;
                }

                $columnNames[] = $nameMap[$name];
                if ($name === $nameMap[$name]) {
                    continue;
                }

                $changed = true;
            }

            if (! $changed) {
                continue;
            }

            $indexes[$key] = $index->edit()
                ->setUnquotedColumnNames(...$columnNames)
                ->create();
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
            $changed = false;

            $referencingColumnNames = [];
            foreach ($constraint->getReferencingColumnNames() as $columnName) {
                $originalColumnName   = $columnName->getIdentifier()->getValue();
                $normalizedColumnName = strtolower($originalColumnName);
                if (! isset($nameMap[$normalizedColumnName])) {
                    unset($foreignKeys[$key]);
                    continue 2;
                }

                $referencingColumnNames[] = $nameMap[$normalizedColumnName];

                if ($originalColumnName === $nameMap[$normalizedColumnName]) {
                    continue;
                }

                $changed = true;
            }

            if (! $changed) {
                continue;
            }

            $foreignKeys[$key] = $constraint->edit()
                ->setUnquotedReferencingColumnNames(...$referencingColumnNames)
                ->create();
        }

        foreach ($diff->getDroppedForeignKeys() as $constraint) {
            $constraintName = $constraint->getName();

            if ($constraintName === '') {
                continue;
            }

            unset($foreignKeys[strtolower($constraintName)]);
        }

        foreach ($diff->getAddedForeignKeys() as $constraint) {
            $constraintName = $constraint->getName();

            if ($constraintName !== '') {
                $foreignKeys[strtolower($constraintName)] = $constraint;
            } else {
                $foreignKeys[] = $constraint;
            }
        }

        return $foreignKeys;
    }

    private function getPrimaryKeyConstraintInAlteredTable(TableDiff $diff, Table $oldTable): ?PrimaryKeyConstraint
    {
        $addedPrimaryKeyConstraint = $diff->getAddedPrimaryKeyConstraint();

        if ($addedPrimaryKeyConstraint !== null) {
            return $addedPrimaryKeyConstraint;
        }

        if ($diff->getDroppedPrimaryKeyConstraint() !== null) {
            return null;
        }

        $primaryKeyConstraint = $oldTable->getPrimaryKeyConstraint();

        if ($primaryKeyConstraint === null) {
            return null;
        }

        $nameMap = $this->getDiffColumnNameMap($diff);

        $changed = false;

        $columnNames = [];
        foreach ($primaryKeyConstraint->getColumnNames() as $columnName) {
            $originalColumnName   = $columnName->getIdentifier()->getValue();
            $normalizedColumnName = strtolower($originalColumnName);
            if (! isset($nameMap[$normalizedColumnName])) {
                return null;
            }

            $columnNames[] = $nameMap[$normalizedColumnName];

            if ($originalColumnName === $nameMap[$normalizedColumnName]) {
                continue;
            }

            $changed = true;
        }

        if (! $changed) {
            return $primaryKeyConstraint;
        }

        return $primaryKeyConstraint->edit()
            ->setUnquotedColumnNames(...$columnNames)
            ->create();
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
