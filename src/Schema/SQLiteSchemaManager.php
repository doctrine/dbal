<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLite;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Exception\UnsupportedSchema;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_change_key_case;
use function array_column;
use function array_map;
use function array_merge;
use function assert;
use function count;
use function func_get_arg;
use function func_num_args;
use function implode;
use function is_string;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strcasecmp;
use function strtolower;
use function substr;
use function trim;

use const CASE_LOWER;

/**
 * SQLite SchemaManager.
 *
 * @extends AbstractSchemaManager<SQLitePlatform>
 */
class SQLiteSchemaManager extends AbstractSchemaManager
{
    public function createForeignKey(ForeignKeyConstraint $foreignKey, string $table): void
    {
        $table = $this->introspectTable($table);

        $this->alterTable(new TableDiff($table, addedForeignKeys: [$foreignKey]));
    }

    public function dropForeignKey(string $name, string $table): void
    {
        $table = $this->introspectTable($table);

        $foreignKey = $table->getForeignKey($name);

        $this->alterTable(new TableDiff($table, droppedForeignKeys: [$foreignKey]));
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $matchResult = preg_match('/^([^()]*)\\s*(\\(((\\d+)(,\\s*(\\d+))?)\\))?/', $tableColumn['type'], $matches);
        assert($matchResult === 1);

        $dbType = trim(strtolower($matches[1]));

        $length = $precision = null;
        $fixed  = $unsigned = false;
        $scale  = 0;

        if (isset($matches[4])) {
            if (isset($matches[6])) {
                $precision = (int) $matches[4];
                $scale     = (int) $matches[6];
            } else {
                $length = (int) $matches[4];
            }
        }

        if (str_contains($dbType, ' unsigned')) {
            $dbType   = str_replace(' unsigned', '', $dbType);
            $unsigned = true;
        }

        $type    = $this->platform->getDoctrineTypeMapping($dbType);
        $default = $tableColumn['dflt_value'];
        if ($default === 'NULL') {
            $default = null;
        }

        if ($default !== null) {
            // SQLite returns the default value as a literal expression, so we need to parse it
            if (preg_match('/^\'(.*)\'$/s', $default, $matches) === 1) {
                $default = str_replace("''", "'", $matches[1]);
            }
        }

        $notnull = (bool) $tableColumn['notnull'];

        if ($dbType === 'char') {
            $fixed = true;
        }

        $options = [
            'autoincrement' => $tableColumn['autoincrement'],
            'comment'   => $tableColumn['comment'],
            'length'    => $length,
            'unsigned'  => $unsigned,
            'fixed'     => $fixed,
            'notnull'   => $notnull,
            'default'   => $default,
            'precision' => $precision,
            'scale'     => $scale,
        ];

        $column = new Column($tableColumn['name'], Type::getType($type), $options);

        if ($type === Types::STRING || $type === Types::TEXT) {
            $column->setPlatformOption('collation', $tableColumn['collation'] ?? 'BINARY');
        }

        return $column;
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        return new View($view['name'], $view['sql']);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableForeignKeysList(array $rows): array
    {
        $list = [];
        foreach ($rows as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $id  = $row['id'];
            if (! isset($list[$id])) {
                $list[$id] = [
                    'name' => $row['constraint_name'],
                    'local' => [],
                    'foreign' => [],
                    'foreignTable' => $row['table'],
                    'onDelete' => $row['on_delete'],
                    'onUpdate' => $row['on_update'],
                    'deferrable' => $row['deferrable'],
                    'deferred' => $row['deferred'],
                ];
            }

            $list[$id]['local'][] = $row['from'];

            if ($row['to'] === null) {
                continue;
            }

            $list[$id]['foreign'][] = $row['to'];
        }

        foreach ($list as $id => $value) {
            if (count($value['foreign']) !== 0) {
                continue;
            }

            // Inferring a shorthand form for the foreign key constraint, where the "to" field is empty.
            // @see https://www.sqlite.org/foreignkeys.html#fk_indexes.
            // @phpstan-ignore missingType.checkedException
            $foreignTablePrimaryKeyColumnRows = $this->fetchPrimaryKeyConstraintColumns(
                '',
                OptionallyQualifiedName::quoted($value['foreignTable']),
            );

            if (count($foreignTablePrimaryKeyColumnRows) < 1) {
                throw UnsupportedSchema::sqliteMissingForeignKeyConstraintReferencedColumns(
                    $value['name'],
                    $value['foreignTable'],
                );
            }

            $list[$id]['foreign'] = array_column($foreignTablePrimaryKeyColumnRows, 'column_name');
        }

        return parent::_getPortableTableForeignKeysList($list);
    }

    /** @link https://www.sqlite.org/autoinc.html#the_autoincrement_keyword */
    private function parseColumnAutoIncrementFromSQL(string $column, string $sql): bool
    {
        $pattern = '/' . $this->buildIdentifierPattern($column) . 'INTEGER\s+PRIMARY\s+KEY\s+AUTOINCREMENT/i';

        return preg_match($pattern, $sql) === 1;
    }

    private function parseColumnCollationFromSQL(string $column, string $sql): ?string
    {
        $pattern = '{' . $this->buildIdentifierPattern($column)
            . '[^,(]+(?:\([^()]+\)[^,]*)?(?:(?:DEFAULT|CHECK)\s*(?:\(.*?\))?[^,]*)*COLLATE\s+["\']?([^\s,"\')]+)}is';

        if (preg_match($pattern, $sql, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    private function parseTableCommentFromSQL(string $table, string $sql): ?string
    {
        $pattern = '/\s* # Allow whitespace characters at start of line
CREATE\sTABLE' . $this->buildIdentifierPattern($table) . '
( # Start capture
   (?:\s*--[^\n]*\n?)+ # Capture anything that starts with whitespaces followed by -- until the end of the line(s)
)/ix';

        if (preg_match($pattern, $sql, $match) !== 1) {
            return null;
        }

        $comment = preg_replace('{^\s*--}m', '', rtrim($match[1], "\n"));

        return $comment === '' ? null : $comment;
    }

    private function parseColumnCommentFromSQL(string $column, string $sql): string
    {
        $pattern = '{[\s(,]' . $this->buildIdentifierPattern($column)
            . '(?:\([^)]*?\)|[^,(])*?,?((?:(?!\n))(?:\s*--[^\n]*\n?)+)}i';

        if (preg_match($pattern, $sql, $match) !== 1) {
            return '';
        }

        $comment = preg_replace('{^\s*--}m', '', rtrim($match[1], "\n"));
        assert(is_string($comment));

        return $comment;
    }

    /**
     * Returns a regular expression pattern that matches the given unquoted or quoted identifier.
     */
    private function buildIdentifierPattern(string $identifier): string
    {
        return '(?:' . implode('|', array_map(
            static function (string $sql): string {
                return '\W' . preg_quote($sql, '/') . '\W';
            },
            [
                $identifier,
                $this->platform->quoteSingleIdentifier($identifier),
            ],
        )) . ')';
    }

    /** @throws Exception */
    private function getCreateTableSQL(string $tableName): string
    {
        $sql = $this->connection->fetchOne(
            <<<'SQL'
SELECT sql
  FROM (
      SELECT *
        FROM sqlite_master
   UNION ALL
      SELECT *
        FROM sqlite_temp_master
  )
WHERE type = 'table'
AND name = ?
SQL
            ,
            [$tableName],
        );

        if ($sql !== false) {
            return $sql;
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    private function getForeignKeyDetails(string $tableName): array
    {
        $createSql = $this->getCreateTableSQL($tableName);

        if (
            preg_match_all(
                '#
                    (?:CONSTRAINT\s+(\S+)\s+)?
                    (?:FOREIGN\s+KEY[^)]+\)\s*)?
                    REFERENCES\s+\S+\s*(?:\([^)]+\))?
                    (?:
                        [^,]*?
                        (NOT\s+DEFERRABLE|DEFERRABLE)
                        (?:\s+INITIALLY\s+(DEFERRED|IMMEDIATE))?
                    )?#isx',
                $createSql,
                $match,
            ) === 0
        ) {
            return [];
        }

        $names      = $match[1];
        $deferrable = $match[2];
        $deferred   = $match[3];
        $details    = [];

        for ($i = 0, $count = count($match[0]); $i < $count; $i++) {
            $details[] = [
                'constraint_name' => isset($names[$i]) ? $this->parseOptionallyQuotedName($names[$i]) : '',
                'deferrable'      => isset($deferrable[$i]) && strcasecmp($deferrable[$i], 'deferrable') === 0,
                'deferred'        => isset($deferred[$i]) && strcasecmp($deferred[$i], 'deferred') === 0,
            ];
        }

        return $details;
    }

    private function parseOptionallyQuotedName(string $sql): string
    {
        if (str_starts_with($sql, '"') && str_ends_with($sql, '"')) {
            return str_replace('""', '"', substr($sql, 1, -1));
        }

        return $sql;
    }

    public function createComparator(/* ComparatorConfig $config = new ComparatorConfig() */): Comparator
    {
        return new SQLite\Comparator($this->platform, func_num_args() > 0 ? func_get_arg(0) : new ComparatorConfig());
    }

    protected function selectTableNames(string $databaseName): Result
    {
        $sql = sprintf(
            <<<'SQL'
SELECT name AS %1$s
FROM sqlite_master
WHERE type = 'table'
  AND name NOT IN ('geometry_columns', 'spatial_ref_sys', 'sqlite_sequence')
UNION ALL
SELECT name AS %1$s
FROM sqlite_temp_master
WHERE type = 'table'
ORDER BY 1
SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
        );

        return $this->connection->executeQuery($sql);
    }

    protected function selectTableColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT t.name AS %s,
                   c.*
              FROM sqlite_master t
              JOIN pragma_table_info(t.name) c
             WHERE %s
          ORDER BY t.name,
                   c.cid
SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            $this->getWhereClause($tableName, $params),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     *
     * @link https://www.sqlite.org/pragma.html#pragma_index_info
     * @link https://www.sqlite.org/pragma.html#pragma_table_info
     * @link https://www.sqlite.org/fileformat2.html#internal_schema_objects
     */
    protected function selectIndexColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT t.name AS %s,
                   i.name,
                   i."unique",
                   c.name AS column_name
              FROM sqlite_master t
              JOIN pragma_index_list(t.name) i
              JOIN pragma_index_info(i.name) c
             WHERE %s
               AND i.name NOT LIKE 'sqlite_%%'
          ORDER BY t.name, i.seq, c.seqno
SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            $this->getWhereClause($tableName, $params),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectForeignKeyColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT t.name AS %s,
                   p.*
              FROM sqlite_master t
              JOIN pragma_foreign_key_list(t.name) p
                ON p.seq != '-1'
             WHERE %s
          ORDER BY t.name,
                   p.id DESC,
                   p.seq
SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            $this->getWhereClause($tableName, $params),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchTableColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): array
    {
        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);
        }

        $rows = parent::fetchTableColumns($databaseName, $tableName);

        $sqlByTable = $result = [];

        foreach ($rows as $row) {
            $unqualifiedTableName = $row[self::TABLE_NAME_COLUMN];
            $columnName           = $row['name'];

            $tableSQL = $sqlByTable[$unqualifiedTableName] ??= $this->getCreateTableSQL($unqualifiedTableName);

            $result[] = array_merge($row, [
                'autoincrement' => $this->parseColumnAutoIncrementFromSQL($columnName, $tableSQL),
                'collation' => $this->parseColumnCollationFromSQL($columnName, $tableSQL),
                'comment' => $this->parseColumnCommentFromSQL($columnName, $tableSQL),
            ]);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchIndexColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): array
    {
        $result = [];

        $indexColumnRows = parent::fetchIndexColumns($databaseName, $tableName);

        foreach ($indexColumnRows as $indexColumnRow) {
            $result[] = [
                self::TABLE_NAME_COLUMN => $indexColumnRow[self::TABLE_NAME_COLUMN],
                'key_name' => $indexColumnRow['name'],
                'type' => $indexColumnRow['unique'] ? IndexType::UNIQUE : IndexType::REGULAR,
                'column_name' => $indexColumnRow['column_name'],
            ];
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * @link https://www.sqlite.org/pragma.html#pragma_table_info
     */
    protected function fetchPrimaryKeyConstraintColumns(
        string $databaseName,
        ?OptionallyQualifiedName $tableName = null,
    ): array {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT NULL AS %s,
                   t.name AS %s,
                   NULL AS constraint_name,
                   p.name AS column_name
              FROM sqlite_master t
              JOIN pragma_table_info(t.name) p
             WHERE %s
               AND p.pk > 0
          ORDER BY t.name,
                   p.pk
        SQL,
            $this->platform->quoteSingleIdentifier(self::SCHEMA_NAME_COLUMN),
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            $this->getWhereClause($tableName, $params),
        );

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchForeignKeyColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): array
    {
        $columnsByTable = [];
        foreach (parent::fetchForeignKeyColumns($databaseName, $tableName) as $column) {
            $columnsByTable[$column[self::TABLE_NAME_COLUMN]][] = $column;
        }

        $columns = [];
        foreach ($columnsByTable as $table => $tableColumns) {
            $foreignKeyDetails = $this->getForeignKeyDetails($table);
            $foreignKeyCount   = count($foreignKeyDetails);

            foreach ($tableColumns as $column) {
                // SQLite identifies foreign keys in reverse order of appearance in SQL
                $columns[] = array_merge($column, $foreignKeyDetails[$foreignKeyCount - $column['id'] - 1]);
            }
        }

        return $columns;
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchTableOptionsByTable(string $databaseName, ?OptionallyQualifiedName $tableName = null): array
    {
        if ($tableName === null) {
            $tableNames = $this->listTableNames();
        } else {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $tableNames = [
                $tableName->getUnqualifiedName()->toNormalizedValue(
                    $this->platform->getUnquotedIdentifierFolding(),
                ),
            ];
        }

        $tableOptions = [];
        foreach ($tableNames as $unqualifiedTableName) {
            $comment = $this->parseTableCommentFromSQL(
                $unqualifiedTableName,
                $this->getCreateTableSQL($unqualifiedTableName),
            );

            if ($comment === null) {
                continue;
            }

            $tableOptions[self::NULL_SCHEMA_KEY][$unqualifiedTableName]['comment'] = $comment;
        }

        return $tableOptions;
    }

    /** @param list<string> $params */
    private function getWhereClause(?OptionallyQualifiedName $tableName, array &$params): string
    {
        $conditions = [
            "t.type = 'table'",
            "t.name NOT IN ('geometry_columns', 'spatial_ref_sys', 'sqlite_sequence')",
        ];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[] = 't.name = ?';
            $params[]     = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        return implode(' AND ', $conditions);
    }
}
