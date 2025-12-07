<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\PostgreSQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
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
use function count;
use function implode;
use function is_numeric;
use function is_string;
use function preg_match;
use function sprintf;
use function str_replace;
use function str_starts_with;

final readonly class PostgreSQLMetadataProvider implements MetadataProvider
{
    /** @link https://www.postgresql.org/docs/current/catalog-pg-constraint.html */
    private const REFERENTIAL_ACTIONS = [
        'a' => ReferentialAction::NO_ACTION,
        'c' => ReferentialAction::CASCADE,
        'd' => ReferentialAction::SET_DEFAULT,
        'n' => ReferentialAction::SET_NULL,
        'r' => ReferentialAction::RESTRICT,
    ];

    /** @internal This class can be instantiated only by a database platform. */
    public function __construct(private Connection $connection, private PostgreSQLPlatform $platform)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @link https://www.postgresql.org/docs/current/catalog-pg-database.html
     */
    public function getAllDatabaseNames(): iterable
    {
        $sql = <<<'SQL'
        SELECT datname
        FROM pg_database
        ORDER BY datname
        SQL;

        foreach ($this->connection->iterateColumn($sql) as $databaseName) {
            assert(is_string($databaseName) && $databaseName !== '');

            yield new DatabaseMetadataRow($databaseName);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @link https://www.postgresql.org/docs/current/catalog-pg-namespace.html
     */
    public function getAllSchemaNames(): iterable
    {
        $sql = sprintf(
            <<<'SQL'
            SELECT nspname
            FROM pg_namespace
            WHERE %s
            ORDER BY nspname
            SQL,
            $this->buildNamespaceNamePredicate('nspname'),
        );

        foreach ($this->connection->iterateColumn($sql) as $schemaName) {
            assert(is_string($schemaName) && $schemaName !== '');

            yield new SchemaMetadataRow($schemaName);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @link https://www.postgresql.org/docs/current/catalog-pg-class.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-namespace.html
     */
    public function getAllTableNames(): iterable
    {
        $sql = sprintf(
            <<<'SQL'
            SELECT n.nspname,
                   c.relname
            FROM pg_class c
                     INNER JOIN pg_namespace n
                                ON n.oid = c.relnamespace
            WHERE %s
              AND %s
            ORDER BY n.nspname,
                     c.relname
            SQL,
            $this->buildNamespaceNamePredicate('n.nspname'),
            $this->buildTablePredicate('c.relkind', 'c.relname'),
        );

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            [$schemaName, $tableName] = $row;
            assert($schemaName === null || (is_string($schemaName) && $schemaName !== ''));
            assert(is_string($tableName) && $tableName !== '');

            yield new TableMetadataRow($schemaName, $tableName, []);
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
     * @link https://www.postgresql.org/docs/current/catalog-pg-attrdef.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-attribute.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-class.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-namespace.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-type.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-collation.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-depend.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-description.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-inherits.html
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
            SELECT n.nspname,
                   c.relname,
                   a.attname,
                   t.typname,
                   format_type(a.atttypid, a.atttypmod),
                   bt.typname,
                   format_type(bt.oid, t.typtypmod),
                   a.attnotnull,
                   a.attidentity,
                   (%s),
                   dsc.description,
                   CASE
                       WHEN coll.collprovider = 'c'
                           THEN coll.collcollate
                       WHEN coll.collprovider = 'd'
                           THEN NULL
                       ELSE coll.collname
                       END
            FROM pg_attribute a
                     JOIN pg_class c
                          ON c.oid = a.attrelid
                     JOIN pg_namespace n
                          ON n.oid = c.relnamespace
                     JOIN pg_type t
                          ON t.oid = a.atttypid
                     LEFT JOIN pg_type bt
                               ON t.typtype = 'd'
                                   AND bt.oid = t.typbasetype
                     LEFT JOIN pg_collation coll
                               ON coll.oid = a.attcollation
                     LEFT JOIN pg_depend dep
                               ON dep.objid = c.oid
                                   AND dep.deptype = 'e'
                                   AND dep.classid = (SELECT oid FROM pg_class WHERE relname = 'pg_class')
                     LEFT JOIN pg_description dsc
                               ON dsc.objoid = c.oid AND dsc.objsubid = a.attnum
                     LEFT JOIN pg_inherits i
                               ON i.inhrelid = c.oid
                     LEFT JOIN pg_class p
                               ON i.inhparent = p.oid
                                   AND p.relkind = 'p'
            WHERE %s
              AND a.attnum > 0
              AND dep.refobjid IS NULL
              -- exclude partitions (tables that inherit from partitioned tables)
              AND p.oid IS NULL
            ORDER BY n.nspname,
                     c.relname,
                     a.attnum
            SQL,
            $this->platform->getDefaultColumnValueSQLSnippet(),
            $this->buildTableQueryPredicate('n', $schemaName, 'c', $tableName, $params),
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
            $namespaceName,
            $relationName,
            $attributeName,
            $typeName,
            $completeType,
            $domainTypeName,
            $domainCompleteType,
            $isNotNull,
            $identity,
            $defaultExpression,
            $description,
            $collationName,
        ] = $row;

        assert($namespaceName === null || (is_string($namespaceName) && $namespaceName !== ''));
        assert(is_string($relationName) && $relationName !== '');
        assert(is_string($attributeName) && $attributeName !== '');
        assert(is_string($typeName));
        assert(is_string($completeType));
        assert(is_string($domainTypeName) || $domainTypeName === null);
        assert(is_string($domainCompleteType) || $domainCompleteType === null);
        assert(is_string($identity) || $identity === null);
        assert(is_string($defaultExpression) || $defaultExpression === null);
        assert(is_string($description) || $description === null);
        assert($collationName === null || (is_string($collationName) && $collationName !== ''));

        $editor = Column::editor()
            ->setQuotedName($attributeName);

        if ($domainTypeName !== null && ! $this->platform->hasDoctrineTypeMappingFor($typeName)) {
            $typeName     = $domainTypeName;
            $completeType = $domainCompleteType;
        }

        assert(is_string($completeType));

        $editor->setTypeName(
            $this->platform->getDoctrineTypeMapping($typeName),
        );

        switch ($typeName) {
            case 'bpchar':
            case 'varchar':
                $parameters = $this->parseColumnTypeParameters($completeType);
                if (count($parameters) > 0) {
                    $editor->setLength($parameters[0]);
                }

                break;

            case 'double':
            case 'decimal':
            case 'money':
            case 'numeric':
                $parameters = $this->parseColumnTypeParameters($completeType);
                if (count($parameters) > 0) {
                    $editor->setPrecision($parameters[0]);
                }

                if (count($parameters) > 1) {
                    $editor->setScale($parameters[1]);
                }

                break;
        }

        if ($typeName === 'bpchar') {
            $editor->setFixed(true);
        }

        $editor
            ->setNotNull((bool) $isNotNull)
            ->setDefaultValue($this->parseDefaultExpression($defaultExpression))
            ->setAutoincrement($identity === 'd');

        if ($description !== null) {
            $editor->setComment($description);
        }

        $editor->setCollation($collationName);

        return new TableColumnMetadataRow($namespaceName, $relationName, $editor->create());
    }

    /**
     * Parses the parameters between parenthesis in the data type.
     *
     * @return list<int>
     */
    private function parseColumnTypeParameters(string $type): array
    {
        if (preg_match('/\((\d+)(?:,(\d+))?\)/', $type, $matches) !== 1) {
            return [];
        }

        $parameters = [(int) $matches[1]];

        if (isset($matches[2])) {
            $parameters[] = (int) $matches[2];
        }

        return $parameters;
    }

    /**
     * Parses a default value expression as given by PostgreSQL
     */
    private function parseDefaultExpression(?string $expression): mixed
    {
        if ($expression === null || str_starts_with($expression, 'NULL::')) {
            return null;
        }

        if ($expression === 'true') {
            return true;
        }

        if ($expression === 'false') {
            return false;
        }

        if (preg_match("/^'(.*)'::/s", $expression, $matches) === 1) {
            return str_replace("''", "'", $matches[1]);
        }

        return $expression;
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
     * @link https://www.postgresql.org/docs/current/catalog-pg-index.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-class.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-namespace.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-class.html
     *
     * @return iterable<IndexColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getIndexColumns(?string $schemaName, ?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT n.nspname,
                   c.relname,
                   ic.relname,
                   i.indisunique,
                   pg_get_expr(indpred, indrelid),
                   attname
            FROM pg_index i
                     JOIN pg_class AS c ON c.oid = i.indrelid
                     JOIN pg_namespace n ON n.oid = c.relnamespace
                     JOIN pg_class AS ic ON ic.oid = i.indexrelid
                     JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS keys(attnum, ord)
                          ON TRUE
                     JOIN pg_attribute a
                          ON a.attrelid = c.oid
                              AND a.attnum = keys.attnum
            WHERE %s
              AND i.indisprimary = false
            ORDER BY n.nspname,
                     c.relname,
                     ic.relname,
                     keys.ord
            SQL,
            $this->buildTableQueryPredicate('n', $schemaName, 'c', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            [
                $schemaName,
                $tableName,
                $indexName,
                $isUnique,
                $predicate,
                $columnName,
            ] = $row;

            assert($schemaName === null || (is_string($schemaName) && $schemaName !== ''));
            assert(is_string($tableName) && $tableName !== '');
            assert(is_string($indexName) && $indexName !== '');
            assert($predicate === null || (is_string($predicate) && $predicate !== ''));
            assert(is_string($columnName) && $columnName !== '');

            yield new IndexColumnMetadataRow(
                schemaName: $schemaName,
                tableName: $tableName,
                indexName: $indexName,
                type: $isUnique ? IndexType::UNIQUE : IndexType::REGULAR,
                isClustered: false,
                predicate: $predicate,
                columnName: $columnName,
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
     * @link https://www.postgresql.org/docs/current/catalog-pg-namespace.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-class.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-constraint.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-index.html
     *
     * @return iterable<PrimaryKeyConstraintColumnRow>
     *
     * @throws Exception
     */
    private function getPrimaryKeyConstraintColumns(?string $schemaName, ?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT n.nspname,
                   c.relname,
                   ct.conname,
                   a.attname
            FROM pg_namespace n
                     INNER JOIN pg_class c
                                ON c.relnamespace = n.oid
                     INNER JOIN pg_constraint ct
                                ON ct.conrelid = c.oid
                     INNER JOIN pg_index i
                                ON i.indrelid = c.oid
                                    AND i.indexrelid = ct.conindid
                     INNER JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS keys(attnum, ord)
                                ON true
                     INNER JOIN
                 pg_attribute a
                 ON a.attrelid = c.oid
                     AND a.attnum = keys.attnum
            WHERE %s
              AND ct.contype = 'p'
            ORDER BY n.nspname,
                     c.relname,
                     ct.conname,
                     keys.ord
            SQL,
            $this->buildTableQueryPredicate('n', $schemaName, 'c', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            [
                $schemaName,
                $tableName,
                $constraintName,
                $columnName,
            ] = $row;

            assert($schemaName === null || (is_string($schemaName) && $schemaName !== ''));
            assert(is_string($tableName) && $tableName !== '');
            assert($constraintName === null || (is_string($constraintName) && $constraintName !== ''));
            assert(is_string($columnName) && $columnName !== '');

            yield new PrimaryKeyConstraintColumnRow(
                schemaName: $schemaName,
                tableName: $tableName,
                constraintName: $constraintName,
                isClustered: true,
                columnName: $columnName,
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
     * @link https://www.postgresql.org/docs/current/catalog-pg-constraint.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-class.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-namespace.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-attribute.html
     *
     * @return iterable<ForeignKeyConstraintColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getForeignKeyConstraintColumns(?string $schemaName, ?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT pkn.nspname,
                   pkc.relname,
                   r.conname,
                   fkn.nspname,
                   fkc.relname,
                   r.confupdtype,
                   r.confdeltype,
                   r.condeferrable,
                   r.condeferred,
                   pka.attname,
                   fka.attname
            FROM pg_constraint r
                     JOIN pg_class fkc
                          ON fkc.oid = r.confrelid
                     JOIN pg_namespace fkn
                          ON fkn.oid = fkc.relnamespace
                     JOIN unnest(r.confkey) WITH ORDINALITY AS fk_attnum(attnum, ord)
                          ON TRUE
                     JOIN pg_attribute fka
                          ON fka.attrelid = fkc.oid
                              AND fka.attnum = fk_attnum.attnum
                     JOIN pg_class pkc
                          ON pkc.oid = r.conrelid
                     JOIN pg_namespace pkn
                          ON pkn.oid = pkc.relnamespace
                     JOIN unnest(r.conkey) WITH ORDINALITY AS pk_attnum(attnum, ord)
                          ON pk_attnum.ord = fk_attnum.ord
                     JOIN pg_attribute pka
                          ON pka.attrelid = pkc.oid
                              AND pka.attnum = pk_attnum.attnum
            WHERE %s
              AND r.contype = 'f'
            ORDER BY pkn.nspname,
                     pkc.relname,
                     r.conname
        SQL,
            $this->buildTableQueryPredicate('pkn', $schemaName, 'pkc', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            [
                $referencingSchemaName,
                $referencingTableName,
                $name,
                $referencedSchemaName,
                $referencedTableName,
                $onUpdateKey,
                $onDeleteKey,
                $deferrable,
                $deferred,
                $referencingColumnName,
                $referencedColumnName,
            ] = $row;

            assert($referencingSchemaName === null || (is_string($referencingSchemaName) && $referencingSchemaName !== ''));
            assert(is_string($referencingTableName) && $referencingTableName !== '');
            assert($name === null || (is_string($name) && $name !== ''));
            assert($referencedSchemaName === null || (is_string($referencedSchemaName) && $referencedSchemaName !== ''));
            assert(is_string($referencedTableName) && $referencedTableName !== '');
            assert(is_string($onUpdateKey) && isset(self::REFERENTIAL_ACTIONS[$onUpdateKey]));
            assert(is_string($onDeleteKey) && isset(self::REFERENTIAL_ACTIONS[$onDeleteKey]));
            assert(is_string($referencingColumnName) && $referencingColumnName !== '');
            assert(is_string($referencedColumnName) && $referencedColumnName !== '');

            yield new ForeignKeyConstraintColumnMetadataRow(
                $referencingSchemaName,
                $referencingTableName,
                null,
                $name,
                $referencedSchemaName,
                $referencedTableName,
                MatchType::SIMPLE,
                self::REFERENTIAL_ACTIONS[$onUpdateKey],
                self::REFERENTIAL_ACTIONS[$onDeleteKey],
                (bool) $deferrable,
                (bool) $deferred,
                $referencingColumnName,
                $referencedColumnName,
            );
        }
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
     * @link https://www.postgresql.org/docs/current/catalog-pg-class.html
     * @link https://www.postgresql.org/docs/current/catalog-pg-namespace.html
     *
     * @return iterable<TableMetadataRow>
     *
     * @throws Exception
     */
    private function getTableOptions(?string $schemaName, ?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT n.nspname,
                   c.relname,
                   CASE c.relpersistence WHEN 'u' THEN true ELSE false END,
                   obj_description(c.oid, 'pg_class')
            FROM pg_class c
                     INNER JOIN pg_namespace n
                                ON n.oid = c.relnamespace
            WHERE %s
            SQL,
            $this->buildTableQueryPredicate('n', $schemaName, 'c', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            [$schemaName, $tableName, $unlogged, $comment] = $row;
            assert($schemaName === null || (is_string($schemaName) && $schemaName !== ''));
            assert(is_string($tableName) && $tableName !== '');
            assert(is_string($comment) || $comment === null);

            yield new TableMetadataRow($schemaName, $tableName, [
                'unlogged' => (bool) $unlogged,
                'comment' => $comment,
            ]);
        }
    }

    /**
     * @param list<int|string> $params
     *
     * @return non-empty-string
     */
    private function buildTableQueryPredicate(
        string $namespaceRelation,
        ?string $schemaName,
        string $tableRelation,
        ?string $tableName,
        array &$params,
    ): string {
        $conditions = [];

        assert(($tableName === null) === ($schemaName === null));

        if ($tableName !== null && $schemaName !== null) {
            $conditions[] = sprintf('%s.nspname = ?', $namespaceRelation);
            $params[]     = $schemaName;

            $conditions[] = sprintf('%s.relname = ?', $tableRelation);
            $params[]     = $tableName;
        }

        $conditions[] = $this->buildNamespaceNamePredicate($namespaceRelation . '.nspname');
        $conditions[] = $this->buildTablePredicate($tableRelation . '.relkind', $tableRelation . '.relname');

        return implode(' AND ', $conditions);
    }

    /**
     * {@inheritDoc}
     *
     * @link https://www.postgresql.org/docs/current/catalog-pg-views.html
     */
    public function getAllViews(): iterable
    {
        $sql = sprintf(
            <<<'SQL'
            SELECT schemaname,
                   viewname,
                   definition
            FROM pg_views
            WHERE %s
            ORDER BY schemaname,
                     viewname
            SQL,
            $this->buildNamespaceNamePredicate('schemaname'),
        );

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            [$schemaName, $viewName, $definition] = $row;
            assert($schemaName === null || (is_string($schemaName) && $schemaName !== ''));
            assert(is_string($viewName) && $viewName !== '');
            assert(is_string($definition));

            yield new ViewMetadataRow($schemaName, $viewName, $definition);
        }
    }

    /** {@inheritDoc} */
    public function getAllSequences(): iterable
    {
        // Using information_schema here instead of pg_sequences since the latter also includes sequences that are owned
        // by serial columns. We want to include only the user-defined ones.
        $sql = sprintf(
            <<<'SQL'
            SELECT sequence_schema,
                   sequence_name,
                   increment,
                   minimum_value
            FROM information_schema.sequences
            WHERE sequence_catalog = CURRENT_DATABASE()
              AND %s
            ORDER BY sequence_schema,
                     sequence_name
            SQL,
            $this->buildNamespaceNamePredicate('sequence_schema'),
        );

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            [
                $schemaName,
                $sequenceName,
                $increment,
                $minimumValue,
            ] = $row;

            assert($schemaName === null || (is_string($schemaName) && $schemaName !== ''));
            assert(is_string($sequenceName) && $sequenceName !== '');
            assert(is_numeric($increment));
            assert(is_numeric($minimumValue));

            yield new SequenceMetadataRow(
                schemaName: $schemaName,
                sequenceName: $sequenceName,
                allocationSize: (int) $increment,
                initialValue: (int) $minimumValue,
                cacheSize: null,
            );
        }
    }

    private function buildNamespaceNamePredicate(string $columnName): string
    {
        return sprintf("%1\$s NOT LIKE 'pg\_%%' AND %1\$s != 'information_schema'", $columnName);
    }

    /**
     * * @link https://www.postgresql.org/docs/current/catalog-pg-class.html
     */
    private function buildTablePredicate(string $kindColumnName, string $nameColumnName): string
    {
        return sprintf(
            // r = ordinary table, p = partitioned table,
            "%s IN ('r', 'p') AND %s NOT IN ('geometry_columns', 'spatial_ref_sys')",
            $kindColumnName,
            $nameColumnName,
        );
    }
}
