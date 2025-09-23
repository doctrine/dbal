<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\SQLite;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\SQLite\SQLiteMetadataProvider\ForeignKeyConstraintDetails;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\UnsupportedName;
use Doctrine\DBAL\Schema\Exception\UnsupportedSchema;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Metadata\ForeignKeyConstraintColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\IndexColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\MetadataProvider;
use Doctrine\DBAL\Schema\Metadata\PrimaryKeyConstraintColumnRow;
use Doctrine\DBAL\Schema\Metadata\TableColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\TableMetadataRow;
use Doctrine\DBAL\Schema\Metadata\ViewMetadataRow;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Types;

use function array_map;
use function assert;
use function count;
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
use function strlen;
use function strtolower;
use function substr;

final readonly class SQLiteMetadataProvider implements MetadataProvider
{
    /** @internal This class can be instantiated only by a database platform. */
    public function __construct(private Connection $connection, private SQLitePlatform $platform)
    {
    }

    /** {@inheritDoc} */
    public function getAllDatabaseNames(): iterable
    {
        throw NotSupported::new(__METHOD__);
    }

    /** {@inheritDoc} */
    public function getAllSchemaNames(): iterable
    {
        throw NotSupported::new(__METHOD__);
    }

    /** {@inheritDoc} */
    public function getAllTableNames(): iterable
    {
        foreach ($this->getTableNames() as $tableName) {
            yield new TableMetadataRow(null, $tableName, []);
        }
    }

    /**
     * @return iterable<non-empty-string>
     *
     * @throws Exception
     */
    private function getTableNames(): iterable
    {
        $sql = sprintf(
            <<<'SQL'
            SELECT name
            FROM sqlite_master
            WHERE type = 'table'
              AND %s
            SQL,
            $this->buildTableNamePredicate('name'),
        );

        yield from $this->connection->iterateColumn($sql);
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
     * @link https://www.sqlite.org/pragma.html#pragma_table_info
     *
     * @return iterable<TableColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getTableColumns(?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT t.name,
                   c.name,
                   c.type,
                   c."notnull",
                   c.dflt_value
            FROM sqlite_master t
                     JOIN pragma_table_info(t.name) c
            WHERE %s
            ORDER BY t.name,
                     c.cid
            SQL,
            $this->buildTableQueryPredicate($tableName, $params),
        );

        $rows = $sqlByTableName = [];

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            [$tableName] = $row;
            $rows[]      = $row;

            $sqlByTableName[$tableName] ??= $this->getCreateTableSQL($tableName);
        }

        foreach ($rows as $row) {
            yield $this->createTableColumn($row, $sqlByTableName);
        }
    }

    /**
     * @param list<mixed>           $row
     * @param array<string, string> $sqlByTableName
     *
     * @throws TypesException
     */
    private function createTableColumn(array $row, array $sqlByTableName): TableColumnMetadataRow
    {
        [$tableName, $columnName, $type, $notNull, $defaultExpression] = $row;

        $matchResult = preg_match('/^([A-Z\s]+?)(?:\s*\((\d+)(?:,\s*(\d+))?\))?$/i', $type, $matches);
        assert($matchResult === 1);

        $editor = Column::editor()
            ->setQuotedName($columnName);

        $dbType = strtolower($matches[1]);

        if (str_contains($dbType, ' unsigned')) {
            $dbType = str_replace(' unsigned', '', $dbType);
            $editor->setUnsigned(true);
        }

        $typeName = $this->platform->getDoctrineTypeMapping($dbType);

        $editor->setTypeName($typeName);

        if ($dbType === 'char') {
            $editor->setFixed(true);
        }

        if (isset($matches[2])) {
            if (isset($matches[3])) {
                $editor
                    ->setPrecision((int) $matches[2])
                    ->setScale((int) $matches[3]);
            } else {
                $editor->setLength((int) $matches[2]);
            }
        }

        if ($defaultExpression !== null) {
            $editor->setDefaultValue(
                $this->parseDefaultExpression($defaultExpression),
            );
        }

        $tableSQL = $sqlByTableName[$tableName];

        $editor
            ->setAutoincrement(
                $this->parseColumnAutoIncrementFromSQL($columnName, $tableSQL),
            )
            ->setComment(
                $this->parseColumnCommentFromSQL($columnName, $tableSQL),
            )
            ->setNotNull((bool) $notNull);

        if ($typeName === Types::STRING || $typeName === Types::TEXT) {
            $editor->setCollation(
                $this->parseColumnCollationFromSQL($columnName, $tableSQL) ?? 'BINARY',
            );
        }

        return new TableColumnMetadataRow(null, $tableName, $editor->create());
    }

    private function parseDefaultExpression(string $value): ?string
    {
        if ($value === 'NULL') {
            return null;
        }

        if (preg_match('/^\'(.*)\'$/s', $value, $matches) === 1) {
            $value = str_replace("''", "'", $matches[1]);
        }

        return $value;
    }

    /** @link https://www.sqlite.org/autoinc.html#the_autoincrement_keyword */
    private function parseColumnAutoIncrementFromSQL(string $column, string $sql): bool
    {
        $pattern = '/' . $this->buildIdentifierPattern($column) . 'INTEGER\s+PRIMARY\s+KEY\s+AUTOINCREMENT/i';

        return preg_match($pattern, $sql) === 1;
    }

    /** @return ?non-empty-string */
    private function parseColumnCollationFromSQL(string $column, string $sql): ?string
    {
        $pattern = '{' . $this->buildIdentifierPattern($column)
            . '[^,(]+(?:\([^()]+\)[^,]*)?(?:(?:DEFAULT|CHECK)\s*(?:\(.*?\))?[^,]*)*COLLATE\s+["\']?([^\s,"\')]+)}is';

        if (preg_match($pattern, $sql, $match) !== 1) {
            return null;
        }

        assert(strlen($match[1]) > 0);

        return $match[1];
    }

    private function parseColumnCommentFromSQL(string $column, string $sql): string
    {
        $pattern = '{[\s(,]' . $this->buildIdentifierPattern($column) . '(?:\([^)]*?\)|[^,(])*?,?(\s*--[^\n]*\n?)+}i';

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
     * @link https://www.sqlite.org/pragma.html#pragma_index_info
     * @link https://www.sqlite.org/pragma.html#pragma_index_list
     * @link https://www.sqlite.org/fileformat2.html#internal_schema_objects
     *
     * @return iterable<IndexColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getIndexColumns(?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT t.name,
                   i.name,
                   i."unique",
                   c.name
            FROM sqlite_master t
                     JOIN pragma_index_list(t.name) i
                     JOIN pragma_index_info(i.name) c
            WHERE %s
              AND i.name NOT LIKE 'sqlite_%%'
            ORDER BY t.name,
                     i.name,
                     c.seqno
            SQL,
            $this->buildTableQueryPredicate($tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new IndexColumnMetadataRow(
                schemaName: null,
                tableName: $row[0],
                indexName: $row[1],
                type: $row[2] ? IndexType::UNIQUE : IndexType::REGULAR,
                isClustered: false,
                predicate: null,
                columnName: $row[3],
                columnLength: null,
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
     * @link https://www.sqlite.org/pragma.html#pragma_table_info
     *
     * @return iterable<PrimaryKeyConstraintColumnRow>
     *
     * @throws Exception
     */
    private function getPrimaryKeyConstraintColumns(?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT t.name,
                   c.name
            FROM sqlite_master t
                     JOIN pragma_table_info(t.name) c
            WHERE %s
              AND c.pk > 0
            ORDER BY t.name,
                     c.pk
        SQL,
            $this->buildTableQueryPredicate($tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new PrimaryKeyConstraintColumnRow(
                schemaName: null,
                tableName: $row[0],
                constraintName: null,
                isClustered: true,
                columnName: $row[1],
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
     * @link https://sqlite.org/pragma.html#pragma_foreign_key_list
     *
     * @return iterable<ForeignKeyConstraintColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getForeignKeyConstraintColumns(?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT t.name,
                   c.id,
                   c."table",
                   c.on_update,
                   c.on_delete,
                   c."from",
                   c."to"
            FROM sqlite_master t
                     JOIN pragma_foreign_key_list(t.name) c
            WHERE %s
            ORDER BY t.name,
                     c.id DESC,
                     c.seq
SQL,
            $this->buildTableQueryPredicate($tableName, $params),
        );

        return $this->generateForeignKeyConstraintColumns(
            $this->connection->iterateNumeric($sql, $params),
        );
    }

    /**
     * @param iterable<list<mixed>> $rows
     *
     * @return iterable<ForeignKeyConstraintColumnMetadataRow>
     *
     * @throws Exception
     */
    private function generateForeignKeyConstraintColumns(iterable $rows): iterable
    {
        $currentTableName = null;
        $currentDetails   = [];

        foreach ($rows as $row) {
            $tableName = $row[0];
            $id        = $row[1];

            if ($tableName !== $currentTableName) {
                $currentDetails   = $this->getForeignKeyConstraintDetails($tableName);
                $currentTableName = $tableName;
            }

            // SQLite identifies foreign keys in reverse order of appearance in SQL
            $details = $currentDetails[count($currentDetails) - $id - 1];

            $name = $details->getName();

            if ($row[6] !== null) {
                $referencedColumnNames = [$row[6]];
            } else {
                // inferring a shorthand form for the foreign key constraint,
                // where the referenced column names are omitted
                $referencedColumnNames = [];

                foreach ($this->getPrimaryKeyConstraintColumns($row[2]) as $primaryKeyConstraintColumn) {
                    $referencedColumnNames[] = $primaryKeyConstraintColumn->getColumnName();
                }

                if (count($referencedColumnNames) === 0) {
                    throw UnsupportedSchema::sqliteMissingForeignKeyConstraintReferencedColumns(
                        $name,
                        $tableName,
                        $row[2],
                    );
                }
            }

            foreach ($referencedColumnNames as $referencedColumnName) {
                yield new ForeignKeyConstraintColumnMetadataRow(
                    referencingSchemaName: null,
                    referencingTableName: $tableName,
                    id: $row[1],
                    name: $name,
                    referencedSchemaName: null,
                    referencedTableName: $row[2],
                    matchType: MatchType::SIMPLE,
                    onUpdateAction: $this->createReferentialAction($row[3]),
                    onDeleteAction: $this->createReferentialAction($row[4]),
                    isDeferrable: $details->isDeferrable(),
                    isDeferred: $details->isDeferred(),
                    referencingColumnName: $row[5],
                    referencedColumnName: $referencedColumnName,
                );
            }
        }
    }

    private function createReferentialAction(string $value): ReferentialAction
    {
        $action = ReferentialAction::tryFrom($value);
        assert($action !== null);

        return $action;
    }

    /**
     * @return list<ForeignKeyConstraintDetails>
     *
     * @throws Exception
     */
    private function getForeignKeyConstraintDetails(string $tableName): array
    {
        $sql = $this->getCreateTableSQL($tableName);

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
                $sql,
                $matches,
            ) === 0
        ) {
            return [];
        }

        $names      = $matches[1];
        $deferrable = $matches[2];
        $deferred   = $matches[3];
        $details    = [];

        for ($i = 0, $count = count($matches[0]); $i < $count; $i++) {
            $details[] = new ForeignKeyConstraintDetails(
                $this->parseOptionallyQuotedName($names[$i]),
                strcasecmp($deferrable[$i], 'deferrable') === 0,
                strcasecmp($deferred[$i], 'deferred') === 0,
            );
        }

        return $details;
    }

    /** @return ?non-empty-string */
    private function parseOptionallyQuotedName(string $sql): ?string
    {
        if ($sql === '') {
            return null;
        }

        if (str_starts_with($sql, '"') && str_ends_with($sql, '"')) {
            $name = str_replace('""', '"', substr($sql, 1, -1));
            assert(strlen($name) > 0);

            return $name;
        }

        return $sql;
    }

    /** @throws Exception */
    private function getCreateTableSQL(string $tableName): string
    {
        $sql = $this->connection->fetchOne(
            <<<'SQL'
            SELECT sql
            FROM sqlite_master
            WHERE type = 'table'
              AND name = ?
            SQL,
            [$tableName],
        );

        assert($sql !== false);

        return $sql;
    }

    /** {@inheritDoc} */
    public function getTableOptionsForAllTables(): iterable
    {
        return $this->getTableOptions($this->getTableNames());
    }

    /** {@inheritDoc} */
    public function getTableOptionsForTable(
        ?string $schemaName,
        string $tableName,
    ): iterable {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getTableOptions([$tableName]);
    }

    /**
     * @param iterable<non-empty-string> $tableNames
     *
     * @return iterable<TableMetadataRow>
     *
     * @throws Exception
     */
    private function getTableOptions(iterable $tableNames): iterable
    {
        foreach ($tableNames as $tableName) {
            yield new TableMetadataRow(null, $tableName, [
                'comment' => $this->parseTableCommentFromSQL(
                    $tableName,
                    $this->getCreateTableSQL($tableName),
                ),
            ]);
        }
    }

    /**
     * @param list<string> $params
     *
     * @return non-empty-string
     */
    private function buildTableQueryPredicate(?string $tableName, array &$params): string
    {
        $conditions = [
            "t.type = 'table'",
            $this->buildTableNamePredicate('t.name'),
        ];

        if ($tableName !== null) {
            $conditions[] = 't.name = ?';
            $params[]     = $tableName;
        }

        return implode(' AND ', $conditions);
    }

    private function parseTableCommentFromSQL(string $table, string $sql): ?string
    {
        $pattern = sprintf(
            <<<'PATTERN'
            /CREATE\s+TABLE%s
            ( # Start capture
               (?:\s*--[^\n]*\n?)+ # Capture anything that starts with whitespaces followed by -- until the end
                                   # of the line(s)
            )/ix
            PATTERN,
            $this->buildIdentifierPattern($table),
        );

        if (preg_match($pattern, $sql, $match) !== 1) {
            return null;
        }

        $comment = preg_replace('{^\s*--}m', '', rtrim($match[1], "\n"));

        return $comment === '' ? null : $comment;
    }

    /** {@inheritDoc} */
    public function getAllViews(): iterable
    {
        $sql = <<<'SQL'
        SELECT name,
               sql
        FROM sqlite_master
        WHERE type = 'view'
        ORDER BY name
        SQL;

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            yield new ViewMetadataRow(null, ...$row);
        }
    }

    /** {@inheritDoc} */
    public function getAllSequences(): iterable
    {
        throw NotSupported::new(__METHOD__);
    }

    private function buildTableNamePredicate(string $columnName): string
    {
        return sprintf("%s NOT IN ('geometry_columns', 'spatial_ref_sys', 'sqlite_sequence')", $columnName);
    }
}
