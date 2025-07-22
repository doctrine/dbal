<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Type;

use function array_change_key_case;
use function array_map;
use function assert;
use function count;
use function explode;
use function implode;
use function is_string;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;

use const CASE_LOWER;

/**
 * PostgreSQL Schema Manager.
 *
 * @extends AbstractSchemaManager<PostgreSQLPlatform>
 */
class PostgreSQLSchemaManager extends AbstractSchemaManager
{
    /**
     * {@inheritDoc}
     */
    public function listSchemaNames(): array
    {
        return $this->connection->fetchFirstColumn(
            <<<'SQL'
SELECT schema_name
FROM   information_schema.schemata
WHERE  schema_name NOT LIKE 'pg\_%'
AND    schema_name != 'information_schema'
SQL,
        );
    }

    /**
     * Returns the name of the current schema.
     *
     * @deprecated Use {@link getCurrentSchemaName()} instead
     *
     * @throws Exception
     */
    protected function getCurrentSchema(): ?string
    {
        return $this->getCurrentSchemaName();
    }

    /**
     * Determines the name of the current schema.
     *
     * @deprecated Use {@link determineCurrentSchemaName()} instead
     *
     * @return non-empty-string
     *
     * @throws Exception
     */
    protected function determineCurrentSchema(): string
    {
        $currentSchema = $this->connection->fetchOne('SELECT current_schema()');
        assert(is_string($currentSchema));
        assert(strlen($currentSchema) > 0);

        return $currentSchema;
    }

