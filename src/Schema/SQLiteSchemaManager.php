<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLite;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;

use function array_change_key_case;
use function array_merge;
use function assert;
use function count;
use function implode;
use function is_string;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function rtrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strcasecmp;
use function strtolower;
use function trim;
use function usort;

use const CASE_LOWER;

/**
 * SQLite SchemaManager.
 *
 * @extends AbstractSchemaManager<SQLitePlatform>
 */
class SQLiteSchemaManager extends AbstractSchemaManager
{
    /**
     * {@inheritDoc}
     */
    protected function fetchForeignKeyColumnsByTable(string $databaseName): array
    {
        $columnsByTable = parent::fetchForeignKeyColumnsByTable($databaseName);

        if (count($columnsByTable) > 0) {
            foreach ($columnsByTable as $table => $columns) {
                $columnsByTable[$table] = $this->addDetailsToTableForeignKeyColumns($table, $columns);
            }
        }

        return $columnsByTable;
    }

    public function createForeignKey(ForeignKeyConstraint $foreignKey, string $table): void
    {
        $table = $this->introspectTable($table);

        $this->alterTable(new TableDiff($table, [], [], [], [], [], [], [], [], [$foreignKey], [], []));
    }

    public function dropForeignKey(string $name, string $table): void
    {
        $table = $this->introspectTable($table);

        $foreignKey = $table->getForeignKey($name);

        $this->alterTable(new TableDiff($table, [], [], [], [], [], [], [], [], [], [], [$foreignKey]));
    }

