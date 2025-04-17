<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Type;

use function array_change_key_case;
use function array_key_exists;
use function array_map;
use function assert;
use function implode;
use function in_array;
use function is_string;
use function preg_match;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;

use const CASE_LOWER;

/**
 * PostgreSQL Schema Manager.
 *
 * @extends AbstractSchemaManager<PostgreSQLPlatform>
 */
class PostgreSQLSchemaManager extends AbstractSchemaManager
{
    private const REFERENTIAL_ACTIONS = [
        'a' => ReferentialAction::NO_ACTION,
        'c' => ReferentialAction::CASCADE,
        'd' => ReferentialAction::SET_DEFAULT,
        'n' => ReferentialAction::SET_NULL,
        'r' => ReferentialAction::RESTRICT,
    ];

    /**
     * The maximum number of columns that can be included in an index.
     */
    private ?int $maxIndexKeys = null;

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

    protected function determineCurrentSchemaName(): ?string
    {
        $currentSchema = $this->connection->fetchOne('SELECT current_schema()');
        assert($currentSchema !== false);
        assert(strlen($currentSchema) > 0);

        return $currentSchema;
    }

    /**
     * Returns the maximum number of columns that can be included in an index.
     *
     * @link https://www.postgresql.org/docs/current/runtime-config-preset.html#GUC-MAX-INDEX-KEYS
     *
     * @throws Exception
     */
    private function getMaxIndexKeys(): int
    {
        return $this->maxIndexKeys ??= (int) $this->connection->fetchOne(
            <<<'SQL'
            SELECT setting FROM pg_settings WHERE name = 'max_index_keys'
            SQL,
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        // @phpstan-ignore missingType.checkedException
        if ($view['schemaname'] === $this->getCurrentSchemaName()) {
            $name = $view['viewname'];
        } else {
            $name = $view['schemaname'] . '.' . $view['viewname'];
        }

        return new View($name, $view['definition']);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableForeignKeysList(array $tableForeignKeys): array
    {
        $list = [];
        foreach ($tableForeignKeys as $value) {
            $value = array_change_key_case($value);
            if (! isset($list[$value['conname']])) {
                // @phpstan-ignore missingType.checkedException
                if ($value['fk_nspname'] === $this->getCurrentSchemaName()) {
                    $value['fk_nspname'] = null;
                }

                $list[$value['conname']] = [
                    'name' => $value['conname'],
                    'local' => [],
                    'foreign' => [],
                    'foreignTable' => $value['fk_relname'],
                    'foreignSchema' => $value['fk_nspname'],
                    'onUpdate' => self::REFERENTIAL_ACTIONS[$value['confupdtype']]->value,
                    'onDelete' => self::REFERENTIAL_ACTIONS[$value['confdeltype']]->value,
                    'deferrable' => $value['condeferrable'],
                    'deferred' => $value['condeferred'],
                ];
            }

            $list[$value['conname']]['local'][]   = $value['pk_attname'];
            $list[$value['conname']]['foreign'][] = $value['fk_attname'];
        }

        return parent::_getPortableTableForeignKeysList($list);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableIndexesList(array $rows): array
    {
        return parent::_getPortableTableIndexesList(array_map(
            /** @param array<string, mixed> $row */
            static function (array $row): array {
                return [
                    'key_name' => $row['relname'],
                    'column_name' => $row['attname'],
                    'type' => $row['indisunique'] ? IndexType::UNIQUE : IndexType::REGULAR,
                    'predicate' => $row['predicate'],
                ];
            },
            $rows,
        ));
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
        // @phpstan-ignore missingType.checkedException
        if ($sequence['schemaname'] !== $this->getCurrentSchemaName()) {
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

        $length = null;

        if (
            in_array(strtolower($tableColumn['typname']), ['varchar', 'bpchar'], true)
            && preg_match('/\((\d*)\)/', $tableColumn['complete_type'], $matches) === 1
        ) {
            $length = (int) $matches[1];
        }

        $autoincrement = $tableColumn['attidentity'] === 'd';

        $matches = [];

        assert(array_key_exists('default', $tableColumn));
        assert(array_key_exists('complete_type', $tableColumn));

        if ($tableColumn['default'] !== null) {
            if (preg_match("/^['(](.*)[')]::/", $tableColumn['default'], $matches) === 1) {
                $tableColumn['default'] = $matches[1];
            } elseif (preg_match('/^NULL::/', $tableColumn['default']) === 1) {
                $tableColumn['default'] = null;
            }
        }

        if ($length === -1 && isset($tableColumn['atttypmod'])) {
            $length = $tableColumn['atttypmod'] - 4;
        }

        if ((int) $length <= 0) {
            $length = null;
        }

        $fixed = false;

        if (! isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        $precision = null;
        $scale     = 0;
        $jsonb     = null;

        $dbType = strtolower($tableColumn['typname']);
        if (
            $tableColumn['domain_type'] !== null
            && $tableColumn['domain_type'] !== ''
            && ! $this->platform->hasDoctrineTypeMappingFor($tableColumn['typname'])
        ) {
            $dbType                       = strtolower($tableColumn['domain_type']);
            $tableColumn['complete_type'] = $tableColumn['domain_complete_type'];
        }

        $type = $this->platform->getDoctrineTypeMapping($dbType);

        switch ($dbType) {
            case 'smallint':
            case 'int2':
            case 'int':
            case 'int4':
            case 'integer':
            case 'bigint':
            case 'int8':
                $length = null;
                break;

            case 'bool':
            case 'boolean':
                if ($tableColumn['default'] === 'true') {
                    $tableColumn['default'] = true;
                }

                if ($tableColumn['default'] === 'false') {
                    $tableColumn['default'] = false;
                }

                $length = null;
                break;

            case 'json':
            case 'text':
            case '_varchar':
            case 'varchar':
                $tableColumn['default'] = $this->parseDefaultExpression($tableColumn['default']);
                break;

            case 'char':
            case 'bpchar':
                $fixed = true;
                break;

            case 'float':
            case 'float4':
            case 'float8':
            case 'double':
            case 'double precision':
            case 'real':
            case 'decimal':
            case 'money':
            case 'numeric':
                if (
                    preg_match(
                        '([A-Za-z]+\(([0-9]+),([0-9]+)\))',
                        $tableColumn['complete_type'],
                        $match,
                    ) === 1
                ) {
                    $precision = (int) $match[1];
                    $scale     = (int) $match[2];
                    $length    = null;
                }

                break;

            case 'year':
                $length = null;
                break;

            // PostgreSQL 9.4+ only
            case 'jsonb':
                $jsonb = true;
                break;
        }

        if (
            is_string($tableColumn['default']) && preg_match(
                "('([^']+)'::)",
                $tableColumn['default'],
                $match,
            ) === 1
        ) {
            $tableColumn['default'] = $match[1];
        }

        $options = [
            'length'        => $length,
            'notnull'       => (bool) $tableColumn['isnotnull'],
            'default'       => $tableColumn['default'],
            'precision'     => $precision,
            'scale'         => $scale,
            'fixed'         => $fixed,
            'autoincrement' => $autoincrement,
        ];

        if ($tableColumn['comment'] !== null) {
            $options['comment'] = $tableColumn['comment'];
        }

        $column = new Column($tableColumn['attname'], Type::getType($type), $options);

        if (! empty($tableColumn['collation'])) {
            $column->setPlatformOption('collation', $tableColumn['collation']);
        }

        if ($column->getType() instanceof JsonType) {
            $column->setPlatformOption('jsonb', $jsonb);
        }

        return $column;
    }

    /**
     * Parses a default value expression as given by PostgreSQL
     */
    private function parseDefaultExpression(?string $default): ?string
    {
        if ($default === null) {
            return $default;
        }

        return str_replace("''", "'", $default);
    }

    protected function selectTableNames(string $databaseName): Result
    {
        $sql = sprintf(
            <<<'SQL'
                SELECT table_schema AS %s,
                       table_name AS %s
                FROM information_schema.tables
                WHERE table_catalog = ?
                  AND table_schema NOT LIKE 'pg\_%%'
                  AND table_schema != 'information_schema'
                  AND table_name != 'geometry_columns'
                  AND table_name != 'spatial_ref_sys'
                  AND table_type = 'BASE TABLE'
                SQL,
            $this->platform->quoteSingleIdentifier(self::SCHEMA_NAME_COLUMN),
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
        );

        return $this->connection->executeQuery($sql, [$databaseName]);
    }

    protected function selectTableColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT
            n.nspname AS %s,
            c.relname AS %s,
            a.attnum,
            quote_ident(a.attname) AS attname,
            t.typname,
            format_type(a.atttypid, a.atttypmod) AS complete_type,
            (
                SELECT CASE
                    WHEN collprovider = 'c' THEN tc.collcollate
                    WHEN collprovider = 'd' THEN null
                    ELSE tc.collname
                END
                FROM pg_catalog.pg_collation tc WHERE tc.oid = a.attcollation
            ) AS collation,
            (SELECT t1.typname FROM pg_catalog.pg_type t1 WHERE t1.oid = t.typbasetype) AS domain_type,
            (SELECT format_type(t2.typbasetype, t2.typtypmod) FROM
              pg_catalog.pg_type t2 WHERE t2.typtype = 'd' AND t2.oid = a.atttypid) AS domain_complete_type,
            a.attnotnull AS isnotnull,
            a.attidentity,
            (SELECT 't'
             FROM pg_index
             WHERE c.oid = pg_index.indrelid
                AND pg_index.indkey[0] = a.attnum
                AND pg_index.indisprimary = 't'
            ) AS pri,
            (SELECT
                CASE
                    WHEN a.attgenerated = 's' THEN NULL
                    ELSE pg_get_expr(adbin, adrelid)
                END
             FROM pg_attrdef
             WHERE c.oid = pg_attrdef.adrelid
                AND pg_attrdef.adnum=a.attnum) AS default,
            (SELECT pg_description.description
                FROM pg_description WHERE pg_description.objoid = c.oid AND a.attnum = pg_description.objsubid
            ) AS comment
            FROM pg_attribute a
                INNER JOIN pg_class c
                    ON c.oid = a.attrelid
                INNER JOIN pg_type t
                    ON t.oid = a.atttypid
                INNER JOIN pg_namespace n
                    ON n.oid = c.relnamespace
                LEFT JOIN pg_depend d
                    ON d.objid = c.oid
                        AND d.deptype = 'e'
                        AND d.classid = (SELECT oid FROM pg_class WHERE relname = 'pg_class')
            WHERE a.attnum > 0
                AND d.refobjid IS NULL
                -- 'r' for regular tables - 'p' for partitioned tables
                AND c.relkind IN('r', 'p')
                -- exclude partitions (tables that inherit from partitioned tables)
                AND NOT EXISTS (
                    SELECT 1
                    FROM pg_inherits
                    INNER JOIN pg_class parent on pg_inherits.inhparent = parent.oid
                        AND parent.relkind = 'p'
                    WHERE inhrelid = c.oid
                )
                AND %s
            ORDER BY a.attnum
            SQL,
            $this->platform->quoteSingleIdentifier(self::SCHEMA_NAME_COLUMN),
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $this->buildQueryConditions($tableName, $params)),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectIndexColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT
                   n.nspname AS %s,
                   c.relname AS %s,
                   ic.relname,
                   i.indisunique,
                   i.indkey,
                   i.indrelid,
                   pg_get_expr(indpred, indrelid) AS predicate,
                   attname
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
               AND i.indisprimary = false
             ORDER BY 1, 2, keys.ord;
            SQL,
            $this->platform->quoteSingleIdentifier(self::SCHEMA_NAME_COLUMN),
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $this->buildQueryConditions($tableName, $params)),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /** {@inheritDoc} */
    protected function fetchPrimaryKeyConstraintColumns(
        string $databaseName,
        ?OptionallyQualifiedName $tableName = null,
    ): array {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT n.nspname AS %s,
                   c.relname AS %s,
                   ct.conname AS constraint_name,
                   a.attname AS column_name
            FROM 
                pg_namespace n
            INNER JOIN
                pg_class c
                    ON c.relnamespace = n.oid
            INNER JOIN
                pg_constraint ct
                    ON ct.conrelid = c.oid
            INNER JOIN
                pg_index i
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
            ORDER BY 
                1, 2, keys.ord;
            SQL,
            $this->platform->quoteSingleIdentifier(self::SCHEMA_NAME_COLUMN),
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $this->buildQueryConditions($tableName, $params)),
        );

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    protected function selectForeignKeyColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $params = [$this->getMaxIndexKeys()];

        $sql = sprintf(
            <<<'SQL'
        SELECT pkn.nspname AS %s,
               pkc.relname AS %s,
               r.conname,
               pka.attname AS pk_attname,
               fkn.nspname AS fk_nspname,
               fkc.relname AS fk_relname,
               fka.attname AS fk_attname,
               r.confupdtype,
               r.confdeltype,
               r.condeferrable,
               r.condeferred
        FROM pg_constraint r
                 JOIN
             pg_class fkc ON fkc.oid = r.confrelid
                 JOIN
             pg_namespace fkn ON fkn.oid = fkc.relnamespace
                 JOIN
             pg_attribute fka ON fkc.oid = fka.attrelid
                 JOIN
             pg_class pkc ON pkc.oid = r.conrelid
                 JOIN
             pg_namespace pkn ON pkn.oid = pkc.relnamespace
                 JOIN
             pg_attribute pka ON pkc.oid = pka.attrelid
                 JOIN
             generate_series(1, ?) pos(n)
             ON fka.attnum = r.confkey[pos.n]
                 AND pka.attnum = r.conkey[pos.n]
                          WHERE r.conrelid IN
                          (
                              SELECT c.oid
                              FROM pg_class c
                                JOIN pg_namespace n
                                    ON n.oid = c.relnamespace
                            WHERE %s
                          ) AND r.contype = 'f'
        SQL,
            $this->platform->quoteSingleIdentifier(self::SCHEMA_NAME_COLUMN),
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $this->buildQueryConditions($tableName, $params)),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchTableOptionsByTable(string $databaseName, ?OptionallyQualifiedName $tableName = null): array
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT n.nspname AS %s,
                   c.relname AS %s,
                   CASE c.relpersistence WHEN 'u' THEN true ELSE false END as unlogged,
                   obj_description(c.oid, 'pg_class') AS comment
            FROM pg_class c
                 INNER JOIN pg_namespace n
                     ON n.oid = c.relnamespace
            WHERE
                c.relkind = 'r'
              AND %s
            SQL,
            $this->platform->quoteSingleIdentifier(self::SCHEMA_NAME_COLUMN),
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $this->buildQueryConditions($tableName, $params)),
        );

        $data = [];

        foreach ($this->connection->fetchAllAssociative($sql, $params) as $row) {
            $data[$row[self::SCHEMA_NAME_COLUMN]][$row[self::TABLE_NAME_COLUMN]] = $row;
        }

        return $data;
    }

    /**
     * @param list<int|string> $params
     *
     * @return non-empty-list<string>
     */
    private function buildQueryConditions(?OptionallyQualifiedName $tableName, array &$params): array
    {
        $conditions = [];

        if ($tableName !== null) {
            $qualifier = $tableName->getQualifier();
            $folding   = $this->platform->getUnquotedIdentifierFolding();

            if ($qualifier !== null) {
                $conditions[] = 'n.nspname = ?';
                $params[]     = $qualifier->toNormalizedValue($folding);
            } else {
                $conditions[] = 'n.nspname = ANY(current_schemas(false))';
            }

            $conditions[] = 'c.relname = ?';
            $params[]     = $tableName->getUnqualifiedName()->toNormalizedValue($folding);
        }

        $conditions[] = "n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')";

        return $conditions;
    }
}