    protected function determineCurrentSchemaName(): ?string
    {
        return $this->determineCurrentSchema();
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableForeignKeyDefinition(array $tableForeignKey): ForeignKeyConstraint
    {
        $onUpdate = null;
        $onDelete = null;

        if (
            preg_match(
                '(ON UPDATE ([a-zA-Z0-9]+( (NULL|ACTION|DEFAULT))?))',
                $tableForeignKey['condef'],
                $match,
            ) === 1
        ) {
            $onUpdate = $match[1];
        }

        if (
            preg_match(
                '(ON DELETE ([a-zA-Z0-9]+( (NULL|ACTION|DEFAULT))?))',
                $tableForeignKey['condef'],
                $match,
            ) === 1
        ) {
            $onDelete = $match[1];
        }

        $result = preg_match('/FOREIGN KEY \((.+)\) REFERENCES (.+)\((.+)\)/', $tableForeignKey['condef'], $values);
        assert($result === 1);

        // PostgreSQL returns identifiers that are keywords with quotes, we need them later, don't get
        // the idea to trim them here.
        $localColumns   = array_map('trim', explode(',', $values[1]));
        $foreignColumns = array_map('trim', explode(',', $values[3]));
        $foreignTable   = $values[2];

        return new ForeignKeyConstraint(
            $localColumns,
            $foreignTable,
            $foreignColumns,
            $tableForeignKey['conname'],
            [
                'onUpdate' => $onUpdate,
                'onDelete' => $onDelete,
                'deferrable' => (bool) $tableForeignKey['condeferrable'],
                'deferred' => (bool) $tableForeignKey['condeferred'],
            ],
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        return new View($view['schemaname'] . '.' . $view['viewname'], $view['definition']);
    }

    /**
     * @deprecated Use the schema name and the unqualified table name separately instead.
     *
     * {@inheritDoc}
     */
    protected function _getPortableTableDefinition(array $table): string
    {
        // @phpstan-ignore missingType.checkedException
        $currentSchema = $this->getCurrentSchema();

        if ($table['schema_name'] === $currentSchema) {
            return $table['table_name'];
        }

        return $table['schema_name'] . '.' . $table['table_name'];
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableIndexesList(array $rows, string $tableName): array
    {
        return parent::_getPortableTableIndexesList(array_map(
            /** @param array<string, mixed> $row */
            static function (array $row): array {
                return [
                    'key_name' => $row['relname'],
                    'non_unique' => ! $row['indisunique'],
                    'primary' => (bool) $row['indisprimary'],
                    'where' => $row['where'],
                    'column_name' => $row['attname'],
                ];
            },
            $rows,
        ), $tableName);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableDatabaseDefinition(array $database): string
    {
        return $database['datname'];
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableSequenceDefinition(array $sequence): Sequence
    {
        if ($sequence['schemaname'] !== 'public') {
            $sequenceName = $sequence['schemaname'] . '.' . $sequence['relname'];
        } else {
            $sequenceName = $sequence['relname'];
        }

        return new Sequence($sequenceName, (int) $sequence['increment_by'], (int) $sequence['min_value']);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        $length    = null;
        $precision = null;
        $scale     = 0;
        $fixed     = false;
        $jsonb     = false;

        $dbType = $tableColumn['type'];

        if (
            $tableColumn['domain_type'] !== null
                && ! $this->platform->hasDoctrineTypeMappingFor($dbType)
        ) {
            $dbType       = $tableColumn['domain_type'];
            $completeType = $tableColumn['domain_complete_type'];
        } else {
            $completeType = $tableColumn['complete_type'];
        }

        $type = $this->platform->getDoctrineTypeMapping($dbType);

        switch ($dbType) {
            case 'bpchar':
            case 'varchar':
                $parameters = $this->parseColumnTypeParameters($completeType);
                if (count($parameters) > 0) {
                    $length = $parameters[0];
                }

                break;

            case 'double':
            case 'decimal':
            case 'money':
            case 'numeric':
                $parameters = $this->parseColumnTypeParameters($completeType);
                if (count($parameters) > 0) {
                    $precision = $parameters[0];
                }

                if (count($parameters) > 1) {
                    $scale = $parameters[1];
                }

                break;
        }

        if ($dbType === 'bpchar') {
            $fixed = true;
        } elseif ($dbType === 'jsonb') {
            $jsonb = true;
        }

        $options = [
            'length'        => $length,
            'notnull'       => (bool) $tableColumn['isnotnull'],
            'default'       => $this->parseDefaultExpression($tableColumn['default']),
            'precision'     => $precision,
            'scale'         => $scale,
            'fixed'         => $fixed,
            'autoincrement' => $tableColumn['attidentity'] === 'd',
        ];

        if ($tableColumn['comment'] !== null) {
            $options['comment'] = $tableColumn['comment'];
        }

        $column = new Column($tableColumn['field'], Type::getType($type), $options);

        if (! empty($tableColumn['collation'])) {
            $column->setPlatformOption('collation', $tableColumn['collation']);
        }

        if ($column->getType() instanceof JsonType) {
            $column->setPlatformOption('jsonb', $jsonb);
        }

        return $column;
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

    protected function selectTableNames(string $databaseName): Result
    {
        $sql = <<<'SQL'
SELECT quote_ident(table_name) AS table_name,
       table_schema AS schema_name
FROM information_schema.tables
WHERE table_catalog = ?
  AND table_schema NOT LIKE 'pg\_%'
  AND table_schema != 'information_schema'
  AND table_name != 'geometry_columns'
  AND table_name != 'spatial_ref_sys'
  AND table_type = 'BASE TABLE'
ORDER BY
  quote_ident(table_name)
SQL;

        return $this->connection->executeQuery($sql, [$databaseName]);
    }

    protected function selectTableColumns(string $databaseName, ?string $tableName = null): Result
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT quote_ident(n.nspname)               AS schema_name,
                   quote_ident(c.relname)               AS table_name,
                   quote_ident(a.attname)               AS field,
                   t.typname                            AS type,
                   format_type(a.atttypid, a.atttypmod) AS complete_type,
                   bt.typname                           AS domain_type,
                   format_type(bt.oid, t.typtypmod)     AS domain_complete_type,
                   a.attnotnull                         AS isnotnull,
                   a.attidentity,
                   (%s)                                 AS "default",
                   dsc.description                      AS comment,
                   CASE
                       WHEN coll.collprovider = 'c'
                           THEN coll.collcollate
                       WHEN coll.collprovider = 'd'
                           THEN NULL
                       ELSE coll.collname
                       END                              AS collation
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
              -- 'r' for regular tables - 'p' for partitioned tables
              AND c.relkind IN ('r', 'p')
              AND a.attnum > 0
              AND dep.refobjid IS NULL
              -- exclude partitions (tables that inherit from partitioned tables)
              AND p.oid IS NULL
            ORDER BY n.nspname,
                c.relname,
                a.attnum
            SQL,
            $this->platform->getDefaultColumnValueSQLSnippet(),
            implode(' AND ', $this->buildQueryConditions($tableName, $params)),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectIndexColumns(string $databaseName, ?string $tableName = null): Result
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT
                   quote_ident(n.nspname) AS schema_name,
                   quote_ident(c.relname) AS table_name,
                   quote_ident(ic.relname) AS relname,
                   i.indisunique,
                   i.indisprimary,
                   i.indkey,
                   i.indrelid,
                   pg_get_expr(indpred, indrelid) AS "where",
                   quote_ident(attname) AS attname
              FROM pg_index i
                   JOIN pg_class AS c ON c.oid = i.indrelid
                   JOIN pg_namespace n ON n.oid = c.relnamespace
                   JOIN pg_class AS ic ON ic.oid = i.indexrelid
                   JOIN LATERAL UNNEST(i.indkey) WITH ORDINALITY AS keys(attnum, ord)
                        ON TRUE
                   JOIN pg_attribute a
                        ON a.attrelid = c.oid
                            AND a.attnum = keys.attnum
             WHERE %s
             ORDER BY 1, 2, keys.ord;
            SQL,
            implode(' AND ', $this->buildQueryConditions($tableName, $params)),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectForeignKeyColumns(string $databaseName, ?string $tableName = null): Result
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
           SELECT
                  quote_ident(tn.nspname) AS schema_name,
                  quote_ident(tc.relname) AS table_name,
                  quote_ident(r.conname) as conname,
                  pg_get_constraintdef(r.oid, true) as condef,
                  r.condeferrable,
                  r.condeferred
                  FROM pg_constraint r
                      JOIN pg_class AS tc ON tc.oid = r.conrelid
                      JOIN pg_namespace tn ON tn.oid = tc.relnamespace
                  WHERE r.conrelid IN
                  (
                      SELECT c.oid
                      FROM pg_class c
                        JOIN pg_namespace n
                            ON n.oid = c.relnamespace
                        WHERE %s)
                  AND r.contype = 'f'
                  ORDER BY 1, 2
        SQL,
            implode(' AND ', $this->buildQueryConditions($tableName, $params)),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchTableOptionsByTable(string $databaseName, ?string $tableName = null): array
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT quote_ident(n.nspname) AS schema_name,
                   quote_ident(c.relname) AS table_name,
                   CASE c.relpersistence WHEN 'u' THEN true ELSE false END as unlogged,
                   obj_description(c.oid, 'pg_class') AS comment
            FROM pg_class c
                 INNER JOIN pg_namespace n
                     ON n.oid = c.relnamespace
            WHERE
                  c.relkind = 'r'
              AND %s
            SQL,
            implode(' AND ', $this->buildQueryConditions($tableName, $params)),
        );

        $tableOptions = [];
        foreach ($this->connection->iterateAssociative($sql, $params) as $row) {
            $tableOptions[$this->_getPortableTableDefinition($row)] = $row;
        }

        return $tableOptions;
    }

    /**
     * @param list<int|string> $params
     *
     * @return non-empty-list<string>
     */
    private function buildQueryConditions(?string $tableName, array &$params): array
    {
        $conditions = [];

        if ($tableName !== null) {
            if (str_contains($tableName, '.')) {
                [$schemaName, $tableName] = explode('.', $tableName);

                $conditions[] = 'n.nspname = ?';
                $params[]     = $schemaName;
            } else {
                $conditions[] = 'n.nspname = ANY(current_schemas(false))';
            }

            $conditions[] = 'c.relname = ?';
            $params[]     = $tableName;
        }

        $conditions[] = "n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')";

        return $conditions;
    }
}
