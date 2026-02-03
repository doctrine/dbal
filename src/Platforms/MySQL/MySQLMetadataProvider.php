<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseRequired;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\UnsupportedName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Metadata\DatabaseMetadataRow;
use Doctrine\DBAL\Schema\Metadata\ForeignKeyConstraintColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\IndexColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\MetadataProvider;
use Doctrine\DBAL\Schema\Metadata\PrimaryKeyConstraintColumnRow;
use Doctrine\DBAL\Schema\Metadata\TableColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\TableMetadataRow;
use Doctrine\DBAL\Schema\Metadata\ViewMetadataRow;
use Doctrine\DBAL\Types\Exception\TypesException;

use function array_map;
use function assert;
use function explode;
use function implode;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function strtr;

final readonly class MySQLMetadataProvider implements MetadataProvider
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

    /** @var non-empty-string */
    private string $databaseName;

    /**
     * @internal This class can be instantiated only by a database platform.
     *
     * @throws Exception
     */
    public function __construct(private Connection $connection, private AbstractMySQLPlatform $platform)
    {
        $databaseName = $connection->fetchOne('SELECT DATABASE()');

        if ($databaseName === null) {
            throw DatabaseRequired::new(__METHOD__);
        }

        $this->databaseName = $databaseName;
    }

    /**
     * {@inheritDoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-schemata-table.html
     */
    public function getAllDatabaseNames(): iterable
    {
        $sql = <<<'SQL'
        SELECT SCHEMA_NAME
        FROM information_schema.SCHEMATA
        ORDER BY SCHEMA_NAME
        SQL;

        foreach ($this->connection->iterateColumn($sql) as $databaseName) {
            yield new DatabaseMetadataRow($databaseName);
        }
    }

    /** {@inheritDoc} */
    public function getAllSchemaNames(): iterable
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * {@inheritDoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-tables-table.html
     */
    public function getAllTableNames(): iterable
    {
        $sql = <<<'SQL'
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ?
          AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_NAME
        SQL;

        foreach ($this->connection->iterateNumeric($sql, [$this->databaseName]) as $row) {
            yield new TableMetadataRow(null, $row[0], []);
        }
    }

    /** {@inheritDoc} */
    public function getTableColumnsForAllTables(): iterable
    {
        return $this->getTableColumns(null);
    }

    /** {@inheritDoc} */
    public function getTableColumnsForTable(?string $schemaName, string $tableName): iterable
    {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getTableColumns($tableName);
    }

    /**
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-columns-table.html
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-tables-table.html
     *
     * @return iterable<TableColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getTableColumns(?string $tableName): iterable
    {
        // The schema name is passed multiple times in the WHERE clause instead of using a JOIN condition to avoid
        // performance issues on MySQL older than 8.0 and the corresponding MariaDB versions caused by
        // https://bugs.mysql.com/bug.php?id=81347
        $conditions = ['c.TABLE_SCHEMA = ?', 't.TABLE_SCHEMA = ?'];
        $params     = [$this->databaseName, $this->databaseName];

        if ($tableName !== null) {
            $conditions[] = 't.TABLE_NAME = ?';
            $params[]     = $tableName;
        }

        $sql = sprintf(
            <<<'SQL'
            SELECT c.TABLE_NAME,
                   c.COLUMN_NAME,
                   %s,
                   c.COLUMN_TYPE,
                   c.CHARACTER_MAXIMUM_LENGTH,
                   c.CHARACTER_OCTET_LENGTH,
                   c.NUMERIC_PRECISION,
                   c.NUMERIC_SCALE,
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
            $this->platform->getColumnTypeSQLSnippet('c', $this->databaseName),
            implode(' AND ', $conditions),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield $this->createTableColumn($row);
        }
    }

    /**
     * @param list<mixed> $row
     *
     * @throws TypesException
     */
    private function createTableColumn(array $row): TableColumnMetadataRow
    {
        [
            $tableName,
            $columnName,
            $dbType,
            $columnType,
            $characterMaximumLength,
            $characterOctetLength,
            $numericPrecision,
            $numericScale,
            $isNullable,
            $columnDefault,
            $extra,
            $columnComment,
            $characterSetName,
            $collationName,
        ] = $row;

        $editor = Column::editor()
            ->setQuotedName($columnName)
            ->setTypeName(
                $this->platform->getDoctrineTypeMapping($dbType),
            );

        if (str_contains($columnType, 'unsigned')) {
            $editor->setUnsigned(true);
        }

        switch ($dbType) {
            case 'char':
            case 'varchar':
                $editor->setLength((int) $characterMaximumLength);
                break;

            case 'binary':
            case 'varbinary':
                $editor->setLength((int) $characterOctetLength);
                break;

            case 'tinytext':
                $editor->setLength(AbstractMySQLPlatform::LENGTH_LIMIT_TINYTEXT);
                break;

            case 'text':
                $editor->setLength(AbstractMySQLPlatform::LENGTH_LIMIT_TEXT);
                break;

            case 'mediumtext':
                $editor->setLength(AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMTEXT);
                break;

            case 'tinyblob':
                $editor->setLength(AbstractMySQLPlatform::LENGTH_LIMIT_TINYBLOB);
                break;

            case 'blob':
                $editor->setLength(AbstractMySQLPlatform::LENGTH_LIMIT_BLOB);
                break;

            case 'mediumblob':
                $editor->setLength(AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMBLOB);
                break;

            case 'float':
            case 'double':
            case 'real':
            case 'numeric':
            case 'decimal':
                $editor->setPrecision((int) $numericPrecision);

                if ($numericScale !== null) {
                    $editor->setScale((int) $numericScale);
                }

                break;
        }

        switch ($dbType) {
            case 'char':
            case 'binary':
                $editor->setFixed(true);
                break;

            case 'enum':
                $editor->setValues($this->parseEnumExpression($columnType));
                break;
        }

        if ($this->platform instanceof MariaDBPlatform) {
            $default = $this->parseMariaDBColumnDefault($this->platform, $columnDefault);
        } else {
            $default = $columnDefault;
        }

        $editor
            ->setDefaultValue($default)
            ->setNotNull($isNullable !== 'YES')
            ->setComment($columnComment)
            ->setCharset($characterSetName)
            ->setCollation($collationName);

        if (str_contains($extra, 'auto_increment')) {
            $editor->setAutoincrement(true);
        }

        return new TableColumnMetadataRow(null, $tableName, $editor->create());
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
     * - Since MariaDB 10.2.7 column defaults stored in information_schema are quoted to distinguish them from
     *   expressions.
     * - The <code>CURRENT_TIMESTAMP</code>, <code>CURRENT_TIME</code> and <code>CURRENT_DATE</code> expressions
     *   are represented as "current_timestamp()", "curdate()" and "curtime()" respectively.
     * - Quoted 'NULL' is not enforced. It is technically possible to have "null" in some circumstances.
     * - Single quotes are always escaped by doubling, even if the original DDL used backslash escaping.
     *
     * @link https://mariadb.com/kb/en/library/information-schema-columns-table/
     * @link https://jira.mariadb.org/browse/MDEV-10134
     * @link https://jira.mariadb.org/browse/MDEV-13132
     * @link https://jira.mariadb.org/browse/MDEV-14053
     *
     * @param string|null $columnDefault default value as stored in information_schema for MariaDB >= 10.2.7
     */
    private function parseMariaDBColumnDefault(MariaDBPlatform $platform, ?string $columnDefault): ?string
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

    /** {@inheritDoc} */
    public function getIndexColumnsForAllTables(): iterable
    {
        return $this->getIndexColumns(null);
    }

    /** {@inheritDoc} */
    public function getIndexColumnsForTable(?string $schemaName, string $tableName): iterable
    {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getIndexColumns($tableName);
    }

    /**
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-statistics-table.html
     *
     * @return iterable<IndexColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getIndexColumns(?string $tableName): iterable
    {
        $conditions = ['TABLE_SCHEMA = ?'];
        $params     = [$this->databaseName];

        if ($tableName !== null) {
            $conditions[] = 'TABLE_NAME = ?';
            $params[]     = $tableName;
        }

        $sql = sprintf(
            <<<'SQL'
            SELECT TABLE_NAME,
                   INDEX_NAME,
                   INDEX_TYPE,
                   NON_UNIQUE,
                   COLUMN_NAME,
                   SUB_PART
            FROM information_schema.STATISTICS
            WHERE %s
              AND INDEX_NAME != 'PRIMARY'
            ORDER BY TABLE_NAME,
                INDEX_NAME,
                SEQ_IN_INDEX
            SQL,
            implode(' AND ', $conditions),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            if ($row[5] !== null) {
                $length = (int) $row[5];
                assert($length > 0);
            } else {
                $length = null;
            }

            if ($row[2] === 'FULLTEXT') {
                $type = IndexType::FULLTEXT;
            } elseif ($row[2] === 'SPATIAL') {
                $type = IndexType::SPATIAL;

                // the SUB_PART column may contain a non-null value for spatial indexes,
                // but this is not the prefix length
                $length = null;
            } elseif ($row[3]) {
                $type = IndexType::REGULAR;
            } else {
                $type = IndexType::UNIQUE;
            }

            yield new IndexColumnMetadataRow(
                schemaName: null,
                tableName: $row[0],
                indexName: $row[1],
                type: $type,
                isClustered: false,
                predicate: null,
                columnName: $row[4],
                columnLength: $length,
            );
        }
    }

    /** {@inheritDoc} */
    public function getPrimaryKeyConstraintColumnsForAllTables(): iterable
    {
        return $this->getPrimaryKeyConstraintColumns(null);
    }

    /** {@inheritDoc} */
    public function getPrimaryKeyConstraintColumnsForTable(
        ?string $schemaName,
        string $tableName,
    ): iterable {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getPrimaryKeyConstraintColumns($tableName);
    }

    /**
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-table-constraints-table.html
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-key-column-usage-table.html
     *
     * @return iterable<PrimaryKeyConstraintColumnRow>
     *
     * @throws Exception
     */
    private function getPrimaryKeyConstraintColumns(?string $tableName): iterable
    {
        // The schema name is passed multiple times in the WHERE clause instead of using a JOIN condition to avoid
        // performance issues on MySQL older than 8.0 and the corresponding MariaDB versions caused by
        // https://bugs.mysql.com/bug.php?id=81347
        $conditions = ['tc.TABLE_SCHEMA = ?', 'kcu.TABLE_SCHEMA = ?'];
        $params     = [$this->databaseName, $this->databaseName];

        if ($tableName !== null) {
            $conditions[] = 'tc.TABLE_NAME = ?';
            $params[]     = $tableName;
        }

        $sql = sprintf(
            <<<'SQL'
            SELECT tc.TABLE_NAME,
                   tc.CONSTRAINT_NAME,
                   kcu.COLUMN_NAME
            FROM information_schema.TABLE_CONSTRAINTS tc
                     INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
                                ON kcu.TABLE_NAME = tc.TABLE_NAME
                                    AND kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
            WHERE %s
              AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
            ORDER BY TABLE_NAME,
                kcu.ORDINAL_POSITION
            SQL,
            implode(' AND ', $conditions),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new PrimaryKeyConstraintColumnRow(
                schemaName: null,
                tableName: $row[0],
                constraintName: $row[1],
                isClustered: true,
                columnName: $row[2],
            );
        }
    }

    /** {@inheritDoc} */
    public function getForeignKeyConstraintColumnsForAllTables(): iterable
    {
        return $this->getForeignKeyConstraintColumns(null);
    }

    /** {@inheritDoc} */
    public function getForeignKeyConstraintColumnsForTable(
        ?string $schemaName,
        string $tableName,
    ): iterable {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getForeignKeyConstraintColumns($tableName);
    }

    /**
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-key-column-usage-table.html
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-referential-constraints-table.html
     *
     * @return iterable<ForeignKeyConstraintColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getForeignKeyConstraintColumns(?string $tableName): iterable
    {
        // The schema name is passed multiple times in the WHERE clause instead of using a JOIN condition
        // to avoid performance issues on MySQL older than 8.0 and the corresponding MariaDB versions caused by
        // https://bugs.mysql.com/bug.php?id=81347
        $conditions = ['k.TABLE_SCHEMA = ?', 'c.CONSTRAINT_SCHEMA = ?'];
        $params     = [$this->databaseName, $this->databaseName];

        if ($tableName !== null) {
            $conditions[] = 'k.TABLE_NAME = ?';
            $params[]     = $tableName;
        }

        $sql = sprintf(
            <<<'SQL'
            SELECT k.TABLE_NAME,
                   k.CONSTRAINT_NAME,
                   k.REFERENCED_TABLE_NAME,
                   c.UPDATE_RULE,
                   c.DELETE_RULE,
                   k.COLUMN_NAME,
                   k.REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE k
                     INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS c
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

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new ForeignKeyConstraintColumnMetadataRow(
                referencingSchemaName: null,
                referencingTableName: $row[0],
                id: null,
                name: $row[1],
                referencedSchemaName: null,
                referencedTableName: $row[2],
                matchType: MatchType::SIMPLE,
                onUpdateAction: $this->createReferentialAction($row[3]),
                onDeleteAction: $this->createReferentialAction($row[4]),
                isDeferrable: false,
                isDeferred: false,
                referencingColumnName: $row[5],
                referencedColumnName: $row[6],
            );
        }
    }

    private function createReferentialAction(string $value): ReferentialAction
    {
        $action = ReferentialAction::tryFrom($value);
        assert($action !== null);

        return $action;
    }

    /** {@inheritDoc} */
    public function getTableOptionsForAllTables(): iterable
    {
        return $this->getTableOptions(null);
    }

    /** {@inheritDoc} */
    public function getTableOptionsForTable(?string $schemaName, string $tableName): iterable
    {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getTableOptions($tableName);
    }

    /**
     * @return iterable<TableMetadataRow>
     *
     * @throws Exception
     */
    private function getTableOptions(?string $tableName): iterable
    {
        $sql = $this->platform->fetchTableOptionsByTable($tableName !== null);

        $params = [$this->databaseName];
        if ($tableName !== null) {
            $params[] = $tableName;
        }

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new TableMetadataRow(null, $row[0], [
                'engine'         => $row[1],
                'autoincrement'  => $row[2],
                'comment'        => $row[3],
                'create_options' => $this->parseCreateOptions($row[4]),
                'collation'      => $row[5],
                'charset'        => $row[6],
            ]);
        }
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

    /**
     * {@inheritDoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-views-table.html
     */
    public function getAllViews(): iterable
    {
        $sql = <<<'SQL'
            SELECT TABLE_NAME,
                   VIEW_DEFINITION
            FROM information_schema.VIEWS
            WHERE TABLE_SCHEMA = ?
            ORDER BY TABLE_NAME
            SQL;

        foreach ($this->connection->iterateNumeric($sql, [$this->databaseName]) as $row) {
            yield new ViewMetadataRow(null, ...$row);
        }
    }

    /** {@inheritDoc} */
    public function getAllSequences(): iterable
    {
        throw NotSupported::new(__METHOD__);
    }
}
