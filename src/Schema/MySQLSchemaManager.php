<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider\CachingCharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider\ConnectionCharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider\CachingCollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider\ConnectionCollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\DefaultTableOptions;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Type;

use function array_change_key_case;
use function array_map;
use function assert;
use function explode;
use function func_get_arg;
use function func_num_args;
use function implode;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function strtr;

use const CASE_LOWER;

/**
 * Schema manager for the MySQL RDBMS.
 *
 * @extends AbstractSchemaManager<AbstractMySQLPlatform>
 */
class MySQLSchemaManager extends AbstractSchemaManager
{
    /** @see https://mariadb.com/kb/en/library/string-literals/#escape-sequences */
    private const MARIADB_ESCAPE_SEQUENCES = [
        '\\0' => "\0",
        "\\'" => "'",
        '\\"' => '"',
        '\\b' => "\b",
        '\\n' => "\n",
        '\\r' => "\r",
        '\\t' => "\t",
        '\\Z' => "\x1a",
        '\\\\' => '\\',
        '\\%' => '%',
        '\\_' => '_',

        // Internally, MariaDB escapes single quotes using the standard syntax
        "''" => "'",
    ];

    private ?DefaultTableOptions $defaultTableOptions = null;

    /**
     * @deprecated Use the schema name and the unqualified table name separately instead.
     *
     * {@inheritDoc}
     */
    protected function _getPortableTableDefinition(array $table): string
    {
        return $table['TABLE_NAME'];
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        return new View($view['TABLE_NAME'], $view['VIEW_DEFINITION']);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableIndexesList(array $rows, string $tableName): array
    {
        foreach ($rows as $i => $row) {
            $row = array_change_key_case($row, CASE_LOWER);

            $row['primary'] = $row['key_name'] === 'PRIMARY';

            if (str_contains($row['index_type'], 'FULLTEXT')) {
                $row['flags'] = ['FULLTEXT'];
            } elseif (str_contains($row['index_type'], 'SPATIAL')) {
                $row['flags'] = ['SPATIAL'];
            }

            // Ignore prohibited prefix `length` for spatial index
            if (! str_contains($row['index_type'], 'SPATIAL')) {
                $row['length'] = isset($row['sub_part']) ? (int) $row['sub_part'] : null;
            }

            $rows[$i] = $row;
        }

        return parent::_getPortableTableIndexesList($rows, $tableName);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableDatabaseDefinition(array $database): string
    {
        return $database['Database'];
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        $dbType    = $tableColumn['type'];
        $length    = null;
        $scale     = 0;
        $precision = null;
        $fixed     = false;
        $values    = [];

        $type = $this->platform->getDoctrineTypeMapping($dbType);

        switch ($dbType) {
            case 'char':
            case 'varchar':
                $length = (int) $tableColumn['character_maximum_length'];
                break;

            case 'binary':
            case 'varbinary':
                $length = (int) $tableColumn['character_octet_length'];
                break;

            case 'tinytext':
                $length = AbstractMySQLPlatform::LENGTH_LIMIT_TINYTEXT;
                break;

            case 'text':
                $length = AbstractMySQLPlatform::LENGTH_LIMIT_TEXT;
                break;

            case 'mediumtext':
                $length = AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMTEXT;
                break;

            case 'tinyblob':
                $length = AbstractMySQLPlatform::LENGTH_LIMIT_TINYBLOB;
                break;

            case 'blob':
                $length = AbstractMySQLPlatform::LENGTH_LIMIT_BLOB;
                break;

            case 'mediumblob':
                $length = AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMBLOB;
                break;

            case 'float':
            case 'double':
            case 'real':
            case 'numeric':
            case 'decimal':
                $precision = (int) $tableColumn['numeric_precision'];

                if (isset($tableColumn['numeric_scale'])) {
                    $scale = (int) $tableColumn['numeric_scale'];
                }

                break;
        }

        switch ($dbType) {
            case 'char':
            case 'binary':
                $fixed = true;
                break;

            case 'enum':
                $values = $this->parseEnumExpression($tableColumn['column_type']);
                break;
        }

        if ($this->platform instanceof MariaDBPlatform) {
            $columnDefault = $this->getMariaDBColumnDefault($this->platform, $tableColumn['default']);
        } else {
            $columnDefault = $tableColumn['default'];
        }

        $options = [
            'length'        => $length,
            'unsigned'      => str_contains($tableColumn['column_type'], 'unsigned'),
            'fixed'         => $fixed,
            'default'       => $columnDefault,
            'notnull'       => $tableColumn['null'] !== 'YES',
            'scale'         => $scale,
            'precision'     => $precision,
            'autoincrement' => str_contains($tableColumn['extra'], 'auto_increment'),
            'values'        => $values,
        ];

        if ($tableColumn['comment'] !== null) {
            $options['comment'] = $tableColumn['comment'];
        }

        $column = new Column($tableColumn['field'], Type::getType($type), $options);
        $column->setPlatformOption('charset', $tableColumn['characterset']);
        $column->setPlatformOption('collation', $tableColumn['collation']);

        return $column;
    }

    /** @return list<string> */
    private function parseEnumExpression(string $expression): array
    {
        $result = preg_match_all("/'([^']*(?:''[^']*)*)'/", $expression, $matches);
        assert($result !== false);

        return array_map(
            static fn (string $match): string => strtr($match, ["''" => "'"]),
            $matches[1],
        );
    }

    /**
     * Return Doctrine/Mysql-compatible column default values for MariaDB 10.2.7+ servers.
     *
     * - Since MariaDb 10.2.7 column defaults stored in information_schema are now quoted
     *   to distinguish them from expressions (see MDEV-10134).
     * - CURRENT_TIMESTAMP, CURRENT_TIME, CURRENT_DATE are stored in information_schema
     *   as current_timestamp(), currdate(), currtime()
     * - Quoted 'NULL' is not enforced by Maria, it is technically possible to have
     *   null in some circumstances (see https://jira.mariadb.org/browse/MDEV-14053)
     * - \' is always stored as '' in information_schema (normalized)
     *
     * @link https://mariadb.com/kb/en/library/information-schema-columns-table/
     * @link https://jira.mariadb.org/browse/MDEV-13132
     *
     * @param string|null $columnDefault default value as stored in information_schema for MariaDB >= 10.2.7
     */
    private function getMariaDBColumnDefault(MariaDBPlatform $platform, ?string $columnDefault): ?string
    {
        if ($columnDefault === 'NULL' || $columnDefault === null) {
            return null;
        }

        if (preg_match('/^\'(.*)\'$/', $columnDefault, $matches) === 1) {
            return strtr($matches[1], self::MARIADB_ESCAPE_SEQUENCES);
        }

        return match ($columnDefault) {
            'current_timestamp()' => $platform->getCurrentTimestampSQL(),
            'curdate()' => $platform->getCurrentDateSQL(),
            'curtime()' => $platform->getCurrentTimeSQL(),
            default => $columnDefault,
        };
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableForeignKeysList(array $rows): array
    {
        $list = [];
        foreach ($rows as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            if (! isset($list[$row['constraint_name']])) {
                if (! isset($row['delete_rule']) || $row['delete_rule'] === 'RESTRICT') {
                    $row['delete_rule'] = null;
                }

                if (! isset($row['update_rule']) || $row['update_rule'] === 'RESTRICT') {
                    $row['update_rule'] = null;
                }

                $list[$row['constraint_name']] = [
                    'name' => $this->getQuotedIdentifierName($row['constraint_name']),
                    'local' => [],
                    'foreign' => [],
                    'foreignTable' => $row['referenced_table_name'],
                    'onDelete' => $row['delete_rule'],
                    'onUpdate' => $row['update_rule'],
                ];
            }

            $list[$row['constraint_name']]['local'][]   = $row['column_name'];
            $list[$row['constraint_name']]['foreign'][] = $row['referenced_column_name'];
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
            ],
        );
    }

    /** @throws Exception */
    public function createComparator(/* ComparatorConfig $config = new ComparatorConfig() */): Comparator
    {
        return new MySQL\Comparator(
            $this->platform,
            new CachingCharsetMetadataProvider(
                new ConnectionCharsetMetadataProvider($this->connection),
            ),
            new CachingCollationMetadataProvider(
                new ConnectionCollationMetadataProvider($this->connection),
            ),
            $this->getDefaultTableOptions(),
            func_num_args() > 0 ? func_get_arg(0) : new ComparatorConfig(),
        );
    }

    protected function selectTableNames(string $databaseName): Result
    {
        $sql = <<<'SQL'
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = ?
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME
SQL;

        return $this->connection->executeQuery($sql, [$databaseName]);
    }

    protected function selectTableColumns(string $databaseName, ?string $tableName = null): Result
    {
        // The schema name is passed multiple times as a literal in the WHERE clause instead of using a JOIN condition
        // in order to avoid performance issues on MySQL older than 8.0 and the corresponding MariaDB versions
        // caused by https://bugs.mysql.com/bug.php?id=81347
        $conditions = ['c.TABLE_SCHEMA = ?', 't.TABLE_SCHEMA = ?'];
        $params     = [$databaseName, $databaseName];

        if ($tableName !== null) {
            $conditions[] = 't.TABLE_NAME = ?';
            $params[]     = $tableName;
        }

        $sql = sprintf(
            <<<'SQL'
SELECT
       c.TABLE_NAME,
       c.COLUMN_NAME        AS field,
       %s                   AS type,
       c.COLUMN_TYPE,
       c.CHARACTER_MAXIMUM_LENGTH,
       c.CHARACTER_OCTET_LENGTH,
       c.NUMERIC_PRECISION,
       c.NUMERIC_SCALE,
       c.IS_NULLABLE        AS `null`,
       c.COLUMN_KEY         AS `key`,
       c.COLUMN_DEFAULT     AS `default`,
       c.EXTRA,
       c.COLUMN_COMMENT     AS comment,
       c.CHARACTER_SET_NAME AS characterset,
       c.COLLATION_NAME     AS collation
FROM information_schema.COLUMNS c
    INNER JOIN information_schema.TABLES t
        ON t.TABLE_NAME = c.TABLE_NAME
 WHERE %s
   AND t.TABLE_TYPE = 'BASE TABLE'
ORDER BY c.TABLE_NAME,
         c.ORDINAL_POSITION
SQL,
            $this->platform->getColumnTypeSQLSnippet('c', $databaseName),
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectIndexColumns(string $databaseName, ?string $tableName = null): Result
    {
        $conditions = ['TABLE_SCHEMA = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $conditions[] = 'TABLE_NAME = ?';
            $params[]     = $tableName;
        }

        $sql = sprintf(
            <<<'SQL'
SELECT
        TABLE_NAME,
        NON_UNIQUE  AS Non_Unique,
        INDEX_NAME  AS Key_name,
        COLUMN_NAME AS Column_Name,
        SUB_PART    AS Sub_Part,
        INDEX_TYPE  AS Index_Type
FROM information_schema.STATISTICS
WHERE %s
ORDER BY TABLE_NAME,
         SEQ_IN_INDEX
SQL,
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectForeignKeyColumns(string $databaseName, ?string $tableName = null): Result
    {
        // The schema name is passed multiple times in the WHERE clause instead of using a JOIN condition
        // in order to avoid performance issues on MySQL older than 8.0 and the corresponding MariaDB versions
        // caused by https://bugs.mysql.com/bug.php?id=81347
        $conditions = ['k.TABLE_SCHEMA = ?', 'c.CONSTRAINT_SCHEMA = ?'];
        $params     = [$databaseName, $databaseName];

        if ($tableName !== null) {
            $conditions[] = 'k.TABLE_NAME = ?';
            $params[]     = $tableName;
        }

        $sql = sprintf(
            <<<'SQL'
SELECT
            k.TABLE_NAME,
            k.CONSTRAINT_NAME,
            k.COLUMN_NAME,
            k.REFERENCED_TABLE_NAME,
            k.REFERENCED_COLUMN_NAME,
            k.ORDINAL_POSITION,
            c.UPDATE_RULE,
            c.DELETE_RULE
FROM information_schema.key_column_usage k
INNER JOIN information_schema.referential_constraints c
ON c.CONSTRAINT_NAME = k.CONSTRAINT_NAME
AND c.TABLE_NAME = k.TABLE_NAME
WHERE %s
AND k.REFERENCED_COLUMN_NAME IS NOT NULL
ORDER BY k.TABLE_NAME,
         k.CONSTRAINT_NAME,
         k.ORDINAL_POSITION
SQL,
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchTableOptionsByTable(string $databaseName, ?string $tableName = null): array
    {
        $sql = $this->platform->fetchTableOptionsByTable($tableName !== null);

        $params = [$databaseName];
        if ($tableName !== null) {
            $params[] = $tableName;
        }

        /** @var array<non-empty-string,array<string,mixed>> $metadata */
        $metadata = $this->connection->executeQuery($sql, $params)
            ->fetchAllAssociativeIndexed();

        $tableOptions = [];
        foreach ($metadata as $table => $data) {
            $data = array_change_key_case($data, CASE_LOWER);

            $tableOptions[$table] = [
                'engine'         => $data['engine'],
                'collation'      => $data['table_collation'],
                'charset'        => $data['character_set_name'],
                'autoincrement'  => $data['auto_increment'],
                'comment'        => $data['table_comment'],
                'create_options' => $this->parseCreateOptions($data['create_options']),
            ];
        }

        return $tableOptions;
    }

    /** @return array<string, string>|array<string, true> */
    private function parseCreateOptions(?string $string): array
    {
        $options = [];

        if ($string === null || $string === '') {
            return $options;
        }

        foreach (explode(' ', $string) as $pair) {
            $parts = explode('=', $pair, 2);

            $options[$parts[0]] = $parts[1] ?? true;
        }

        return $options;
    }

    /** @throws Exception */
    private function getDefaultTableOptions(): DefaultTableOptions
    {
        if ($this->defaultTableOptions === null) {
            $row = $this->connection->fetchNumeric(
                'SELECT @@character_set_database, @@collation_database',
            );

            assert($row !== false);

            $this->defaultTableOptions = new DefaultTableOptions(...$row);
        }

        return $this->defaultTableOptions;
    }

    /** Returns the quoted representation of the given identifier name. */
    private function getQuotedIdentifierName(?string $identifier): ?string
    {
        if ($identifier === null) {
            return null;
        }

        return $this->platform->quoteSingleIdentifier($identifier);
    }
}
