<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnValuesRequired;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Types;

use function array_map;
use function array_merge;
use function count;
use function implode;
use function is_array;
use function is_numeric;
use function sprintf;
use function str_replace;

/**
 * Provides the base implementation for the lowest versions of supported MySQL-like database platforms.
 */
abstract class AbstractMySQLPlatform extends AbstractPlatform
{
    final public const LENGTH_LIMIT_TINYTEXT   = 255;
    final public const LENGTH_LIMIT_TEXT       = 65535;
    final public const LENGTH_LIMIT_MEDIUMTEXT = 16777215;

    final public const LENGTH_LIMIT_TINYBLOB   = 255;
    final public const LENGTH_LIMIT_BLOB       = 65535;
    final public const LENGTH_LIMIT_MEDIUMBLOB = 16777215;

    public function __construct()
    {
        parent::__construct(UnquotedIdentifierFolding::NONE);
    }

    protected function doModifyLimitQuery(string $query, ?int $limit, int $offset): string
    {
        if ($limit !== null) {
            $query .= sprintf(' LIMIT %d', $limit);

            if ($offset > 0) {
                $query .= sprintf(' OFFSET %d', $offset);
            }
        } elseif ($offset > 0) {
            // 2^64-1 is the maximum of unsigned BIGINT, the biggest limit possible
            $query .= sprintf(' LIMIT 18446744073709551615 OFFSET %d', $offset);
        }

        return $query;
    }

    public function quoteSingleIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function getRegexpExpression(): string
    {
        return 'RLIKE';
    }

    public function getLocateExpression(string $string, string $substring, ?string $start = null): string
    {
        if ($start === null) {
            return sprintf('LOCATE(%s, %s)', $substring, $string);
        }

        return sprintf('LOCATE(%s, %s, %s)', $substring, $string, $start);
    }

    public function getConcatExpression(string ...$string): string
    {
        return sprintf('CONCAT(%s)', implode(', ', $string));
    }

    protected function getDateArithmeticIntervalExpression(
        string $date,
        string $operator,
        string $interval,
        DateIntervalUnit $unit,
    ): string {
        $function = $operator === '+' ? 'DATE_ADD' : 'DATE_SUB';

        return $function . '(' . $date . ', INTERVAL ' . $interval . ' ' . $unit->value . ')';
    }

    public function getDateDiffExpression(string $date1, string $date2): string
    {
        return 'DATEDIFF(' . $date1 . ', ' . $date2 . ')';
    }

    public function getCurrentDatabaseExpression(): string
    {
        return 'DATABASE()';
    }

    public function getLengthExpression(string $string): string
    {
        return 'CHAR_LENGTH(' . $string . ')';
    }

    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function getListDatabasesSQL(): string
    {
        return 'SHOW DATABASES';
    }

    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function getListViewsSQL(string $database): string
    {
        return 'SELECT * FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ' . $this->quoteStringLiteral($database);
    }

    /**
     * {@inheritDoc}
     */
    public function getJsonTypeDeclarationSQL(array $column): string
    {
        return 'JSON';
    }

    /**
     * Gets the SQL snippet used to declare a CLOB column type.
     *     TINYTEXT   : 2 ^  8 - 1 = 255
     *     TEXT       : 2 ^ 16 - 1 = 65535
     *     MEDIUMTEXT : 2 ^ 24 - 1 = 16777215
     *     LONGTEXT   : 2 ^ 32 - 1 = 4294967295
     *
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $column): string
    {
        if (! empty($column['length']) && is_numeric($column['length'])) {
            $length = $column['length'];

            if ($length <= static::LENGTH_LIMIT_TINYTEXT) {
                return 'TINYTEXT';
            }

            if ($length <= static::LENGTH_LIMIT_TEXT) {
                return 'TEXT';
            }

            if ($length <= static::LENGTH_LIMIT_MEDIUMTEXT) {
                return 'MEDIUMTEXT';
            }
        }

        return 'LONGTEXT';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $column): string
    {
        if (isset($column['version']) && $column['version'] === true) {
            return 'TIMESTAMP';
        }

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
    public function getBooleanTypeDeclarationSQL(array $column): string
    {
        return 'TINYINT(1)';
    }

    /**
     * {@inheritDoc}
     *
     * MySQL supports this through AUTO_INCREMENT columns.
     */
    public function supportsIdentityColumns(): bool
    {
        return true;
    }

    protected function supportsInlineColumnComments(): bool
    {
        return true;
    }

    protected function supportsColumnCollation(): bool
    {
        return true;
    }

