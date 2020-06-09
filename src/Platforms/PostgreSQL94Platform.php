<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

use function array_diff;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function sprintf;
use function strpos;
use function strtolower;
use function trim;

/**
 * Provides the behavior, features and SQL dialect of the PostgreSQL 9.4+ database platform.
 */
class PostgreSQL94Platform extends AbstractPlatform
{
    /** @var bool */
    private $useBooleanTrueFalseStrings = true;

    /** @var string[][] PostgreSQL booleans literals */
    private $booleanLiterals = [
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

    public function getNowExpression(): string
    {
        return 'LOCALTIMESTAMP(0)';
    }

    public function getRegexpExpression(): string
    {
        return 'SIMILAR TO';
    }

    public function getLocateExpression(string $string, string $substring, ?string $start = null): string
    {
        if ($start !== null) {
            $string = $this->getSubstringExpression($string, $start);

            return 'CASE WHEN (POSITION(' . $substring . ' IN ' . $string . ') = 0) THEN 0 ELSE (POSITION(' . $substring . ' IN ' . $string . ') + ' . $start . ' - 1) END';
        }

        return sprintf('POSITION(%s IN %s)', $substring, $string);
    }

    protected function getDateArithmeticIntervalExpression(string $date, string $operator, string $interval, string $unit): string
    {
        if ($unit === DateIntervalUnit::QUARTER) {
            $interval = $this->multiplyInterval($interval, 3);
            $unit     = DateIntervalUnit::MONTH;
        }

        return '(' . $date . ' ' . $operator . ' (' . $interval . " || ' " . $unit . "')::interval)";
    }

    public function getDateDiffExpression(string $date1, string $date2): string
    {
        return '(DATE(' . $date1 . ')-DATE(' . $date2 . '))';
    }

    public function getCurrentDatabaseExpression(): string
    {
        return 'CURRENT_DATABASE()';
    }

    public function supportsSequences(): bool
    {
        return true;
    }

    public function supportsSchemas(): bool
    {
        return true;
    }

    public function getDefaultSchemaName(): string
    {
        return 'public';
    }

    public function supportsIdentityColumns(): bool
    {
        return true;
    }

    public function supportsPartialIndexes(): bool
    {
        return true;
    }

    public function usesSequenceEmulatedIdentityColumns(): bool
    {
        return true;
    }

    public function getIdentitySequenceName(string $tableName, string $columnName): string
    {
        return $tableName . '_' . $columnName . '_seq';
    }

    public function supportsCommentOnStatement(): bool
    {
        return true;
    }

    public function prefersSequences(): bool
    {
        return true;
    }

    public function hasNativeGuidType(): bool
    {
        return true;
    }

    public function getListDatabasesSQL(): string
    {
        return 'SELECT datname FROM pg_database';
    }

    public function getListNamespacesSQL(): string
    {
        return "SELECT schema_name AS nspname
                FROM   information_schema.schemata
                WHERE  schema_name NOT LIKE 'pg\_%'
                AND    schema_name != 'information_schema'";
    }

    public function getListSequencesSQL(string $database): string
    {
        return "SELECT sequence_name AS relname,
                       sequence_schema AS schemaname
                FROM   information_schema.sequences
                WHERE  sequence_schema NOT LIKE 'pg\_%'
                AND    sequence_schema != 'information_schema'";
    }

    public function getListTablesSQL(): string
    {
        return "SELECT quote_ident(table_name) AS table_name,
                       table_schema AS schema_name
                FROM   information_schema.tables
                WHERE  table_schema NOT LIKE 'pg\_%'
                AND    table_schema != 'information_schema'
                AND    table_name != 'geometry_columns'
                AND    table_name != 'spatial_ref_sys'
                AND    table_type != 'VIEW'";
    }

    public function getListViewsSQL(string $database): string
    {
        return 'SELECT quote_ident(table_name) AS viewname,
                       table_schema AS schemaname,
                       view_definition AS definition
                FROM   information_schema.views
                WHERE  view_definition IS NOT NULL';
    }

    public function getListTableForeignKeysSQL(string $table, ?string $database = null): string
    {
        return 'SELECT quote_ident(r.conname) as conname, pg_catalog.pg_get_constraintdef(r.oid, true) as condef
                  FROM pg_catalog.pg_constraint r
                  WHERE r.conrelid =
                  (
                      SELECT c.oid
                      FROM pg_catalog.pg_class c, pg_catalog.pg_namespace n
                      WHERE ' . $this->getTableWhereClause($table) . " AND n.oid = c.relnamespace
                  )
                  AND r.contype = 'f'";
    }

    public function getCreateViewSQL(string $name, string $sql): string
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    public function getDropViewSQL(string $name): string
    {
        return 'DROP VIEW ' . $name;
    }

    public function getListTableConstraintsSQL(string $table): string
    {
        $table = new Identifier($table);
        $table = $this->quoteStringLiteral($table->getName());

        return sprintf(
            <<<'SQL'
SELECT
    quote_ident(relname) as relname
FROM
    pg_class
WHERE oid IN (
    SELECT indexrelid
    FROM pg_index, pg_class
    WHERE pg_class.relname = %s
        AND pg_class.oid = pg_index.indrelid
        AND (indisunique = 't' OR indisprimary = 't')
    )
SQL
            ,
            $table
        );
    }

    /**
     * {@inheritDoc}
     *
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaPgsqlReader.html
     */
    public function getListTableIndexesSQL(string $table, ?string $currentDatabase = null): string
    {
        return 'SELECT quote_ident(relname) as relname, pg_index.indisunique, pg_index.indisprimary,
                       pg_index.indkey, pg_index.indrelid,
                       pg_get_expr(indpred, indrelid) AS where
                 FROM pg_class, pg_index
                 WHERE oid IN (
                    SELECT indexrelid
                    FROM pg_index si, pg_class sc, pg_namespace sn
                    WHERE ' . $this->getTableWhereClause($table, 'sc', 'sn') . ' AND sc.oid=si.indrelid AND sc.relnamespace = sn.oid
                 ) AND pg_index.indexrelid = oid';
    }

    private function getTableWhereClause(string $table, string $classAlias = 'c', string $namespaceAlias = 'n'): string
    {
        $whereClause = $namespaceAlias . ".nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast') AND ";
        if (strpos($table, '.') !== false) {
            [$schema, $table] = explode('.', $table);
            $schema           = $this->quoteStringLiteral($schema);
        } else {
            $schema = 'ANY(current_schemas(false))';
        }

        $table = new Identifier($table);
        $table = $this->quoteStringLiteral($table->getName());

        return $whereClause . sprintf(
            '%s.relname = %s AND %s.nspname = %s',
            $classAlias,
            $table,
            $namespaceAlias,
            $schema
        );
    }

    public function getListTableColumnsSQL(string $table, ?string $database = null): string
    {
        return "SELECT
                    a.attnum,
                    quote_ident(a.attname) AS field,
                    t.typname AS type,
                    format_type(a.atttypid, a.atttypmod) AS complete_type,
                    (SELECT tc.collcollate FROM pg_catalog.pg_collation tc WHERE tc.oid = a.attcollation) AS collation,
                    (SELECT t1.typname FROM pg_catalog.pg_type t1 WHERE t1.oid = t.typbasetype) AS domain_type,
                    (SELECT format_type(t2.typbasetype, t2.typtypmod) FROM
                      pg_catalog.pg_type t2 WHERE t2.typtype = 'd' AND t2.oid = a.atttypid) AS domain_complete_type,
                    a.attnotnull AS isnotnull,
                    (SELECT 't'
                     FROM pg_index
                     WHERE c.oid = pg_index.indrelid
                        AND pg_index.indkey[0] = a.attnum
                        AND pg_index.indisprimary = 't'
                    ) AS pri,
                    (SELECT pg_get_expr(adbin, adrelid)
                     FROM pg_attrdef
                     WHERE c.oid = pg_attrdef.adrelid
                        AND pg_attrdef.adnum=a.attnum
                    ) AS default,
                    (SELECT pg_description.description
                        FROM pg_description WHERE pg_description.objoid = c.oid AND a.attnum = pg_description.objsubid
                    ) AS comment
                    FROM pg_attribute a, pg_class c, pg_type t, pg_namespace n
                    WHERE " . $this->getTableWhereClause($table, 'c', 'n') . '
                        AND a.attnum > 0
                        AND a.attrelid = c.oid
                        AND a.atttypid = t.oid
                        AND n.oid = c.relnamespace
                    ORDER BY a.attnum';
    }

    public function getCreateDatabaseSQL(string $database): string
    {
        return 'CREATE DATABASE ' . $database;
    }

    /**
     * Returns the SQL statement for disallowing new connections on the given database.
     *
     * This is useful to force DROP DATABASE operations which could fail because of active connections.
     *
     * @param string $database The name of the database to disallow new connections for.
     */
    public function getDisallowDatabaseConnectionsSQL(string $database): string
    {
        return "UPDATE pg_database SET datallowconn = 'false' WHERE datname = " . $this->quoteStringLiteral($database);
    }

    /**
     * Returns the SQL statement for closing currently active connections on the given database.
     *
     * This is useful to force DROP DATABASE operations which could fail because of active connections.
     *
     * @param string $database The name of the database to close currently active connections for.
     */
    public function getCloseActiveDatabaseConnectionsSQL(string $database): string
    {
        return 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '
            . $this->quoteStringLiteral($database);
    }

    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        $query = '';

        if ($foreignKey->hasOption('match')) {
            $query .= ' MATCH ' . $foreignKey->getOption('match');
        }

        $query .= parent::getAdvancedForeignKeyOptionsSQL($foreignKey);

        if ($foreignKey->hasOption('deferrable') && $foreignKey->getOption('deferrable') !== false) {
            $query .= ' DEFERRABLE';
        } else {
            $query .= ' NOT DEFERRABLE';
        }

        if (
            $foreignKey->hasOption('deferred') && $foreignKey->getOption('deferred') !== false
        ) {
            $query .= ' INITIALLY DEFERRED';
        } else {
            $query .= ' INITIALLY IMMEDIATE';
        }

        return $query;
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff): array
    {
        $sql         = [];
        $commentsSQL = [];
        $columnSql   = [];

        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $query = 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;

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

            $query = 'DROP ' . $column->getQuotedName($this);
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
        }

