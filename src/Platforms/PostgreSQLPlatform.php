<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQL\PostgreSQLMetadataProvider;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\UnspecifiedConstraintName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Types;
use Override;
use UnexpectedValueException;

use function array_merge;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function sprintf;
use function strtolower;
use function trim;

/**
 * Provides the behavior, features and SQL dialect of the PostgreSQL database platform
 * of the oldest supported version.
 */
class PostgreSQLPlatform extends AbstractPlatform
{
    /** @see https://www.postgresql.org/docs/current/collation.html */
    private const string DEFAULT_COLLATION = 'default';

    private bool $useBooleanTrueFalseStrings = true;

    /** @var string[][] PostgreSQL booleans literals */
    private array $booleanLiterals = [
        'true' => [
            't',
            'true',
            'y',
            'yes',
            'on',
            '1',
        ],
        'false' => [
            'f',
            'false',
            'n',
            'no',
            'off',
            '0',
        ],
    ];

    public function __construct()
    {
        parent::__construct(UnquotedIdentifierFolding::LOWER);
    }

    /**
     * PostgreSQL has different behavior with some drivers
     * with regard to how booleans have to be handled.
     *
     * Enables use of 'true'/'false' or otherwise 1 and 0 instead.
     */
    public function setUseBooleanTrueFalseStrings(bool $flag): void
    {
        $this->useBooleanTrueFalseStrings = $flag;
    }

    #[Override]
    public function getRegexpExpression(): string
    {
        return 'SIMILAR TO';
    }

    #[Override]
    public function getLocateExpression(string $string, string $substring, ?string $start = null): string
    {
        if ($start !== null) {
            $string = $this->getSubstringExpression($string, $start);

            return 'CASE WHEN (POSITION(' . $substring . ' IN ' . $string . ') = 0) THEN 0'
                . ' ELSE (POSITION(' . $substring . ' IN ' . $string . ') + ' . $start . ' - 1) END';
        }

        return sprintf('POSITION(%s IN %s)', $substring, $string);
    }

    #[Override]
    protected function getDateArithmeticIntervalExpression(
        string $date,
        string $operator,
        string $interval,
        DateIntervalUnit $unit,
    ): string {
        if ($unit === DateIntervalUnit::QUARTER) {
            $interval = $this->multiplyInterval($interval, 3);
            $unit     = DateIntervalUnit::MONTH;
        }

        return '(' . $date . ' ' . $operator . ' (' . $interval . " || ' " . $unit->value . "')::interval)";
    }

    #[Override]
    public function getDateDiffExpression(string $date1, string $date2): string
    {
        return '(DATE(' . $date1 . ')-DATE(' . $date2 . '))';
    }

    #[Override]
    public function getCurrentDatabaseExpression(): string
    {
        return 'CURRENT_DATABASE()';
    }

    #[Override]
    public function supportsSequences(): bool
    {
        return true;
    }

    #[Override]
    public function supportsSchemas(): bool
    {
        return true;
    }

    #[Override]
    public function supportsIdentityColumns(): bool
    {
        return true;
    }

    #[Override]
    protected function supportsCommentOnStatement(): bool
    {
        return true;
    }

    #[Override]
    protected function getPrimaryKeyConstraintDeclarationSQL(PrimaryKeyConstraint $constraint): string
    {
        $this->ensurePrimaryKeyConstraintIsClustered($constraint);

        return parent::getPrimaryKeyConstraintDeclarationSQL($constraint);
    }

    #[Override]
    protected function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        $query = '';

        $matchType = $foreignKey->getMatchType();
        if ($matchType !== MatchType::SIMPLE) {
            $query .= ' MATCH ' . $matchType->toSQL();
        }

        $query .= parent::getAdvancedForeignKeyOptionsSQL($foreignKey);

        $deferrability = $foreignKey->getDeferrability();
        if ($deferrability !== Deferrability::NOT_DEFERRABLE) {
            $query = ' ' . $deferrability->toSQL();
        }

