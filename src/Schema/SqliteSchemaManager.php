<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;

use function array_change_key_case;
use function array_reverse;
use function array_values;
use function assert;
use function count;
use function file_exists;
use function is_string;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_replace;
use function strpos;
use function strtolower;
use function trim;
use function unlink;
use function usort;

use const CASE_LOWER;

/**
 * Sqlite SchemaManager.
 */
class SqliteSchemaManager extends AbstractSchemaManager
{
    public function dropDatabase(string $database): void
    {
        if (! file_exists($database)) {
            return;
        }

        unlink($database);
    }

    public function createDatabase(string $database): void
    {
        $params  = $this->_conn->getParams();
        $driver  = $params['driver'];
        $options = [
            'driver' => $driver,
            'path' => $database,
        ];
        $conn    = DriverManager::getConnection($options);
        $conn->connect();
        $conn->close();
    }

    public function renameTable(string $name, string $newName): void
    {
        $tableDiff            = new TableDiff($name);
        $tableDiff->fromTable = $this->listTableDetails($name);
        $tableDiff->newName   = $newName;
        $this->alterTable($tableDiff);
    }

    /**
     * {@inheritdoc}
     */
    public function createForeignKey(ForeignKeyConstraint $foreignKey, $table): void
    {
        $table = $this->ensureTable($table);

        $tableDiff = $this->getTableDiffForAlterForeignKey($table);

        $tableDiff->addedForeignKeys[] = $foreignKey;

        $this->alterTable($tableDiff);
    }

    /**
     * {@inheritdoc}
     */
    public function dropAndCreateForeignKey(ForeignKeyConstraint $foreignKey, $table): void
    {
        $table = $this->ensureTable($table);

        $tableDiff = $this->getTableDiffForAlterForeignKey($table);

        $tableDiff->changedForeignKeys[] = $foreignKey;

        $this->alterTable($tableDiff);
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeignKey($foreignKey, $table): void
    {
        $table = $this->ensureTable($table);

        $tableDiff = $this->getTableDiffForAlterForeignKey($table);

        if (is_string($foreignKey)) {
            $tableDiff->removedForeignKeys[] = $table->getForeignKey($foreignKey);
        } else {
            $tableDiff->removedForeignKeys[] = $foreignKey;
        }

        $this->alterTable($tableDiff);
    }

    /**
     * {@inheritdoc}
     */
    public function listTableForeignKeys(string $table, ?string $database = null): array
    {
        if ($database === null) {
            $database = $this->_conn->getDatabase();
        }

        $sql              = $this->_platform->getListTableForeignKeysSQL($table, $database);
        $tableForeignKeys = $this->_conn->fetchAllAssociative($sql);

        if (! empty($tableForeignKeys)) {
            $createSql = $this->getCreateTableSQL($table);

            if (
                preg_match_all(
                    '#
                    (?:CONSTRAINT\s+([^\s]+)\s+)?
                    (?:FOREIGN\s+KEY[^\)]+\)\s*)?
                    REFERENCES\s+[^\s]+\s+(?:\([^\)]+\))?
                    (?:
                        [^,]*?
                        (NOT\s+DEFERRABLE|DEFERRABLE)
                        (?:\s+INITIALLY\s+(DEFERRED|IMMEDIATE))?
                    )?#isx',
                    $createSql,
                    $match
                ) > 0
            ) {
                $names      = array_reverse($match[1]);
                $deferrable = array_reverse($match[2]);
                $deferred   = array_reverse($match[3]);
            } else {
                $names = $deferrable = $deferred = [];
            }

