<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Types\Type;

use function array_change_key_case;
use function array_map;
use function assert;
use function count;
use function implode;
use function preg_match;
use function sprintf;
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

        $length    = null;
        $precision = null;
        $scale     = 0;
        $fixed     = false;

        $dbType = $tableColumn['typname'];
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

        $column = new Column($tableColumn['attname'], Type::getType($type), $options);

        if (! empty($tableColumn['collation'])) {
            $column->setPlatformOption('collation', $tableColumn['collation']);
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
            SELECT n.nspname                            AS %s,
                   c.relname                            AS %s,
                   quote_ident(a.attname)               AS attname,
                   t.typname,
                   format_type(a.atttypid, a.atttypmod) AS complete_type,
                   bt.typname                           AS domain_type,
                   format_type(bt.oid, t.typtypmod)     AS domain_complete_type,
                   a.attnotnull                         AS isnotnull,
                   a.attidentity,
                   (SELECT
                        CASE
                            WHEN a.attgenerated = 's' THEN NULL
                            ELSE pg_get_expr(adbin, adrelid)
                        END
                     FROM pg_attrdef
                     WHERE c.oid = pg_attrdef.adrelid
                         AND pg_attrdef.adnum=a.attnum) AS "default",
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