        foreach ($diff->changedColumns as $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            if ($this->isUnchangedBinaryColumn($columnDiff)) {
                continue;
            }

            $oldColumnName = $columnDiff->getOldColumnName()->getQuotedName($this);
            $column        = $columnDiff->column;

            if ($columnDiff->hasChanged('type') || $columnDiff->hasChanged('precision') || $columnDiff->hasChanged('scale') || $columnDiff->hasChanged('fixed')) {
                $type = $column->getType();

                // SERIAL/BIGSERIAL are not "real" types and we can't alter a column to that type
                $columnDefinition                  = $column->toArray();
                $columnDefinition['autoincrement'] = false;

                // here was a server version check before, but DBAL API does not support this anymore.
                $query = 'ALTER ' . $oldColumnName . ' TYPE ' . $type->getSQLDeclaration($columnDefinition, $this);
                $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
            }

            if ($columnDiff->hasChanged('default') || $this->typeChangeBreaksDefaultValue($columnDiff)) {
                $defaultClause = $column->getDefault() === null
                    ? ' DROP DEFAULT'
                    : ' SET' . $this->getDefaultValueDeclarationSQL($column->toArray());
                $query         = 'ALTER ' . $oldColumnName . $defaultClause;
                $sql[]         = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
            }

            if ($columnDiff->hasChanged('notnull')) {
                $query = 'ALTER ' . $oldColumnName . ' ' . ($column->getNotnull() ? 'SET' : 'DROP') . ' NOT NULL';
                $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
            }

            if ($columnDiff->hasChanged('autoincrement')) {
                if ($column->getAutoincrement()) {
                    // add autoincrement
                    $seqName = $this->getIdentitySequenceName($diff->name, $oldColumnName);

                    $sql[] = 'CREATE SEQUENCE ' . $seqName;
                    $sql[] = "SELECT setval('" . $seqName . "', (SELECT MAX(" . $oldColumnName . ') FROM ' . $diff->getName($this)->getQuotedName($this) . '))';
                    $query = 'ALTER ' . $oldColumnName . " SET DEFAULT nextval('" . $seqName . "')";
                    $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
                } else {
                    // Drop autoincrement, but do NOT drop the sequence. It might be re-used by other tables or have
                    $query = 'ALTER ' . $oldColumnName . ' DROP DEFAULT';
                    $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
                }
            }

            $newComment = $this->getColumnComment($column);
            $oldComment = $this->getOldColumnComment($columnDiff);

            if ($columnDiff->hasChanged('comment') || ($columnDiff->fromColumn !== null && $oldComment !== $newComment)) {
                $commentsSQL[] = $this->getCommentOnColumnSQL(
                    $diff->getName($this)->getQuotedName($this),
                    $column->getQuotedName($this),
                    $newComment
                );
            }

            if (! $columnDiff->hasChanged('length')) {
                continue;
            }

            $query = 'ALTER ' . $oldColumnName . ' TYPE ' . $column->getType()->getSQLDeclaration($column->toArray(), $this);
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = new Identifier($oldColumnName);

            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) .
                ' RENAME COLUMN ' . $oldColumnName->getQuotedName($this) . ' TO ' . $column->getQuotedName($this);
        }

        $tableSql = [];

        if (! $this->onSchemaAlterTable($diff, $tableSql)) {
            $sql = array_merge($sql, $commentsSQL);

            $newName = $diff->getNewName();

            if ($newName !== null) {
                $sql[] = sprintf(
                    'ALTER TABLE %s RENAME TO %s',
                    $diff->getName($this)->getQuotedName($this),
                    $newName->getQuotedName($this)
                );
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
     * Checks whether a given column diff is a logically unchanged binary type column.
     *
     * Used to determine whether a column alteration for a binary type column can be skipped.
     * Doctrine's {@link \Doctrine\DBAL\Types\BinaryType} and {@link \Doctrine\DBAL\Types\BlobType}
     * are mapped to the same database column type on this platform as this platform
     * does not have a native VARBINARY/BINARY column type. Therefore the {@link \Doctrine\DBAL\Schema\Comparator}
     * might detect differences for binary type columns which do not have to be propagated
     * to database as there actually is no difference at database level.
     *
     * @param ColumnDiff $columnDiff The column diff to check against.
     *
     * @return bool True if the given column diff is an unchanged binary type column, false otherwise.
     */
    private function isUnchangedBinaryColumn(ColumnDiff $columnDiff): bool
    {
        $columnType = $columnDiff->column->getType();

        if (! $columnType instanceof BinaryType && ! $columnType instanceof BlobType) {
            return false;
        }

        $fromColumn = $columnDiff->fromColumn;

        if ($fromColumn !== null) {
            $fromColumnType = $fromColumn->getType();

            if (! $fromColumnType instanceof BinaryType && ! $fromColumnType instanceof BlobType) {
                return false;
            }

            return count(array_diff($columnDiff->changedProperties, ['type', 'length', 'fixed'])) === 0;
        }

        if ($columnDiff->hasChanged('type')) {
            return false;
        }

        return count(array_diff($columnDiff->changedProperties, ['length', 'fixed'])) === 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, string $tableName): array
    {
        if (strpos($tableName, '.') !== false) {
            [$schema]     = explode('.', $tableName);
            $oldIndexName = $schema . '.' . $oldIndexName;
        }

        return ['ALTER INDEX ' . $oldIndexName . ' RENAME TO ' . $index->getQuotedName($this)];
    }

    public function getCreateSequenceSQL(Sequence $sequence): string
    {
        return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this) .
            ' INCREMENT BY ' . $sequence->getAllocationSize() .
            ' MINVALUE ' . $sequence->getInitialValue() .
            ' START ' . $sequence->getInitialValue() .
            $this->getSequenceCacheSQL($sequence);
    }

    public function getAlterSequenceSQL(Sequence $sequence): string
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this) .
            ' INCREMENT BY ' . $sequence->getAllocationSize() .
            $this->getSequenceCacheSQL($sequence);
    }

    /**
     * Cache definition for sequences
     */
    private function getSequenceCacheSQL(Sequence $sequence): string
    {
        if ($sequence->getCache() > 1) {
            return ' CACHE ' . $sequence->getCache();
        }

        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getDropSequenceSQL($sequence): string
    {
        if ($sequence instanceof Sequence) {
            $sequence = $sequence->getQuotedName($this);
        }

        return 'DROP SEQUENCE ' . $sequence . ' CASCADE';
    }

    public function getCreateSchemaSQL(string $schemaName): string
    {
        return 'CREATE SCHEMA ' . $schemaName;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropForeignKeySQL($foreignKey, $table): string
    {
        return $this->getDropConstraintSQL($foreignKey, $table);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL(string $tableName, array $columns, array $options = []): array
    {
        $queryFields = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['primary']) && ! empty($options['primary'])) {
            $keyColumns   = array_unique(array_values($options['primary']));
            $queryFields .= ', PRIMARY KEY(' . implode(', ', $keyColumns) . ')';
        }

        $query = 'CREATE TABLE ' . $tableName . ' (' . $queryFields . ')';

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

        return $sql;
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
     * @return mixed
     *
     * @throws UnexpectedValueException
     */
    private function convertSingleBooleanValue($value, callable $callback)
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
            $value
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
     *
     * @return mixed
     */
    private function doConvertBooleans($item, callable $callback)
    {
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                $item[$key] = $this->convertSingleBooleanValue($value, $callback);
            }

            return $item;
        }

        return $this->convertSingleBooleanValue($item, $callback);
    }

    /**
     * {@inheritDoc}
     *
     * Postgres wants boolean values converted to the strings 'true'/'false'.
     */
    public function convertBooleans($item)
    {
        if (! $this->useBooleanTrueFalseStrings) {
            return parent::convertBooleans($item);
        }

        return $this->doConvertBooleans(
            $item,
            static function ($boolean): string {
                if ($boolean === null) {
                    return 'NULL';
                }

                return $boolean === true ? 'true' : 'false';
            }
        );
    }

    /**
     * {@inheritDoc}
     */
    public function convertBooleansToDatabaseValue($item)
    {
        if (! $this->useBooleanTrueFalseStrings) {
            return parent::convertBooleansToDatabaseValue($item);
        }

        return $this->doConvertBooleans(
            $item,
            static function ($boolean): ?int {
                return $boolean === null ? null : (int) $boolean;
            }
        );
    }

    /**
     * {@inheritDoc}
     */
    public function convertFromBoolean($item): ?bool
    {
        if (in_array($item, $this->booleanLiterals['false'], true)) {
            return false;
        }

        return parent::convertFromBoolean($item);
    }

    public function getSequenceNextValSQL(string $sequenceName): string
    {
        return "SELECT NEXTVAL('" . $sequenceName . "')";
    }

    public function getSetTransactionIsolationSQL(int $level): string
    {
        return 'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL '
            . $this->_getTransactionIsolationLevelSQL($level);
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
        if (! empty($columnDef['autoincrement'])) {
            return 'SERIAL';
        }

        return 'INT';
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $columnDef): string
    {
        if (! empty($columnDef['autoincrement'])) {
            return 'BIGSERIAL';
        }

        return 'BIGINT';
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $columnDef): string
    {
        if (! empty($columnDef['autoincrement'])) {
            return 'SMALLSERIAL';
        }

        return 'SMALLINT';
    }

    /**
     * {@inheritDoc}
     */
    public function getGuidTypeDeclarationSQL(array $column): string
    {
        return 'UUID';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return 'TIMESTAMP(0) WITHOUT TIME ZONE';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return 'TIMESTAMP(0) WITH TIME ZONE';
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
        return 'TIME(0) WITHOUT TIME ZONE';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef): string
    {
        return '';
    }

    protected function getVarcharTypeDeclarationSQLSnippet(?int $length): string
    {
        $sql = 'VARCHAR';

        if ($length !== null) {
            $sql .= sprintf('(%d)', $length);
        }

        return $sql;
    }

    protected function getBinaryTypeDeclarationSQLSnippet(?int $length): string
    {
        return 'BYTEA';
    }

    protected function getVarbinaryTypeDeclarationSQLSnippet(?int $length): string
    {
        return 'BYTEA';
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field): string
    {
        return 'TEXT';
    }

    public function getName(): string
    {
        return 'postgresql';
    }

    /**
     * {@inheritDoc}
     *
     * PostgreSQL returns all column names in SQL result sets in lowercase.
     */
    public function getSQLResultCasing(string $column): string
    {
        return strtolower($column);
    }

    public function getDateTimeTzFormatString(): string
    {
        return 'Y-m-d H:i:sO';
    }

    public function getEmptyIdentityInsertSQL(string $tableName, string $identifierColumnName): string
    {
        return 'INSERT INTO ' . $tableName . ' (' . $identifierColumnName . ') VALUES (DEFAULT)';
    }

    public function getTruncateTableSQL(string $tableName, bool $cascade = false): string
    {
        $tableIdentifier = new Identifier($tableName);
        $sql             = 'TRUNCATE ' . $tableIdentifier->getQuotedName($this);

        if ($cascade) {
            $sql .= ' CASCADE';
        }

        return $sql;
    }

    public function getReadLockSQL(): string
    {
        return 'FOR SHARE';
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        $this->doctrineTypeMapping = [
            'bigint'           => 'bigint',
            'bigserial'        => 'bigint',
            'bool'             => 'boolean',
            'boolean'          => 'boolean',
            'bpchar'           => 'string',
            'bytea'            => 'blob',
            'char'             => 'string',
            'date'             => 'date',
            'datetime'         => 'datetime',
            'decimal'          => 'decimal',
            'double'           => 'float',
            'double precision' => 'float',
            'float'            => 'float',
            'float4'           => 'float',
            'float8'           => 'float',
            'inet'             => 'string',
            'int'              => 'integer',
            'int2'             => 'smallint',
            'int4'             => 'integer',
            'int8'             => 'bigint',
            'integer'          => 'integer',
            'interval'         => 'string',
            'json'             => 'json',
            'jsonb'            => 'json',
            'money'            => 'decimal',
            'numeric'          => 'decimal',
            'serial'           => 'integer',
            'serial4'          => 'integer',
            'serial8'          => 'bigint',
            'real'             => 'float',
            'smallint'         => 'smallint',
            'text'             => 'text',
            'time'             => 'time',
            'timestamp'        => 'datetime',
            'timestamptz'      => 'datetimetz',
            'timetz'           => 'time',
            'tsvector'         => 'text',
            'uuid'             => 'guid',
            'varchar'          => 'string',
            'year'             => 'date',
            '_varchar'         => 'string',
        ];
    }

    public function hasNativeJsonType(): bool
    {
        return true;
    }

    protected function getReservedKeywordsClass(): string
    {
        return Keywords\PostgreSQL94Keywords::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field): string
    {
        return 'BYTEA';
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValueDeclarationSQL(array $field): string
    {
        if ($this->isSerialField($field)) {
            return '';
        }

        return parent::getDefaultValueDeclarationSQL($field);
    }

    public function supportsColumnCollation(): bool
    {
        return true;
    }

    public function getColumnCollationDeclarationSQL(string $collation): string
    {
        return 'COLLATE ' . $this->quoteSingleIdentifier($collation);
    }

    /**
     * {@inheritdoc}
     */
    public function getJsonTypeDeclarationSQL(array $field): string
    {
        if (! empty($field['jsonb'])) {
            return 'JSONB';
        }

        return 'JSON';
    }

    /**
     * @param mixed[] $field
     */
    private function isSerialField(array $field): bool
    {
        return isset($field['type'], $field['autoincrement'])
            && $field['autoincrement'] === true
            && $this->isNumericType($field['type']);
    }

    /**
     * Check whether the type of a column is changed in a way that invalidates the default value for the column
     */
    private function typeChangeBreaksDefaultValue(ColumnDiff $columnDiff): bool
    {
        if ($columnDiff->fromColumn === null) {
            return $columnDiff->hasChanged('type');
        }

        $oldTypeIsNumeric = $this->isNumericType($columnDiff->fromColumn->getType());
        $newTypeIsNumeric = $this->isNumericType($columnDiff->column->getType());

        // default should not be changed when switching between numeric types and the default comes from a sequence
        return $columnDiff->hasChanged('type')
            && ! ($oldTypeIsNumeric && $newTypeIsNumeric && $columnDiff->column->getAutoincrement());
    }

    private function isNumericType(Type $type): bool
    {
        return $type instanceof IntegerType || $type instanceof BigIntType;
    }

    private function getOldColumnComment(ColumnDiff $columnDiff): ?string
    {
        if ($columnDiff->fromColumn === null) {
            return null;
        }

        return $this->getColumnComment($columnDiff->fromColumn);
    }

    public function getListTableMetadataSQL(string $table, ?string $schema = null): string
    {
        if ($schema !== null) {
            $table = $schema . '.' . $table;
        }

        return sprintf(
            <<<'SQL'
SELECT obj_description(%s::regclass) AS table_comment;
SQL
            ,
            $this->quoteStringLiteral($table)
        );
    }
}
