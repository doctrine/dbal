<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\SQLServer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
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
use Doctrine\DBAL\Schema\Metadata\SchemaMetadataRow;
use Doctrine\DBAL\Schema\Metadata\SequenceMetadataRow;
use Doctrine\DBAL\Schema\Metadata\TableColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\TableMetadataRow;
use Doctrine\DBAL\Schema\Metadata\ViewMetadataRow;
use Doctrine\DBAL\Types\Exception\TypesException;

use function assert;
use function implode;
use function preg_match;
use function sprintf;
use function str_replace;

final readonly class SQLServerMetadataProvider implements MetadataProvider
{
    /** @internal This class can be instantiated only by a database platform. */
    public function __construct(private Connection $connection, private SQLServerPlatform $platform)
    {
    }

    /** {@inheritDoc} */
    public function getAllDatabaseNames(): iterable
    {
        $sql = <<<'SQL'
        SELECT name
        FROM sys.databases
        ORDER BY name
        SQL;

        foreach ($this->connection->iterateColumn($sql) as $databaseName) {
            yield new DatabaseMetadataRow($databaseName);
        }
    }

    /** {@inheritDoc} */
    public function getAllSchemaNames(): iterable
    {
        $sql = <<<'SQL'
        SELECT name
        FROM sys.schemas
        WHERE name NOT LIKE 'db_%'
          AND name NOT IN ('guest', 'INFORMATION_SCHEMA', 'sys')
        SQL;

        foreach ($this->connection->iterateColumn($sql) as $schemaName) {
            yield new SchemaMetadataRow($schemaName);
        }
    }

    /** {@inheritDoc} */
    public function getAllTableNames(): iterable
    {
        $sql = sprintf(
            <<<'SQL'
            SELECT s.name,
                   t.name
            FROM sys.tables AS t
                     JOIN sys.schemas AS s
                          ON t.schema_id = s.schema_id
            WHERE %s
              AND %s
            ORDER BY s.name,
                     t.name
            SQL,
            $this->buildSchemaNamePredicate('s.name'),
            $this->buildTableNamePredicate('t.name'),
        );

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            yield new TableMetadataRow($row[0], $row[1], []);
        }
    }

    /** {@inheritDoc} */
    public function getTableColumnsForAllTables(): iterable
    {
        return $this->getTableColumns(null, null);
    }

    /** {@inheritDoc} */
    public function getTableColumnsForTable(?string $schemaName, string $tableName): iterable
    {
        if ($schemaName === null) {
            throw UnsupportedName::fromNullSchemaName(__METHOD__);
        }

        return $this->getTableColumns($schemaName, $tableName);
    }

    /**
     * @link https://learn.microsoft.com/en-us/sql/relational-databases/system-catalog-views/extended-properties-catalog-views-sys-extended-properties
     *
     * @return iterable<TableColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getTableColumns(?string $schemaName, ?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT scm.name,
                   tbl.name,
                   col.name,
                   type.name,
                   col.max_length,
                   col.is_nullable,
                   def.definition,
                   def.name,
                   col.precision,
                   col.scale,
                   col.is_identity,
                   col.collation_name,
                   -- CAST avoids driver error for sql_variant type
                   CAST(prop.value AS NVARCHAR(MAX))
            FROM sys.columns AS col
                     JOIN sys.types AS type
                          ON col.user_type_id = type.user_type_id
                     JOIN sys.tables AS tbl
                          ON col.object_id = tbl.object_id
                     JOIN sys.schemas AS scm
                          ON tbl.schema_id = scm.schema_id
                     LEFT JOIN sys.default_constraints def
                               ON col.default_object_id = def.object_id
                                   AND col.object_id = def.parent_object_id
                     LEFT JOIN sys.extended_properties AS prop
                               ON tbl.object_id = prop.major_id
                                   AND col.column_id = prop.minor_id
                                   AND prop.name = N'MS_Description'
            WHERE %s
            ORDER BY scm.name,
                     tbl.name,
                     col.column_id
            SQL,
            $this->buildTableQueryPredicate('scm', $schemaName, 'tbl', $tableName, $params),
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
            $schemaName,
            $tableName,
            $columnName,
            $dbType,
            $length,
            $isNullable,
            $defaultExpression,
            $defaultConstraintName,
            $precision,
            $scale,
            $isIdentity,
            $collationName,
            $description,
        ] = $row;

        $length = (int) $length;

        switch ($dbType) {
            case 'nchar':
            case 'ntext':
                // Unicode data requires 2 bytes per character
                $length = (int) ($length / 2);
                break;

            case 'nvarchar':
                if ($length === -1) {
                    break;
                }

                // Unicode data requires 2 bytes per character
                $length = (int) ($length / 2);
                break;

            case 'varchar':
                // TEXT type is returned as VARCHAR(MAX) with a length of -1
                if ($length === -1) {
                    $dbType = 'text';
                }

                break;

            case 'varbinary':
                if ($length === -1) {
                    $dbType = 'blob';
                }

                break;
        }

        $type = $this->platform->getDoctrineTypeMapping($dbType);

        $editor = Column::editor()
            ->setQuotedName($columnName)
            ->setTypeName(
                $this->platform->getDoctrineTypeMapping($dbType),
            )
            ->setNotNull(! $isNullable)
            ->setAutoincrement((bool) $isIdentity);

        if ($precision !== null) {
            $editor->setPrecision((int) $precision);
        }

        if ($scale !== null) {
            $editor->setScale((int) $scale);
        }

        if ($dbType === 'char' || $dbType === 'nchar' || $dbType === 'binary') {
            $editor->setFixed(true);
        }

        if ($description !== null) {
            $editor->setComment($description);
        }

        if ($length !== 0 && ($type === 'text' || $type === 'string' || $type === 'binary')) {
            $editor->setLength($length);
        }

        if ($defaultExpression !== null) {
            $editor
                ->setDefaultValue($this->parseDefaultExpression($defaultExpression))
                ->setDefaultConstraintName($defaultConstraintName);
        }

        $editor->setCollation($collationName);

        return new TableColumnMetadataRow($schemaName, $tableName, $editor->create());
    }

    private function parseDefaultExpression(string $value): ?string
    {
        while (preg_match('/^\((.*)\)$/s', $value, $matches) === 1) {
            $value = $matches[1];
        }

        if ($value === 'NULL') {
            return null;
        }

        if (preg_match('/^\'(.*)\'$/s', $value, $matches) === 1) {
            $value = str_replace("''", "'", $matches[1]);
        }

        if ($value === 'getdate()') {
            return $this->platform->getCurrentTimestampSQL();
        }

        return $value;
    }

    /** {@inheritDoc} */
    public function getIndexColumnsForAllTables(): iterable
    {
        return $this->getIndexColumns(null, null);
    }

    /** {@inheritDoc} */
    public function getIndexColumnsForTable(?string $schemaName, string $tableName): iterable
    {
        if ($schemaName === null) {
            throw UnsupportedName::fromNullSchemaName(__METHOD__);
        }

        return $this->getIndexColumns($schemaName, $tableName);
    }

    /**
     * @return iterable<IndexColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getIndexColumns(?string $schemaName, ?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT s.name,
                   t.name,
                   i.name,
                   i.is_unique,
                   i.type,
                   c.name
            FROM sys.tables AS t
                     JOIN sys.schemas AS s
                          ON t.schema_id = s.schema_id
                     JOIN sys.indexes AS i
                          ON t.object_id = i.object_id
                     JOIN sys.index_columns AS idxcol
                          ON i.object_id = idxcol.object_id
                              AND i.index_id = idxcol.index_id
                     JOIN sys.columns AS c
                          ON idxcol.object_id = c.object_id
                              AND idxcol.column_id = c.column_id
            WHERE %s
              AND i.is_primary_key = 0
            ORDER BY s.name,
                     t.name,
                     i.name,
                     idxcol.key_ordinal
            SQL,
            $this->buildTableQueryPredicate('s', $schemaName, 't', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new IndexColumnMetadataRow(
                schemaName: $row[0],
                tableName: $row[1],
                indexName: $row[2],
                type: $row[3] ? IndexType::UNIQUE : IndexType::REGULAR,
                isClustered: (int) $row[4] === 1,
                predicate: null,
                columnName: $row[5],
                columnLength: null,
            );
        }
    }

    /** {@inheritDoc} */
    public function getPrimaryKeyConstraintColumnsForAllTables(): iterable
    {
        return $this->getPrimaryKeyConstraintColumns(null, null);
    }

    /** {@inheritDoc} */
    public function getPrimaryKeyConstraintColumnsForTable(
        ?string $schemaName,
        string $tableName,
    ): iterable {
        if ($schemaName === null) {
            throw UnsupportedName::fromNullSchemaName(__METHOD__);
        }

        return $this->getPrimaryKeyConstraintColumns($schemaName, $tableName);
    }

    /**
     * @return iterable<PrimaryKeyConstraintColumnRow>
     *
     * @throws Exception
     */
    private function getPrimaryKeyConstraintColumns(?string $schemaName, ?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT s.name,
                   t.name,
                   i.name,
                   i.type,
                   c.name
            FROM sys.schemas s
                     INNER JOIN sys.tables t
                                ON t.schema_id = s.schema_id
                     INNER JOIN sys.indexes i
                                ON i.object_id = t.object_id
                                    AND i.is_primary_key = 1
                     INNER JOIN sys.index_columns ic
                                ON ic.object_id = t.object_id
                                    AND ic.index_id = i.index_id
                     INNER JOIN sys.columns c
                                ON c.object_id = t.object_id
                                    AND c.column_id = ic.column_id
            WHERE %s
            ORDER BY s.name,
                     t.name,
                     ic.key_ordinal
            SQL,
            $this->buildTableQueryPredicate('s', $schemaName, 't', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new PrimaryKeyConstraintColumnRow(
                schemaName: $row[0],
                tableName: $row[1],
                constraintName: $row[2],
                isClustered: (int) $row[3] === 1,
                columnName: $row[4],
            );
        }
    }

    /** {@inheritDoc} */
    public function getForeignKeyConstraintColumnsForAllTables(): iterable
    {
        return $this->getForeignKeyConstraintColumns(null, null);
    }

    /** {@inheritDoc} */
    public function getForeignKeyConstraintColumnsForTable(
        ?string $schemaName,
        string $tableName,
    ): iterable {
        if ($schemaName === null) {
            throw UnsupportedName::fromNullSchemaName(__METHOD__);
        }

        return $this->getForeignKeyConstraintColumns($schemaName, $tableName);
    }

    /**
     * @return iterable<ForeignKeyConstraintColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getForeignKeyConstraintColumns(?string $schemaName, ?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT pks.name,
                   pkt.name,
                   fk.name,
                   fks.name,
                   fkt.name,
                   fk.update_referential_action_desc,
                   fk.delete_referential_action_desc,
                   pkc.name,
                   fkc.name
            FROM sys.foreign_keys AS fk
                     JOIN sys.foreign_key_columns AS c
                          ON fk.object_id = c.constraint_object_id
                     JOIN sys.tables AS pkt
                          ON pkt.object_id = fk.parent_object_id
                     JOIN sys.schemas AS pks
                          ON pks.schema_id = pkt.schema_id
                     JOIN sys.columns AS pkc
                          ON pkc.object_id = c.parent_object_id
                              AND pkc.column_id = c.parent_column_id
                     JOIN sys.tables AS fkt
                          ON fkt.object_id = fk.referenced_object_id
                     JOIN sys.schemas AS fks
                          ON fks.schema_id = fkt.schema_id
                     JOIN sys.columns AS fkc
                          ON fkc.object_id = c.referenced_object_id
                              AND fkc.column_id = c.referenced_column_id
            WHERE %s
            ORDER BY pks.name,
                     pkt.name,
                     fk.name,
                     c.constraint_column_id
SQL,
            $this->buildTableQueryPredicate('pks', $schemaName, 'pkt', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new ForeignKeyConstraintColumnMetadataRow(
                referencingSchemaName: $row[0],
                referencingTableName: $row[1],
                id: null,
                name: $row[2],
                referencedSchemaName: $row[3],
                referencedTableName: $row[4],
                matchType: MatchType::SIMPLE,
                onUpdateAction: $this->createReferentialAction($row[5]),
                onDeleteAction: $this->createReferentialAction($row[6]),
                isDeferrable: false,
                isDeferred: false,
                referencingColumnName: $row[7],
                referencedColumnName: $row[8],
            );
        }
    }

    private function createReferentialAction(string $value): ReferentialAction
    {
        $action = ReferentialAction::tryFrom(str_replace('_', ' ', $value));
        assert($action !== null);

        return $action;
    }

    /** {@inheritDoc} */
    public function getTableOptionsForAllTables(): iterable
    {
        return $this->getTableOptions(null, null);
    }

    /** {@inheritDoc} */
    public function getTableOptionsForTable(
        ?string $schemaName,
        string $tableName,
    ): iterable {
        if ($schemaName === null) {
            throw UnsupportedName::fromNullSchemaName(__METHOD__);
        }

        return $this->getTableOptions($schemaName, $tableName);
    }

    /**
     * @return iterable<TableMetadataRow>
     *
     * @throws Exception
     */
    private function getTableOptions(?string $schemaName, ?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT scm.name,
                   tbl.name,
                   p.value
            FROM sys.tables AS tbl
                     JOIN sys.schemas AS scm
                          ON tbl.schema_id = scm.schema_id
                     LEFT JOIN sys.extended_properties AS p
                          ON p.major_id = tbl.object_id
                              AND p.minor_id = 0
                              AND p.class = 1
                              AND p.name = N'MS_Description'
            WHERE %s
            SQL,
            $this->buildTableQueryPredicate('scm', $schemaName, 'tbl', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new TableMetadataRow($row[0], $row[1], [
                'comment' => $row[2],
            ]);
        }
    }

    /**
     * @param list<int|string> $params
     *
     * @return non-empty-string
     */
    private function buildTableQueryPredicate(
        string $schemaRelation,
        ?string $schemaName,
        string $tableRelation,
        ?string $tableName,
        array &$params,
    ): string {
        assert(($tableName === null) === ($schemaName === null));

        $conditions = [];

        if ($tableName !== null && $schemaName !== null) {
            $conditions = [sprintf('%s.name = ?', $schemaRelation)];
            $params[]   = $schemaName;

            $conditions[] = sprintf('%s.name = ?', $tableRelation);
            $params[]     = $tableName;
        }

        $conditions[] = $this->buildSchemaNamePredicate($schemaRelation . '.name');
        $conditions[] = $this->buildTableNamePredicate($tableRelation . '.name');

        return implode(' AND ', $conditions);
    }

    /**
     * {@inheritDoc}
     *
     * @link https://learn.microsoft.com/en-us/sql/relational-databases/system-catalog-views/sys-views-transact-sql
     */
    public function getAllViews(): iterable
    {
        $sql = sprintf(
            <<<'SQL'
            SELECT s.name,
                   v.name,
                   m.definition
            FROM sys.views v
                     JOIN sys.schemas s
                          ON v.schema_id = s.schema_id
                     JOIN sys.sql_modules m
                          ON v.object_id = m.object_id
            WHERE %s
            ORDER BY s.name,
                     v.name
            SQL,
            $this->buildSchemaNamePredicate('s.name'),
        );

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            yield new ViewMetadataRow(...$row);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @link https://learn.microsoft.com/en-us/sql/relational-databases/system-catalog-views/sys-sequences-transact-sql
     */
    public function getAllSequences(): iterable
    {
        $sql = <<<'SQL'
        SELECT scm.name,
               seq.name,
               seq.increment,
               seq.start_value
        FROM sys.sequences AS seq
                 JOIN sys.schemas AS scm
                      ON scm.schema_id = seq.schema_id
        SQL;

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            yield new SequenceMetadataRow(
                schemaName: $row[0],
                sequenceName: $row[1],
                allocationSize: (int) $row[2],
                initialValue: (int) $row[3],
                cacheSize: null,
            );
        }
    }

    private function buildSchemaNamePredicate(string $columnName): string
    {
        return sprintf(
            "%1\$s NOT LIKE 'db\_%%' AND %1\$s NOT IN ('guest', 'INFORMATION_SCHEMA', 'sys')",
            $columnName,
        );
    }

    private function buildTableNamePredicate(string $columnName): string
    {
        // The "sysdiagrams" table must be ignored as it's internal SQL Server table for Database Diagrams
        return sprintf("%s != 'sysdiagrams'", $columnName);
    }
}