            foreach ($tableForeignKeys as $key => $value) {
                $id                                        = $value['id'];
                $tableForeignKeys[$key]['constraint_name'] = isset($names[$id]) && $names[$id] !== '' ? $names[$id] : $id;
                $tableForeignKeys[$key]['deferrable']      = isset($deferrable[$id]) && strtolower($deferrable[$id]) === 'deferrable';
                $tableForeignKeys[$key]['deferred']        = isset($deferred[$id]) && strtolower($deferred[$id]) === 'deferred';
            }
        }

        return $this->_getPortableTableForeignKeysList($tableForeignKeys);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableDefinition(array $table): string
    {
        return $table['name'];
    }

    /**
     * {@inheritdoc}
     *
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaPgsqlReader.html
     */
    protected function _getPortableTableIndexesList(array $tableIndexRows, string $tableName): array
    {
        $indexBuffer = [];

        // fetch primary
        $indexArray = $this->_conn->fetchAllAssociative(sprintf(
            'PRAGMA TABLE_INFO (%s)',
            $this->_conn->quote($tableName)
        ));

        usort($indexArray, static function ($a, $b) {
            if ($a['pk'] === $b['pk']) {
                return $a['cid'] - $b['cid'];
            }

            return $a['pk'] - $b['pk'];
        });
        foreach ($indexArray as $indexColumnRow) {
            if ($indexColumnRow['pk'] === '0') {
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
        foreach ($tableIndexRows as $tableIndex) {
            // Ignore indexes with reserved names, e.g. autoindexes
            if (strpos($tableIndex['name'], 'sqlite_') === 0) {
                continue;
            }

            $keyName           = $tableIndex['name'];
            $idx               = [];
            $idx['key_name']   = $keyName;
            $idx['primary']    = false;
            $idx['non_unique'] = ! $tableIndex['unique'];

            $indexArray = $this->_conn->fetchAllAssociative(sprintf(
                'PRAGMA INDEX_INFO (%s)',
                $this->_conn->quote($keyName)
            ));

            foreach ($indexArray as $indexColumnRow) {
                $idx['column_name'] = $indexColumnRow['name'];
                $indexBuffer[]      = $idx;
            }
        }

        return parent::_getPortableTableIndexesList($indexBuffer, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableColumnList(string $table, string $database, array $tableColumns): array
    {
        $list = parent::_getPortableTableColumnList($table, $database, $tableColumns);

        // find column with autoincrement
        $autoincrementColumn = null;
        $autoincrementCount  = 0;

        foreach ($tableColumns as $tableColumn) {
            if ($tableColumn['pk'] === '0') {
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
                    $this->parseColumnCollationFromSQL($columnName, $createSql) ?? 'BINARY'
                );
            }

            $comment = $this->parseColumnCommentFromSQL($columnName, $createSql);

            $type = $this->extractDoctrineTypeFromComment($comment);

            if ($type !== null) {
                $column->setType(Type::getType($type));
            }

            $column->setComment($comment);
        }

        return $list;
    }

    /**
     * {@inheritdoc}
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

        if (strpos($dbType, ' unsigned') !== false) {
            $dbType   = str_replace(' unsigned', '', $dbType);
            $unsigned = true;
        }

        $type    = $this->_platform->getDoctrineTypeMapping($dbType);
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
            'length'   => $length,
            'unsigned' => $unsigned,
            'fixed'    => $fixed,
            'notnull'  => $notnull,
            'default'  => $default,
            'precision' => $precision,
            'scale'     => $scale,
            'autoincrement' => false,
        ];

        return new Column($tableColumn['name'], Type::getType($type), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        return new View($view['name'], $view['sql']);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableForeignKeysList(array $tableForeignKeys): array
    {
        $list = [];
        foreach ($tableForeignKeys as $value) {
            $value = array_change_key_case($value, CASE_LOWER);
            $name  = $value['constraint_name'];
            if (! isset($list[$name])) {
                if (! isset($value['on_delete']) || $value['on_delete'] === 'RESTRICT') {
                    $value['on_delete'] = null;
                }

                if (! isset($value['on_update']) || $value['on_update'] === 'RESTRICT') {
                    $value['on_update'] = null;
                }

                $list[$name] = [
                    'name' => $name,
                    'local' => [],
                    'foreign' => [],
                    'foreignTable' => $value['table'],
                    'onDelete' => $value['on_delete'],
                    'onUpdate' => $value['on_update'],
                    'deferrable' => $value['deferrable'],
                    'deferred' => $value['deferred'],
                ];
            }

            $list[$name]['local'][]   = $value['from'];
            $list[$name]['foreign'][] = $value['to'];
        }

        $result = [];
        foreach ($list as $constraint) {
            $result[] = new ForeignKeyConstraint(
                array_values($constraint['local']),
                $constraint['foreignTable'],
                array_values($constraint['foreign']),
                $constraint['name'],
                [
                    'onDelete' => $constraint['onDelete'],
                    'onUpdate' => $constraint['onUpdate'],
                    'deferrable' => $constraint['deferrable'],
                    'deferred' => $constraint['deferred'],
                ]
            );
        }

        return $result;
    }

    private function getTableDiffForAlterForeignKey(Table $table): TableDiff
    {
        $tableDiff            = new TableDiff($table->getName());
        $tableDiff->fromTable = $table;

        return $tableDiff;
    }

    /**
     * @param string|Table $table
     */
    private function ensureTable($table): Table
    {
        if (is_string($table)) {
            $table = $this->listTableDetails($table);
        }

        return $table;
    }

    private function parseColumnCollationFromSQL(string $column, string $sql): ?string
    {
        $pattern = '{(?:\W' . preg_quote($column) . '\W|\W' . preg_quote($this->_platform->quoteSingleIdentifier($column))
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
(?:\W"' . preg_quote($this->_platform->quoteSingleIdentifier($table), '/') . '"\W|\W' . preg_quote($table, '/')
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
        $pattern = '{[\s(,](?:\W' . preg_quote($this->_platform->quoteSingleIdentifier($column)) . '\W|\W' . preg_quote($column)
            . '\W)(?:\([^)]*?\)|[^,(])*?,?((?:(?!\n))(?:\s*--[^\n]*\n?)+)}i';

        if (preg_match($pattern, $sql, $match) !== 1) {
            return '';
        }

        $comment = preg_replace('{^\s*--}m', '', rtrim($match[1], "\n"));
        assert(is_string($comment));

        return $comment;
    }

    private function getCreateTableSQL(string $table): string
    {
        $sql = $this->_conn->fetchOne(
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
            [$table]
        );

        if ($sql !== false) {
            return $sql;
        }

        return '';
    }

    public function listTableDetails(string $tableName): Table
    {
        $table = parent::listTableDetails($tableName);

        $tableCreateSql = $this->getCreateTableSQL($tableName);

        $comment = $this->parseTableCommentFromSQL($tableName, $tableCreateSql);

        if ($comment !== null) {
            $table->addOption('comment', $comment);
        }

        return $table;
    }
}