    /**
     * The SQL snippet required to elucidate a column type
     *
     * Returns a column type SELECT snippet string
     *
     * @internal The method should be only used from within the {@see MySQLSchemaManager} class hierarchy.
     */
    public function getColumnTypeSQLSnippet(string $tableAlias, string $databaseName): string
    {
        return $tableAlias . '.COLUMN_TYPE';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL(OptionallyQualifiedName $tableName, array $columns, array $parameters): array
    {
        $elements = [];

        foreach ($columns as $column) {
            $elements[] = $this->getColumnDeclarationSQL($column);
        }

        foreach ($parameters['uniqueConstraints'] as $definition) {
            $elements[] = $this->getUniqueConstraintDeclarationSQL($definition);
        }

        foreach ($parameters['indexes'] as $definition) {
            $elements[] = $this->getIndexDeclarationSQL($definition);
        }

        if (isset($parameters['primaryKey'])) {
            $elements[] = $this->getPrimaryKeyConstraintDeclarationSQL($parameters['primaryKey']);
        }

        $sql = ['CREATE'];

        if (! empty($parameters['temporary'])) {
            $sql[] = 'TEMPORARY';
        }

        $sql[] = 'TABLE ' . $tableName->toSQL($this) . ' (' . implode(', ', $elements) . ')';

        $tableOptions = $this->buildTableOptions($parameters);

        if ($tableOptions !== '') {
            $sql[] = $tableOptions;
        }

        if (isset($parameters['partition_options'])) {
            $sql[] = $parameters['partition_options'];
        }

        $sql = [implode(' ', $sql)];

        if (isset($parameters['foreignKeys'])) {
            foreach ($parameters['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $tableName->toSQL($this));
            }
        }

        return $sql;
    }

    public function getCreateIndexSQL(Index $index, string $table): string
    {
        $this->ensureIndexIsNotClustered($index);
        $this->ensureIndexIsNotPartial($index);

        return parent::getCreateIndexSQL($index, $table);
    }

    /**
     * Build SQL for table options
     *
     * @param mixed[] $options
     */
    private function buildTableOptions(array $options): string
    {
        if (isset($options['table_options'])) {
            return $options['table_options'];
        }

        $tableOptions = [];

        if (isset($options['charset'])) {
            $tableOptions[] = sprintf('DEFAULT CHARACTER SET %s', $options['charset']);
        }

        if (isset($options['collation'])) {
            $tableOptions[] = $this->getColumnCollationDeclarationSQL($options['collation']);
        }

        if (isset($options['engine'])) {
            $tableOptions[] = sprintf('ENGINE = %s', $options['engine']);
        }

        // Auto increment
        if (isset($options['auto_increment'])) {
            $tableOptions[] = sprintf('AUTO_INCREMENT = %s', $options['auto_increment']);
        }

        // Comment
        if (isset($options['comment'])) {
            $tableOptions[] = sprintf('COMMENT = %s ', $this->quoteStringLiteral($options['comment']));
        }

        // Row format
        if (isset($options['row_format'])) {
            $tableOptions[] = sprintf('ROW_FORMAT = %s', $options['row_format']);
        }

        return implode(' ', $tableOptions);
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff): array
    {
        $queryParts = [];

        foreach ($diff->getAddedColumns() as $column) {
            $columnProperties = array_merge($column->toArray());

            $queryParts[] = 'ADD ' . $this->getColumnDeclarationSQL($columnProperties);
        }

        foreach ($diff->getDroppedColumns() as $column) {
            $queryParts[] = 'DROP ' . $column->getObjectName()->toSQL($this);
        }

        foreach ($diff->getChangedColumns() as $columnDiff) {
            $newColumn = $columnDiff->getNewColumn();

            $newColumnProperties = $newColumn->toArray();

            $oldColumn = $columnDiff->getOldColumn();

            $queryParts[] = 'CHANGE ' . $oldColumn->getObjectName()->toSQL($this) . ' '
                . $this->getColumnDeclarationSQL($newColumnProperties);
        }

        if ($diff->getDroppedPrimaryKeyConstraint() !== null) {
            $queryParts[] = 'DROP PRIMARY KEY';
        }

        $addedPrimaryKeyConstraint = $diff->getAddedPrimaryKeyConstraint();

        if ($addedPrimaryKeyConstraint !== null) {
            $queryParts[] = 'ADD ' . $this->getPrimaryKeyConstraintDeclarationSQL($addedPrimaryKeyConstraint);
        }

        $tableSql = [];

        if (count($queryParts) > 0) {
            $tableSql[] = 'ALTER TABLE ' . $diff->getOldTable()->getObjectName()->toSQL($this) . ' '
                . implode(', ', $queryParts);
        }

        return array_merge(
            $this->getPreAlterTableIndexForeignKeySQL($diff),
            $tableSql,
            $this->getPostAlterTableIndexForeignKeySQL($diff),
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        $sql = [];

        $tableNameSQL = $diff->getOldTable()->getObjectName()->toSQL($this);

        foreach ($diff->getDroppedIndexes() as $droppedIndex) {
            foreach ($diff->getAddedIndexes() as $addedIndex) {
                if (! $this->indexedColumnNamesEqual($droppedIndex, $addedIndex)) {
                    continue;
                }

                $sql[] = sprintf(
                    'ALTER TABLE %s DROP INDEX %s, ADD %s',
                    $tableNameSQL,
                    $droppedIndex->getObjectName()->toSQL($this),
                    $this->getIndexDeclarationSQL($addedIndex),
                );

                $diff->unsetAddedIndex($addedIndex);
                $diff->unsetDroppedIndex($droppedIndex);

                break;
            }
        }

        return array_merge($sql, parent::getPreAlterTableIndexForeignKeySQL($diff));
    }

    private function indexedColumnNamesEqual(Index $index1, Index $index2): bool
    {
        $columns1 = $index1->getIndexedColumns();
        $columns2 = $index2->getIndexedColumns();

        if (count($columns1) !== count($columns2)) {
            return false;
        }

        $folding = $this->getUnquotedIdentifierFolding();
        foreach ($columns1 as $i => $column1) {
            if (! $column1->getColumnName()->equals($columns2[$i]->getColumnName(), $folding)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the SQL fragment representing an index.
     */
    private function getIndexDeclarationSQL(Index $index): string
    {
        $chunks = [];
        $type   = $index->getType();

        if ($type === IndexType::UNIQUE) {
            $chunks[] = 'UNIQUE';
        } elseif ($type === IndexType::FULLTEXT) {
            $chunks[] = 'FULLTEXT';
        } elseif ($type === IndexType::SPATIAL) {
            $chunks[] = 'SPATIAL';
        }

        $chunks[] = 'INDEX';
        $chunks[] = $index->getObjectName()->toSQL($this);
        $chunks[] = $this->buildIndexedColumnListSQL($index->getIndexedColumns());

        return implode(' ', $chunks);
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
    public function getFloatDeclarationSQL(array $column): string
    {
        return 'DOUBLE PRECISION' . $this->getUnsignedDeclaration($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallFloatDeclarationSQL(array $column): string
    {
        return 'FLOAT' . $this->getUnsignedDeclaration($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getDecimalTypeDeclarationSQL(array $column): string
    {
        return parent::getDecimalTypeDeclarationSQL($column) . $this->getUnsignedDeclaration($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getEnumDeclarationSQL(array $column): string
    {
        if (! isset($column['values']) || ! is_array($column['values']) || $column['values'] === []) {
            throw ColumnValuesRequired::new($this, 'ENUM');
        }

        return sprintf('ENUM(%s)', implode(', ', array_map(
            $this->quoteStringLiteral(...),
            $column['values'],
        )));
    }

    /**
     * Get unsigned declaration for a column.
     *
     * @param mixed[] $columnDef
     */
    private function getUnsignedDeclaration(array $columnDef): string
    {
        return ! empty($columnDef['unsigned']) ? ' UNSIGNED' : '';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $column): string
    {
        $sql = $this->getUnsignedDeclaration($column);

        if (! empty($column['autoincrement'])) {
            $sql .= ' AUTO_INCREMENT';
        }

        return $sql;
    }

    protected function getColumnCharsetDeclarationSQL(string $charset): string
    {
        return 'CHARACTER SET ' . $charset;
    }

    protected function getPrimaryKeyConstraintDeclarationSQL(PrimaryKeyConstraint $constraint): string
    {
        $this->ensurePrimaryKeyConstraintIsNotNamed($constraint);
        $this->ensurePrimaryKeyConstraintIsClustered($constraint);

        return parent::getPrimaryKeyConstraintDeclarationSQL($constraint);
    }

    protected function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        $query = '';

        $matchType = $foreignKey->getMatchType();
        if ($matchType !== MatchType::SIMPLE) {
            $query .= ' MATCH ' . $matchType->toSQL();
        }

        $query .= parent::getAdvancedForeignKeyOptionsSQL($foreignKey);

        return $query;
    }

    public function getDropIndexSQL(string $name, string $table): string
    {
        return 'DROP INDEX ' . $name . ' ON ' . $table;
    }

    /**
     * The `ALTER TABLE ... DROP CONSTRAINT` syntax is only available as of MySQL 8.0.19.
     *
     * @link https://dev.mysql.com/doc/refman/8.0/en/alter-table.html
     */
    public function getDropUniqueConstraintSQL(string $name, string $tableName): string
    {
        return $this->getDropIndexSQL($name, $tableName);
    }

    public function getSetTransactionIsolationSQL(TransactionIsolationLevel $level): string
    {
        return 'SET SESSION TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        $this->doctrineTypeMapping = [
            'bigint'     => Types::BIGINT,
            'binary'     => Types::BINARY,
            'blob'       => Types::BLOB,
            'char'       => Types::STRING,
            'date'       => Types::DATE_MUTABLE,
            'datetime'   => Types::DATETIME_MUTABLE,
            'decimal'    => Types::DECIMAL,
            'double'     => Types::FLOAT,
            'enum'       => Types::ENUM,
            'float'      => Types::SMALLFLOAT,
            'int'        => Types::INTEGER,
            'integer'    => Types::INTEGER,
            'json'       => Types::JSON,
            'longblob'   => Types::BLOB,
            'longtext'   => Types::TEXT,
            'mediumblob' => Types::BLOB,
            'mediumint'  => Types::INTEGER,
            'mediumtext' => Types::TEXT,
            'numeric'    => Types::DECIMAL,
            'real'       => Types::FLOAT,
            'set'        => Types::SIMPLE_ARRAY,
            'smallint'   => Types::SMALLINT,
            'string'     => Types::STRING,
            'text'       => Types::TEXT,
            'time'       => Types::TIME_MUTABLE,
            'timestamp'  => Types::DATETIME_MUTABLE,
            'tinyblob'   => Types::BLOB,
            'tinyint'    => Types::BOOLEAN,
            'tinytext'   => Types::TEXT,
            'varbinary'  => Types::BINARY,
            'varchar'    => Types::STRING,
            'year'       => Types::DATE_MUTABLE,
        ];
    }

    /**
     * {@inheritDoc}
     *
     * MySQL commits a transaction implicitly when DROP TABLE is executed, however not
     * if DROP TEMPORARY TABLE is executed.
     */
    public function getDropTemporaryTableSQL(string $table): string
    {
        return 'DROP TEMPORARY TABLE ' . $table;
    }

    /**
     * Gets the SQL Snippet used to declare a BLOB column type.
     *     TINYBLOB   : 2 ^  8 - 1 = 255
     *     BLOB       : 2 ^ 16 - 1 = 65535
     *     MEDIUMBLOB : 2 ^ 24 - 1 = 16777215
     *     LONGBLOB   : 2 ^ 32 - 1 = 4294967295
     *
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $column): string
    {
        if (! empty($column['length']) && is_numeric($column['length'])) {
            $length = $column['length'];

            if ($length <= static::LENGTH_LIMIT_TINYBLOB) {
                return 'TINYBLOB';
            }

            if ($length <= static::LENGTH_LIMIT_BLOB) {
                return 'BLOB';
            }

            if ($length <= static::LENGTH_LIMIT_MEDIUMBLOB) {
                return 'MEDIUMBLOB';
            }
        }

        return 'LONGBLOB';
    }

    public function quoteStringLiteral(string $str): string
    {
        // MySQL requires backslashes to be escaped as well.
        $str = str_replace('\\', '\\\\', $str);

        return parent::quoteStringLiteral($str);
    }

    public function getDefaultTransactionIsolationLevel(): TransactionIsolationLevel
    {
        return TransactionIsolationLevel::REPEATABLE_READ;
    }

    public function createSchemaManager(Connection $connection): MySQLSchemaManager
    {
        return new MySQLSchemaManager($connection, $this);
    }

    /** @internal The method should be only used from within the {@see MySQLSchemaManager} class hierarchy. */
    public function fetchTableOptionsByTable(bool $includeTableName): string
    {
        $sql = <<<'SQL'
    SELECT t.TABLE_NAME,
           t.ENGINE,
           t.AUTO_INCREMENT,
           t.TABLE_COMMENT,
           t.CREATE_OPTIONS,
           t.TABLE_COLLATION,
           ccsa.CHARACTER_SET_NAME
      FROM information_schema.TABLES t
        INNER JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY ccsa
          ON ccsa.COLLATION_NAME = t.TABLE_COLLATION
SQL;

        $conditions = ['t.TABLE_SCHEMA = ?'];

        if ($includeTableName) {
            $conditions[] = 't.TABLE_NAME = ?';
        }

        $conditions[] = "t.TABLE_TYPE = 'BASE TABLE'";

        return $sql . ' WHERE ' . implode(' AND ', $conditions);
    }
}