    /**
     * {@inheritDoc}
     */
    public function listTableForeignKeys(string $table): array
    {
        $table = $this->normalizeName($table);

        $columns = $this->selectForeignKeyColumns('', $table)
            ->fetchAllAssociative();

        if (count($columns) > 0) {
            $columns = $this->addDetailsToTableForeignKeyColumns($table, $columns);
        }

        return $this->_getPortableTableForeignKeysList($columns);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableDefinition(array $table): string
    {
        return $table['table_name'];
    }

    /**
     * {@inheritDoc}
     *
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaPgsqlReader.html
     */
    protected function _getPortableTableIndexesList(array $tableIndexes, string $tableName): array
    {
        $indexBuffer = [];

        // fetch primary
        $indexArray = $this->connection->fetchAllAssociative('SELECT * FROM PRAGMA_TABLE_INFO (?)', [$tableName]);

        usort(
            $indexArray,
            /**
             * @param array<string,mixed> $a
             * @param array<string,mixed> $b
             */
            static function (array $a, array $b): int {
                if ($a['pk'] === $b['pk']) {
                    return $a['cid'] - $b['cid'];
                }

                return $a['pk'] - $b['pk'];
            },
        );

        foreach ($indexArray as $indexColumnRow) {
            if ($indexColumnRow['pk'] === 0 || $indexColumnRow['pk'] === '0') {
                continue;
            }

            $indexBuffer[] = [
                'key_name' => 'primary',
                'primary' => true,
                'non_unique' => false,
                'column_name' => $indexColumnRow['name'],
            ];
        }

        // fetch regular indexes
        foreach ($tableIndexes as $tableIndex) {
            // Ignore indexes with reserved names, e.g. autoindexes
            if (str_starts_with($tableIndex['name'], 'sqlite_')) {
                continue;
            }

            $keyName           = $tableIndex['name'];
            $idx               = [];
            $idx['key_name']   = $keyName;
            $idx['primary']    = false;
            $idx['non_unique'] = ! $tableIndex['unique'];

            $indexArray = $this->connection->fetchAllAssociative('SELECT * FROM PRAGMA_INDEX_INFO (?)', [$keyName]);

            foreach ($indexArray as $indexColumnRow) {
                $idx['column_name'] = $indexColumnRow['name'];
                $indexBuffer[]      = $idx;
            }
        }

        return parent::_getPortableTableIndexesList($indexBuffer, $tableName);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableColumnList(string $table, string $database, array $tableColumns): array
    {
        $list = parent::_getPortableTableColumnList($table, $database, $tableColumns);

        // find column with autoincrement
        $autoincrementColumn = null;
        $autoincrementCount  = 0;

        foreach ($tableColumns as $tableColumn) {
            if ($tableColumn['pk'] === 0 || $tableColumn['pk'] === '0') {
                continue;
            }

            $autoincrementCount++;
            if ($autoincrementColumn !== null || strtolower($tableColumn['type']) !== 'integer') {
                continue;
            }

            $autoincrementColumn = $tableColumn['name'];
        }

        if ($autoincrementCount === 1 && $autoincrementColumn !== null) {
            foreach ($list as $column) {
                if ($autoincrementColumn !== $column->getName()) {
                    continue;
                }

                $column->setAutoincrement(true);
            }
        }

        // inspect column collation and comments
        $createSql = $this->getCreateTableSQL($table);

        foreach ($list as $columnName => $column) {
            $type = $column->getType();

            if ($type instanceof StringType || $type instanceof TextType) {
                $column->setPlatformOption(
                    'collation',
                    $this->parseColumnCollationFromSQL($columnName, $createSql) ?? 'BINARY',
                );
            }

            $comment = $this->parseColumnCommentFromSQL($columnName, $createSql);

            $column->setComment($comment);
        }

        return $list;
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        preg_match('/^([^()]*)\\s*(\\(((\\d+)(,\\s*(\\d+))?)\\))?/', $tableColumn['type'], $matches);

        $dbType = trim(strtolower($matches[1]));

        $length = $precision = $unsigned = null;
        $fixed  = $unsigned = false;
        $scale  = 0;

        if (count($matches) >= 6) {
            $precision = (int) $matches[4];
            $scale     = (int) $matches[6];
        } elseif (count($matches) >= 4) {
            $length = (int) $matches[4];
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

        if (! isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        if ($dbType === 'char') {
            $fixed = true;
        }

        $options = [
            'length'    => $length,
            'unsigned'  => $unsigned,
            'fixed'     => $fixed,
            'notnull'   => $notnull,
            'default'   => $default,
            'precision' => $precision,
            'scale'     => $scale,
        ];

        return new Column($tableColumn['name'], Type::getType($type), $options);
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
    protected function _getPortableTableForeignKeysList(array $tableForeignKeys): array
    {
        $list = [];
        foreach ($tableForeignKeys as $value) {
            $value = array_change_key_case($value, CASE_LOWER);
            $id    = $value['id'];
            if (! isset($list[$id])) {
                if (! isset($value['on_delete']) || $value['on_delete'] === 'RESTRICT') {
                    $value['on_delete'] = null;
                }

                if (! isset($value['on_update']) || $value['on_update'] === 'RESTRICT') {
                    $value['on_update'] = null;
                }

                $list[$id] = [
                    'name' => $value['constraint_name'],
                    'local' => [],
                    'foreign' => [],
                    'foreignTable' => $value['table'],
                    'onDelete' => $value['on_delete'],
                    'onUpdate' => $value['on_update'],
                    'deferrable' => $value['deferrable'],
                    'deferred' => $value['deferred'],
                ];
            }

            $list[$id]['local'][] = $value['from'];

            if ($value['to'] === null) {
                // Inferring a shorthand form for the foreign key constraint, where the "to" field is empty.
                // @see https://www.sqlite.org/foreignkeys.html#fk_indexes.
                $foreignTableIndexes = $this->_getPortableTableIndexesList([], $value['table']);

                if (! isset($foreignTableIndexes['primary'])) {
                    continue;
                }

                $list[$id]['foreign'] = [...$list[$id]['foreign'], ...$foreignTableIndexes['primary']->getColumns()];

                continue;
            }

            $list[$id]['foreign'][] = $value['to'];
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
        $pattern = '{(?:\W' . preg_quote($column) . '\W|\W'
            . preg_quote($this->platform->quoteSingleIdentifier($column))
            . '\W)[^,(]+(?:\([^()]+\)[^,]*)?(?:(?:DEFAULT|CHECK)\s*(?:\(.*?\))?[^,]*)*COLLATE\s+["\']?([^\s,"\')]+)}is';

        if (preg_match($pattern, $sql, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    private function parseTableCommentFromSQL(string $table, string $sql): ?string
    {
        $pattern = '/\s* # Allow whitespace characters at start of line
CREATE\sTABLE # Match "CREATE TABLE"
(?:\W"' . preg_quote($this->platform->quoteSingleIdentifier($table), '/') . '"\W|\W' . preg_quote($table, '/')
            . '\W) # Match table name (quoted and unquoted)
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
        $pattern = '{[\s(,](?:\W' . preg_quote($this->platform->quoteSingleIdentifier($column))
            . '\W|\W' . preg_quote($column) . '\W)(?:\([^)]*?\)|[^,(])*?,?((?:(?!\n))(?:\s*--[^\n]*\n?)+)}i';

        if (preg_match($pattern, $sql, $match) !== 1) {
            return '';
        }

        $comment = preg_replace('{^\s*--}m', '', rtrim($match[1], "\n"));
        assert(is_string($comment));

        return $comment;
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
     * @param list<array<string,mixed>> $columns
     *
     * @return list<array<string,mixed>>
     *
     * @throws Exception
     */
    private function addDetailsToTableForeignKeyColumns(string $table, array $columns): array
    {
        $foreignKeyDetails = $this->getForeignKeyDetails($table);
        $foreignKeyCount   = count($foreignKeyDetails);

        foreach ($columns as $i => $column) {
            // SQLite identifies foreign keys in reverse order of appearance in SQL
            $columns[$i] = array_merge($column, $foreignKeyDetails[$foreignKeyCount - $column['id'] - 1]);
        }

        return $columns;
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

    public function createComparator(): Comparator
    {
        return new SQLite\Comparator($this->platform);
    }

    protected function selectTableNames(string $databaseName): Result
    {
        $sql = <<<'SQL'
SELECT name AS table_name
FROM sqlite_master
WHERE type = 'table'
  AND name != 'sqlite_sequence'
  AND name != 'geometry_columns'
  AND name != 'spatial_ref_sys'
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
        $sql = <<<'SQL'
            SELECT t.name AS table_name,
                   c.*
              FROM sqlite_master t
              JOIN pragma_table_info(t.name) c
SQL;

        $conditions = [
            "t.type = 'table'",
            "t.name NOT IN ('geometry_columns', 'spatial_ref_sys', 'sqlite_sequence')",
        ];
        $params     = [];

        if ($tableName !== null) {
            $conditions[] = 't.name = ?';
            $params[]     = str_replace('.', '__', $tableName);
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY t.name, c.cid';

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectIndexColumns(string $databaseName, ?string $tableName = null): Result
    {
        $sql = <<<'SQL'
            SELECT t.name AS table_name,
                   i.*
              FROM sqlite_master t
              JOIN pragma_index_list(t.name) i
SQL;

        $conditions = [
            "t.type = 'table'",
            "t.name NOT IN ('geometry_columns', 'spatial_ref_sys', 'sqlite_sequence')",
        ];
        $params     = [];

        if ($tableName !== null) {
            $conditions[] = 't.name = ?';
            $params[]     = str_replace('.', '__', $tableName);
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY t.name, i.seq';

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectForeignKeyColumns(string $databaseName, ?string $tableName = null): Result
    {
        $sql = <<<'SQL'
            SELECT t.name AS table_name,
                   p.*
              FROM sqlite_master t
              JOIN pragma_foreign_key_list(t.name) p
                ON p."seq" != "-1"
SQL;

        $conditions = [
            "t.type = 'table'",
            "t.name NOT IN ('geometry_columns', 'spatial_ref_sys', 'sqlite_sequence')",
        ];
        $params     = [];

        if ($tableName !== null) {
            $conditions[] = 't.name = ?';
            $params[]     = str_replace('.', '__', $tableName);
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY t.name, p.id DESC, p.seq';

        return $this->connection->executeQuery($sql, $params);
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

        return $tableOptions;
    }
}
