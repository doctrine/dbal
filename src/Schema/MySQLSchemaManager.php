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
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Types\Type;

use function array_change_key_case;
use function array_map;
use function assert;
use function explode;
use function func_get_arg;
use function func_num_args;
use function implode;
use function is_string;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function strtok;
use function strtolower;
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
     * {@inheritDoc}
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        return new View($view['TABLE_NAME'], $view['VIEW_DEFINITION']);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableIndexesList(array $rows): array
    {
        foreach ($rows as $i => $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            if (str_contains($row['index_type'], 'FULLTEXT')) {
                $row['type'] = IndexType::FULLTEXT;
            } elseif (str_contains($row['index_type'], 'SPATIAL')) {
                $row['type'] = IndexType::SPATIAL;
            } elseif ($row['non_unique']) {
                $row['type'] = IndexType::REGULAR;
            } else {
                $row['type'] = IndexType::UNIQUE;
            }

            // Ignore prohibited prefix `length` for spatial index
            if (! str_contains($row['index_type'], 'SPATIAL')) {
                $row['length'] = isset($row['sub_part']) ? (int) $row['sub_part'] : null;
            }

            $rows[$i] = $row;
        }

        return parent::_getPortableTableIndexesList($rows);
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

        $dbType = strtolower($tableColumn['column_type']);
        $dbType = strtok($dbType, '(), ');
        assert(is_string($dbType));

        $length = $tableColumn['length'] ?? strtok('(), ');

        $fixed = false;

        $scale     = 0;
        $precision = null;

        $type = $this->platform->getDoctrineTypeMapping($dbType);

        $values = [];

        switch ($dbType) {
            case 'char':
            case 'binary':
                $fixed = true;
                break;

            case 'float':
            case 'double':
            case 'real':
            case 'numeric':
            case 'decimal':
                if (
                    preg_match(
                        '([A-Za-z]+\(([0-9]+),([0-9]+)\))',
                        $tableColumn['column_type'],
                        $match,
                    ) === 1
                ) {
                    $precision = (int) $match[1];
                    $scale     = (int) $match[2];
                    $length    = null;
                }

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

            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'integer':
            case 'bigint':
            case 'year':
                $length = null;
                break;

            case 'enum':
                $values = $this->parseEnumExpression($tableColumn['column_type']);
                break;
        }

        if ($this->platform instanceof MariaDBPlatform) {
            $columnDefault = $this->getMariaDBColumnDefault($this->platform, $tableColumn['column_default']);
        } else {
            $columnDefault = $tableColumn['column_default'];
        }

        $options = [
            'length'        => $length !== null ? (int) $length : null,
            'unsigned'      => str_contains($tableColumn['column_type'], 'unsigned'),
            'fixed'         => $fixed,
            'default'       => $columnDefault,
            'notnull'       => $tableColumn['is_nullable'] !== 'YES',
            'scale'         => $scale,
            'precision'     => $precision,
            'autoincrement' => str_contains($tableColumn['extra'], 'auto_increment'),
            'values'        => $values,
        ];

        if ($tableColumn['column_comment'] !== null) {
            $options['comment'] = $tableColumn['column_comment'];
        }

        $column = new Column($tableColumn['column_name'], Type::getType($type), $options);
        $column->setPlatformOption('charset', $tableColumn['character_set_name']);
        $column->setPlatformOption('collation', $tableColumn['collation_name']);

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
                $list[$row['constraint_name']] = [
                    'name' => $row['constraint_name'],
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
        $sql = sprintf(
            <<<'SQL'
                SELECT TABLE_NAME AS %s
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_TYPE = 'BASE TABLE'
                ORDER BY TABLE_NAME
                SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
        );

        return $this->connection->executeQuery($sql, [$databaseName]);
    }

    protected function selectTableColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        // The schema name is passed multiple times as a literal in the WHERE clause instead of using a JOIN condition
        // in order to avoid performance issues on MySQL older than 8.0 and the corresponding MariaDB versions
        // caused by https://bugs.mysql.com/bug.php?id=81347
        $conditions = ['c.TABLE_SCHEMA = ?', 't.TABLE_SCHEMA = ?'];
        $params     = [$databaseName, $databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[] = 't.TABLE_NAME = ?';
            $params[]     = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
SELECT
       c.TABLE_NAME AS %s,
       c.COLUMN_NAME,
       %s AS COLUMN_TYPE,
       c.IS_NULLABLE,
       c.COLUMN_DEFAULT,
       c.EXTRA,
       c.COLUMN_COMMENT,
       c.CHARACTER_SET_NAME,
       c.COLLATION_NAME
FROM information_schema.COLUMNS c
    INNER JOIN information_schema.TABLES t
        ON t.TABLE_NAME = c.TABLE_NAME
 WHERE %s
   AND t.TABLE_TYPE = 'BASE TABLE'
ORDER BY c.TABLE_NAME,
         c.ORDINAL_POSITION
SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            $this->platform->getColumnTypeSQLSnippet('c', $databaseName),
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectIndexColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $conditions = ['TABLE_SCHEMA = ?'];
        $params     = [$databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[] = 'TABLE_NAME = ?';
            $params[]     = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
SELECT
        TABLE_NAME AS %s,
        NON_UNIQUE,
        INDEX_NAME AS Key_name,
        COLUMN_NAME,
        SUB_PART,
        INDEX_TYPE
FROM information_schema.STATISTICS
WHERE %s
AND INDEX_NAME != 'PRIMARY'
ORDER BY TABLE_NAME,
         SEQ_IN_INDEX
SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /** {@inheritDoc} */
    protected function fetchPrimaryKeyConstraintColumns(
        string $databaseName,
        ?OptionallyQualifiedName $tableName = null,
    ): array {
        // The schema name is passed multiple times as a literal in the WHERE clause instead of using a JOIN condition
        // in order to avoid performance issues on MySQL older than 8.0 and the corresponding MariaDB versions
        // caused by https://bugs.mysql.com/bug.php?id=81347
        $conditions = ['tc.TABLE_SCHEMA = ?', 'kcu.TABLE_SCHEMA = ?'];
        $params     = [$databaseName, $databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[] = 'tc.TABLE_NAME = ?';
            $params[]     = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
            SELECT
                tc.TABLE_NAME,
                tc.CONSTRAINT_NAME,
                kcu.COLUMN_NAME
            FROM
                information_schema.TABLE_CONSTRAINTS tc
            INNER JOIN
                information_schema.KEY_COLUMN_USAGE kcu
                ON kcu.TABLE_NAME = tc.TABLE_NAME
               AND kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
            WHERE %s
              AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
            ORDER BY TABLE_NAME,
                     kcu.ORDINAL_POSITION;
            SQL,
            implode(' AND ', $conditions),
        );

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    protected function selectForeignKeyColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        // The schema name is passed multiple times in the WHERE clause instead of using a JOIN condition
        // in order to avoid performance issues on MySQL older than 8.0 and the corresponding MariaDB versions
        // caused by https://bugs.mysql.com/bug.php?id=81347
        $conditions = ['k.TABLE_SCHEMA = ?', 'c.CONSTRAINT_SCHEMA = ?'];
        $params     = [$databaseName, $databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[] = 'k.TABLE_NAME = ?';
            $params[]     = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
SELECT
            k.TABLE_NAME AS %s,
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
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchTableOptionsByTable(string $databaseName, ?OptionallyQualifiedName $tableName = null): array
    {
        $sql = $this->platform->fetchTableOptionsByTable($tableName !== null);

        $params = [$databaseName];
        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $params[] = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        /** @var array<non-empty-string,array<string,mixed>> $metadata */
        $metadata = $this->connection->executeQuery($sql, $params)
            ->fetchAllAssociativeIndexed();

        $tableOptions = [];
        foreach ($metadata as $table => $data) {
            $data = array_change_key_case($data, CASE_LOWER);

            $tableOptions[self::NULL_SCHEMA_KEY][$table] = [
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
}
