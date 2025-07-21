<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLite;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\Deprecation;

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
use function str_replace;
use function strcasecmp;
use function strtolower;

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
     * @deprecated Use the schema name and the unqualified table name separately instead.
     *
     * {@inheritDoc}
     */
    protected function _getPortableTableDefinition(array $table): string
    {
        return $table['table_name'];
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $matchResult = preg_match('/^([A-Z\s]+?)(?:\s*\((\d+)(?:,\s*(\d+))?\))?$/i', $tableColumn['type'], $matches);
        assert($matchResult === 1);

        $dbType = strtolower($matches[1]);

        $length = $precision = null;
        $fixed  = $unsigned = false;
        $scale  = 0;

        if (isset($matches[2])) {
            if (isset($matches[3])) {
                $precision = (int) $matches[2];
                $scale     = (int) $matches[3];
            } else {
                $length = (int) $matches[2];
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
                if (! isset($row['on_delete']) || $row['on_delete'] === 'RESTRICT') {
                    $row['on_delete'] = null;
                }

                if (! isset($row['on_update']) || $row['on_update'] === 'RESTRICT') {
                    $row['on_update'] = null;
                }

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
            $foreignTablePrimaryKeyColumnRows = $this->fetchPrimaryKeyColumns($value['foreignTable']);

            if (count($foreignTablePrimaryKeyColumnRows) < 1) {
                Deprecation::trigger(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/6701',
                    'Introspection of SQLite foreign key constraints with omitted referenced column names'
                        . ' in an incomplete schema is deprecated.',
                );

                continue;
            }

            $list[$id]['foreign'] = array_column($foreignTablePrimaryKeyColumnRows, 'name');
        }

        return parent::_getPortableTableForeignKeysList($list);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableForeignKeyDefinition(array $tableForeignKey): ForeignKeyConstraint
    {
        return new ForeignKeyConstraint(
            $tableForeignKey['local'],
            $tableForeignKey['foreignTable'],
            $tableForeignKey['foreign'],
            $tableForeignKey['name'],
            [
                'onDelete' => $tableForeignKey['onDelete'],
                'onUpdate' => $tableForeignKey['onUpdate'],
                'deferrable' => $tableForeignKey['deferrable'],
                'deferred' => $tableForeignKey['deferred'],
            ],
        );
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
    private function getCreateTableSQL(string $table): string
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
            [$table],
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
    private function getForeignKeyDetails(string $table): array
    {
        $createSql = $this->getCreateTableSQL($table);

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
                'constraint_name' => $names[$i] ?? '',
                'deferrable'      => isset($deferrable[$i]) && strcasecmp($deferrable[$i], 'deferrable') === 0,
                'deferred'        => isset($deferred[$i]) && strcasecmp($deferred[$i], 'deferred') === 0,
            ];
        }

        return $details;
    }

    public function createComparator(/* ComparatorConfig $config = new ComparatorConfig() */): Comparator
    {
        return new SQLite\Comparator($this->platform, func_num_args() > 0 ? func_get_arg(0) : new ComparatorConfig());
    }

    protected function selectTableNames(string $databaseName): Result
    {
        $sql = <<<'SQL'
SELECT name AS table_name
FROM sqlite_master
WHERE type = 'table'
  AND name NOT IN ('geometry_columns', 'spatial_ref_sys', 'sqlite_sequence')
UNION ALL
SELECT name
FROM sqlite_temp_master
WHERE type = 'table'
ORDER BY name
SQL;

        return $this->connection->executeQuery($sql);
    }

    protected function selectTableColumns(string $databaseName, ?string $tableName = null): Result
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT t.name AS table_name,
                   c.*
              FROM sqlite_master t
              JOIN pragma_table_info(t.name) c
             WHERE %s
          ORDER BY t.name,
                   c.cid
SQL,
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
    protected function selectIndexColumns(string $databaseName, ?string $tableName = null): Result
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT t.name AS table_name,
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
            $this->getWhereClause($tableName, $params),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectForeignKeyColumns(string $databaseName, ?string $tableName = null): Result
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT t.name AS table_name,
                   p.*
              FROM sqlite_master t
              JOIN pragma_foreign_key_list(t.name) p
                ON p.seq != '-1'
             WHERE %s
          ORDER BY t.name,
                   p.id DESC,
                   p.seq
SQL,
            $this->getWhereClause($tableName, $params),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchTableColumns(string $databaseName, ?string $tableName = null): array
    {
        $rows = parent::fetchTableColumns($databaseName, $tableName);

        $sqlByTable = $pkColumnNamesByTable = $result = [];

        foreach ($rows as $row) {
            $tableName = $row['table_name'];

            $sqlByTable[$tableName] ??= $this->getCreateTableSQL($tableName);

            if ($row['pk'] === 0 || $row['pk'] === '0' || $row['type'] !== 'INTEGER') {
                continue;
            }

            $pkColumnNamesByTable[$tableName][] = $row['name'];
        }

        foreach ($rows as $row) {
            $tableName  = $row['table_name'];
            $columnName = $row['name'];
            $tableSQL   = $sqlByTable[$row['table_name']];

            $result[] = array_merge($row, [
                'autoincrement' => isset($pkColumnNamesByTable[$tableName])
                    && $pkColumnNamesByTable[$tableName] === [$columnName],
                'collation' => $this->parseColumnCollationFromSQL($columnName, $tableSQL),
                'comment' => $this->parseColumnCommentFromSQL($columnName, $tableSQL),
            ]);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchIndexColumns(string $databaseName, ?string $tableName = null): array
    {
        $result = [];

        $pkColumnNameRows = $this->fetchPrimaryKeyColumns($tableName);

        foreach ($pkColumnNameRows as $pkColumnNameRow) {
            $result[] = [
                'table_name' => $pkColumnNameRow['table_name'],
                'key_name' => 'primary',
                'primary' => true,
                'non_unique' => false,
                'column_name' => $pkColumnNameRow['name'],
            ];
        }

        $indexColumnRows = parent::fetchIndexColumns($databaseName, $tableName);

        foreach ($indexColumnRows as $indexColumnRow) {
            $result[] = [
                'table_name' => $indexColumnRow['table_name'],
                'key_name'   => $indexColumnRow['name'],
                'primary'    => false,
                'non_unique' => ! $indexColumnRow['unique'],
                'column_name' => $indexColumnRow['column_name'],
            ];
        }

        return $result;
    }

    /**
     * Fetches names of primary key columns. If the table name is specified, narrows down the selection to this table.
     *
     * @link https://www.sqlite.org/pragma.html#pragma_table_info
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    private function fetchPrimaryKeyColumns(?string $tableName = null): array
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT t.name AS table_name,
                   p.name
              FROM sqlite_master t
              JOIN pragma_table_info(t.name) p
             WHERE %s
               AND p.pk > 0
          ORDER BY t.name,
                   p.pk
        SQL,
            $this->getWhereClause($tableName, $params),
        );

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchForeignKeyColumns(string $databaseName, ?string $tableName = null): array
    {
        $columnsByTable = [];
        foreach (parent::fetchForeignKeyColumns($databaseName, $tableName) as $column) {
            $columnsByTable[$column['table_name']][] = $column;
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
    protected function fetchTableOptionsByTable(string $databaseName, ?string $tableName = null): array
    {
        if ($tableName === null) {
            $tables = $this->listTableNames();
        } else {
            $tables = [$tableName];
        }

        $tableOptions = [];
        foreach ($tables as $table) {
            $comment = $this->parseTableCommentFromSQL($table, $this->getCreateTableSQL($table));

            if ($comment === null) {
                continue;
            }

            $tableOptions[$table]['comment'] = $comment;
        }

        /** @phpstan-ignore return.type */
        return $tableOptions;
    }

    /** @param list<string> $params */
    private function getWhereClause(?string $tableName, array &$params): string
    {
        $conditions = [
            "t.type = 'table'",
            "t.name NOT IN ('geometry_columns', 'spatial_ref_sys', 'sqlite_sequence')",
        ];

        if ($tableName !== null) {
            $conditions[] = 't.name = ?';
            $params[]     = $tableName;
        }

        return implode(' AND ', $conditions);
    }
}