        return $query;
    }

    #[Override]
    public function getAlterTableSQL(TableDiff $diff): array
    {
        $sql         = [];
        $commentsSQL = [];

        $table        = $diff->getOldTable();
        $tableName    = $table->getObjectName();
        $tableNameSQL = $tableName->toSQL($this);

        foreach ($diff->getDroppedForeignKeyConstraintNames() as $constraintName) {
            $sql[] = $this->getDropForeignKeySQL($constraintName->toSQL($this), $tableNameSQL);
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

        foreach ($diff->getAddedColumns() as $addedColumn) {
            $query = 'ADD ' . $this->getColumnDeclarationSQL($addedColumn->toArray());

            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;

            $comment = $addedColumn->getComment();

            if ($comment === '') {
                continue;
            }

            $commentsSQL[] = $this->getCommentOnColumnSQL(
                $tableNameSQL,
                $addedColumn->getObjectName()->toSQL($this),
                $comment,
            );
        }

        foreach ($diff->getDroppedColumns() as $droppedColumn) {
            $query = 'DROP ' . $droppedColumn->getObjectName()->toSQL($this);
            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
        }

        foreach ($diff->getChangedColumns() as $columnDiff) {
            $oldColumn = $columnDiff->getOldColumn();
            $newColumn = $columnDiff->getNewColumn();

            $oldColumnName = $oldColumn->getObjectName()->toSQL($this);
            $newColumnName = $newColumn->getObjectName()->toSQL($this);

            if ($columnDiff->hasNameChanged()) {
                $sql = array_merge(
                    $sql,
                    $this->getRenameColumnSQL($tableNameSQL, $oldColumnName, $newColumnName),
                );
            }

            $newTypeSQLDeclaration = $this->getTypeSQLDeclaration($newColumn);
            $oldTypeSQLDeclaration = $this->getTypeSQLDeclaration($oldColumn);

            $newCollation = $newColumn->getCollation() ?? self::DEFAULT_COLLATION;
            $oldCollation = $oldColumn->getCollation() ?? self::DEFAULT_COLLATION;

            $typeChanged      = $oldTypeSQLDeclaration !== $newTypeSQLDeclaration;
            $collationChanged = $oldCollation !== $newCollation;
            if ($typeChanged || $collationChanged) {
                $query = 'ALTER ' . $newColumnName . ' TYPE ' . $newTypeSQLDeclaration;
                if (! $typeChanged || $newCollation !== self::DEFAULT_COLLATION) {
                    $query .= ' ' . $this->getColumnCollationDeclarationSQL($newCollation);
                }

                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
            }

            if ($columnDiff->hasDefaultChanged()) {
                $defaultClause = $newColumn->getDefault() === null
                    ? ' DROP DEFAULT'
                    : ' SET' . $this->getDefaultValueDeclarationSQL($newColumn->toArray());

                $query = 'ALTER ' . $newColumnName . $defaultClause;
                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
            }

            if ($columnDiff->hasNotNullChanged()) {
                $query = 'ALTER ' . $newColumnName . ' ' . ($newColumn->getNotnull() ? 'SET' : 'DROP') . ' NOT NULL';
                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
            }

            if ($columnDiff->hasAutoIncrementChanged()) {
                if ($newColumn->getAutoincrement()) {
                    $query = 'ADD GENERATED BY DEFAULT AS IDENTITY';
                } else {
                    $query = 'DROP IDENTITY';
                }

                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ALTER ' . $newColumnName . ' ' . $query;
            }

            if (! $columnDiff->hasCommentChanged()) {
                continue;
            }

            $commentsSQL[] = $this->getCommentOnColumnSQL(
                $tableNameSQL,
                $newColumn->getObjectName()->toSQL($this),
                $newColumn->getComment(),
            );
        }

        $addedPrimaryKeyConstraint = $diff->getAddedPrimaryKeyConstraint();

        if ($addedPrimaryKeyConstraint !== null) {
            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ADD '
                . $this->getPrimaryKeyConstraintDeclarationSQL($addedPrimaryKeyConstraint);
        }

        foreach ($diff->getAddedForeignKeys() as $foreignKey) {
            $sql[] = $this->getCreateForeignKeySQL($foreignKey, $tableNameSQL);
        }

        foreach ($diff->getAddedIndexes() as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $tableNameSQL);
        }

        foreach ($diff->getRenamedIndexes() as $oldIndexName => $index) {
            $sql = array_merge(
                $sql,
                $this->getRenameIndexSQL($oldIndexName, $index, $tableNameSQL),
            );
        }

        return array_merge($sql, $commentsSQL);
    }

    private function getTypeSQLDeclaration(Column $column): string
    {
        $type = $column->getType();

        // SERIAL/BIGSERIAL are not "real" types and we can't alter a column to that type
        $columnDefinition                  = $column->toArray();
        $columnDefinition['autoincrement'] = false;

        return $type->getSQLDeclaration($columnDefinition, $this);
    }

    #[Override]
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, string $tableName): array
    {
        $parsedOldIndexName = $this->parseUnqualifiedName($oldIndexName);
        $parsedTableName    = $this->parseOptionallyQualifiedName($tableName);

        return [
            sprintf(
                'ALTER INDEX %s RENAME TO %s',
                $this->deriveQualifier($parsedOldIndexName, $parsedTableName)->toSQL($this),
                $index->getObjectName()->toSQL($this),
            ),
        ];
    }

    #[Override]
    public function getCreateSequenceSQL(Sequence $sequence): string
    {
        return 'CREATE SEQUENCE ' . $sequence->getObjectName()->toSQL($this) .
            ' INCREMENT BY ' . $sequence->getAllocationSize() .
            ' MINVALUE ' . $sequence->getInitialValue() .
            ' START ' . $sequence->getInitialValue() .
            $this->getSequenceCacheSQL($sequence->getCacheSize());
    }

    #[Override]
    public function getAlterSequenceSQL(Sequence $sequence): string
    {
        return 'ALTER SEQUENCE ' . $sequence->getObjectName()->toSQL($this) .
            ' INCREMENT BY ' . $sequence->getAllocationSize() .
            $this->getSequenceCacheSQL($sequence->getCacheSize());
    }

    /**
     * Cache definition for sequences
     */
    private function getSequenceCacheSQL(?int $cacheSize): string
    {
        if ($cacheSize > 1) {
            return ' CACHE ' . $cacheSize;
        }

        return '';
    }

    #[Override]
    public function getDropSequenceSQL(string $name): string
    {
        return parent::getDropSequenceSQL($name) . ' CASCADE';
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

        return sprintf(
            'DROP INDEX %s',
            $this->deriveQualifier($parsedIndexName, $parsedTableName)->toSQL($this),
        );
    }

    #[Override]
    protected function _getCreateTableSQL(OptionallyQualifiedName $tableName, array $columns, array $parameters): array
    {
        $elements = [];

        foreach ($columns as $column) {
            $elements[] = $this->getColumnDeclarationSQL($column);
        }

        if (isset($parameters['primaryKey'])) {
            $elements[] = $this->getPrimaryKeyConstraintDeclarationSQL($parameters['primaryKey']);
        }

        $unlogged = isset($parameters['unlogged']) && $parameters['unlogged'] === true ? ' UNLOGGED' : '';

        $query = 'CREATE' . $unlogged . ' TABLE ' . $tableName->toSQL($this) . ' (' . implode(', ', $elements) . ')';

        $sql = [$query];

        $tableNameSQL = $tableName->toSQL($this);

        foreach ($parameters['indexes'] as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $tableNameSQL);
        }

        foreach ($parameters['uniqueConstraints'] as $uniqueConstraint) {
            $sql[] = $this->getCreateUniqueConstraintSQL($uniqueConstraint, $tableNameSQL);
        }

        foreach ($parameters['foreignKeys'] as $definition) {
            $sql[] = $this->getCreateForeignKeySQL($definition, $tableNameSQL);
        }

        return $sql;
    }

    #[Override]
    public function getCreateIndexSQL(Index $index, string $tableName): string
    {
        $this->ensureIndexHasNoColumnLengths($index);
        $this->ensureIndexIsNotFulltext($index);
        $this->ensureIndexIsNotSpatial($index);
        $this->ensureIndexIsNotClustered($index);

        return parent::getCreateIndexSQL($index, $tableName);
    }

    /**
     * Converts a single boolean value.
     *
     * First converts the value to its native PHP boolean type
     * and passes it to the given callback function to be reconverted
     * into any custom representation.
     *
     * @param mixed    $value    The value to convert.
     * @param callable $callback The callback function to use for converting the real boolean value.
     *
     * @throws UnexpectedValueException
     */
    private function convertSingleBooleanValue(mixed $value, callable $callback): mixed
    {
        if ($value === null) {
            return $callback(null);
        }

        if (is_bool($value) || is_numeric($value)) {
            return $callback((bool) $value);
        }

        if (! is_string($value)) {
            return $callback(true);
        }

        /**
         * Better safe than sorry: http://php.net/in_array#106319
         */
        if (in_array(strtolower(trim($value)), $this->booleanLiterals['false'], true)) {
            return $callback(false);
        }

        if (in_array(strtolower(trim($value)), $this->booleanLiterals['true'], true)) {
            return $callback(true);
        }

        throw new UnexpectedValueException(sprintf(
            'Unrecognized boolean literal, %s given.',
            $value,
        ));
    }

    /**
     * Converts one or multiple boolean values.
     *
     * First converts the value(s) to their native PHP boolean type
     * and passes them to the given callback function to be reconverted
     * into any custom representation.
     *
     * @param mixed    $item     The value(s) to convert.
     * @param callable $callback The callback function to use for converting the real boolean value(s).
     */
    private function doConvertBooleans(mixed $item, callable $callback): mixed
    {
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                $item[$key] = $this->convertSingleBooleanValue($value, $callback);
            }

            return $item;
        }

        return $this->convertSingleBooleanValue($item, $callback);
    }

    /** Postgres wants boolean values converted to the strings 'true'/'false'. */
    #[Override]
    public function convertBooleans(mixed $item): mixed
    {
        if (! $this->useBooleanTrueFalseStrings) {
            return parent::convertBooleans($item);
        }

        return $this->doConvertBooleans(
            $item,
            /** @param mixed $value */
            static function ($value): string {
                if ($value === null) {
                    return 'NULL';
                }

                return $value === true ? 'true' : 'false';
            },
        );
    }

    #[Override]
    public function convertBooleansToDatabaseValue(mixed $item): mixed
    {
        if (! $this->useBooleanTrueFalseStrings) {
            return parent::convertBooleansToDatabaseValue($item);
        }

        return $this->doConvertBooleans(
            $item,
            /** @param mixed $value */
            static function ($value): ?int {
                return $value === null ? null : (int) $value;
            },
        );
    }

    /**
     * @param T $item
     *
     * @return (T is null ? null : bool)
     *
     * @template T
     */
    #[Override]
    public function convertFromBoolean(mixed $item): ?bool
    {
        if (in_array($item, $this->booleanLiterals['false'], true)) {
            return false;
        }

        return parent::convertFromBoolean($item);
    }

    #[Override]
    public function getSequenceNextValSQL(string $sequenceName): string
    {
        $parsedName = $this->parseUnqualifiedName($sequenceName);

        return sprintf('SELECT NEXTVAL(%s)', $this->quoteStringLiteral($parsedName->toSQL($this)));
    }

    #[Override]
    public function getSetTransactionIsolationSQL(TransactionIsolationLevel $level): string
    {
        return 'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL '
            . $this->_getTransactionIsolationLevelSQL($level);
    }

    #[Override]
    public function getBooleanTypeDeclarationSQL(array $column): string
    {
        return 'BOOLEAN';
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
        return 'UUID';
    }

    #[Override]
    public function getDateTimeTypeDeclarationSQL(array $column): string
    {
        return 'TIMESTAMP(0) WITHOUT TIME ZONE';
    }

    #[Override]
    public function getDateTimeTzTypeDeclarationSQL(array $column): string
    {
        return 'TIMESTAMP(0) WITH TIME ZONE';
    }

    #[Override]
    public function getDateTypeDeclarationSQL(array $column): string
    {
        return 'DATE';
    }

    #[Override]
    public function getTimeTypeDeclarationSQL(array $column): string
    {
        return 'TIME(0) WITHOUT TIME ZONE';
    }

    #[Override]
    protected function _getCommonIntegerTypeDeclarationSQL(array $column): string
    {
        if (! empty($column['autoincrement'])) {
            return ' GENERATED BY DEFAULT AS IDENTITY';
        }

        return '';
    }

    #[Override]
    protected function getVarcharTypeDeclarationSQLSnippet(?int $length): string
    {
        $sql = 'VARCHAR';

        if ($length !== null) {
            $sql .= sprintf('(%d)', $length);
        }

        return $sql;
    }

    #[Override]
    protected function getBinaryTypeDeclarationSQLSnippet(?int $length): string
    {
        return 'BYTEA';
    }

    #[Override]
    protected function getVarbinaryTypeDeclarationSQLSnippet(?int $length): string
    {
        return 'BYTEA';
    }

    #[Override]
    public function getClobTypeDeclarationSQL(array $column): string
    {
        return 'TEXT';
    }

    #[Override]
    public function getDateTimeTzFormatString(): string
    {
        return 'Y-m-d H:i:sO';
    }

    #[Override]
    public function getEmptyIdentityInsertSQL(string $quotedTableName, string $quotedIdentifierColumnName): string
    {
        return 'INSERT INTO ' . $quotedTableName . ' (' . $quotedIdentifierColumnName . ') VALUES (DEFAULT)';
    }

    #[Override]
    public function getTruncateTableSQL(string $tableName, bool $cascade = false): string
    {
        $parsedName = $this->parseOptionallyQualifiedName($tableName);

        $sql = 'TRUNCATE ' . $parsedName->toSQL($this);

        if ($cascade) {
            $sql .= ' CASCADE';
        }

        return $sql;
    }

    #[Override]
    protected function initializeDoctrineTypeMappings(): void
    {
        $this->doctrineTypeMapping = [
            'bigint'           => Types::BIGINT,
            'bigserial'        => Types::BIGINT,
            'bool'             => Types::BOOLEAN,
            'boolean'          => Types::BOOLEAN,
            'bpchar'           => Types::STRING,
            'bytea'            => Types::BLOB,
            'char'             => Types::STRING,
            'date'             => Types::DATE_MUTABLE,
            'decimal'          => Types::DECIMAL,
            'double precision' => Types::FLOAT,
            'float'            => Types::FLOAT,
            'float4'           => Types::SMALLFLOAT,
            'float8'           => Types::FLOAT,
            'inet'             => Types::STRING,
            'int'              => Types::INTEGER,
            'int2'             => Types::SMALLINT,
            'int4'             => Types::INTEGER,
            'int8'             => Types::BIGINT,
            'integer'          => Types::INTEGER,
            'interval'         => Types::STRING,
            'json'             => Types::JSON,
            'jsonb'            => Types::JSONB,
            'money'            => Types::DECIMAL,
            'numeric'          => Types::DECIMAL,
            'serial'           => Types::INTEGER,
            'serial4'          => Types::INTEGER,
            'serial8'          => Types::BIGINT,
            'real'             => Types::SMALLFLOAT,
            'smallint'         => Types::SMALLINT,
            'text'             => Types::TEXT,
            'time'             => Types::TIME_MUTABLE,
            'timestamp'        => Types::DATETIME_MUTABLE,
            'timestamptz'      => Types::DATETIMETZ_MUTABLE,
            'timetz'           => Types::TIME_MUTABLE,
            'tsvector'         => Types::TEXT,
            'uuid'             => Types::GUID,
            'varchar'          => Types::STRING,
            '_varchar'         => Types::STRING,
        ];
    }

    #[Override]
    public function getBlobTypeDeclarationSQL(array $column): string
    {
        return 'BYTEA';
    }

    #[Override]
    protected function getDefaultValueDeclarationSQL(array $column): string
    {
        if (isset($column['autoincrement']) && $column['autoincrement'] === true) {
            return '';
        }

        return parent::getDefaultValueDeclarationSQL($column);
    }

    #[Override]
    protected function supportsColumnCollation(): bool
    {
        return true;
    }

    #[Override]
    public function getJsonTypeDeclarationSQL(array $column): string
    {
        return 'JSON';
    }

    #[Override]
    public function getJsonbTypeDeclarationSQL(array $column): string
    {
        return 'JSONB';
    }

    #[Override]
    public function createMetadataProvider(Connection $connection): PostgreSQLMetadataProvider
    {
        return new PostgreSQLMetadataProvider($connection, $this);
    }

    #[Override]
    public function createSchemaManager(Connection $connection): PostgreSQLSchemaManager
    {
        return new PostgreSQLSchemaManager($connection, $this);
    }
}
