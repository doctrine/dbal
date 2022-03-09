<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Type;

use function array_change_key_case;
use function array_keys;
use function array_map;
use function assert;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function preg_match;
use function sprintf;
use function str_replace;
use function strpos;
use function strtolower;
use function trim;

use const CASE_LOWER;

/**
 * PostgreSQL Schema Manager.
 *
 * @extends AbstractSchemaManager<PostgreSQLPlatform>
 */
class PostgreSQLSchemaManager extends AbstractSchemaManager
{
    private ?string $currentSchema = null;

    /**
     * {@inheritDoc}
     */
    public function listSchemaNames(): array
    {
        return $this->_conn->fetchFirstColumn(
            <<<'SQL'
SELECT schema_name
FROM   information_schema.schemata
WHERE  schema_name NOT LIKE 'pg\_%'
AND    schema_name != 'information_schema'
SQL
        );
    }

    public function createSchemaConfig(): SchemaConfig
    {
        $config = parent::createSchemaConfig();

        $config->setName($this->getCurrentSchema());

        return $config;
    }

    /**
     * Returns the name of the current schema.
     *
     * @throws Exception
     */
    protected function getCurrentSchema(): ?string
    {
        return $this->currentSchema ??= $this->determineCurrentSchema();
    }

    /**
     * Determines the name of the current schema.
     *
     * @throws Exception
     */
    protected function determineCurrentSchema(): string
    {
        $currentSchema = $this->_conn->fetchOne('SELECT current_schema()');
        assert(is_string($currentSchema));

        return $currentSchema;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableForeignKeyDefinition(array $tableForeignKey): ForeignKeyConstraint
    {
        $onUpdate = null;
        $onDelete = null;

        if (
            preg_match(
                '(ON UPDATE ([a-zA-Z0-9]+( (NULL|ACTION|DEFAULT))?))',
                $tableForeignKey['condef'],
                $match
            ) === 1
        ) {
            $onUpdate = $match[1];
        }

        if (
            preg_match(
                '(ON DELETE ([a-zA-Z0-9]+( (NULL|ACTION|DEFAULT))?))',
                $tableForeignKey['condef'],
                $match
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
            ['onUpdate' => $onUpdate, 'onDelete' => $onDelete]
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        return new View($view['schemaname'] . '.' . $view['viewname'], $view['definition']);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableDefinition(array $table): string
    {
        $currentSchema = $this->getCurrentSchema();

        if ($table['schema_name'] === $currentSchema) {
            return $table['table_name'];
        }

        return $table['schema_name'] . '.' . $table['table_name'];
    }

    /**
     * {@inheritdoc}
     *
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaPgsqlReader.html
     */
    protected function _getPortableTableIndexesList(array $tableIndexes, string $tableName): array
    {
        $buffer = [];
        foreach ($tableIndexes as $row) {
            $colNumbers    = array_map('intval', explode(' ', $row['indkey']));
            $columnNameSql = sprintf(
                'SELECT attnum, attname FROM pg_attribute WHERE attrelid=%d AND attnum IN (%s) ORDER BY attnum ASC',
                $row['indrelid'],
                implode(' ,', $colNumbers)
            );

            $indexColumns = $this->_conn->fetchAllAssociative($columnNameSql);

            // required for getting the order of the columns right.
            foreach ($colNumbers as $colNum) {
                foreach ($indexColumns as $colRow) {
                    if ($colNum !== $colRow['attnum']) {
                        continue;
                    }

                    $buffer[] = [
                        'key_name' => $row['relname'],
                        'column_name' => trim($colRow['attname']),
                        'non_unique' => ! $row['indisunique'],
                        'primary' => $row['indisprimary'],
                        'where' => $row['where'],
                    ];
                }
            }
        }

        return parent::_getPortableTableIndexesList($buffer, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableDatabaseDefinition(array $database): string
    {
        return $database['datname'];
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableSequencesList(array $sequences): array
    {
        $sequenceDefinitions = [];

        foreach ($sequences as $sequence) {
            if ($sequence['schemaname'] !== 'public') {
                $sequenceName = $sequence['schemaname'] . '.' . $sequence['relname'];
            } else {
                $sequenceName = $sequence['relname'];
            }

            $sequenceDefinitions[$sequenceName] = $sequence;
        }

        $list = [];

        foreach ($this->filterAssetNames(array_keys($sequenceDefinitions)) as $sequenceName) {
            $list[] = $this->_getPortableSequenceDefinition($sequenceDefinitions[$sequenceName]);
        }

        return $list;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        $length = null;

        if (
            in_array(strtolower($tableColumn['type']), ['varchar', 'bpchar'], true)
            && preg_match('/\((\d*)\)/', $tableColumn['complete_type'], $matches) === 1
        ) {
            $length = (int) $matches[1];
        }

        $matches = [];

        $autoincrement = false;

        if (
            $tableColumn['default'] !== null
            && preg_match("/^nextval\('(.*)'(::.*)?\)$/", $tableColumn['default'], $matches) === 1
        ) {
            $tableColumn['sequence'] = $matches[1];
            $tableColumn['default']  = null;
            $autoincrement           = true;
        }

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

        $dbType = strtolower($tableColumn['type']);
        if (
            $tableColumn['domain_type'] !== null
            && $tableColumn['domain_type'] !== ''
            && ! $this->_platform->hasDoctrineTypeMappingFor($tableColumn['type'])
        ) {
            $dbType                       = strtolower($tableColumn['domain_type']);
            $tableColumn['complete_type'] = $tableColumn['domain_complete_type'];
        }

        $type = $this->_platform->getDoctrineTypeMapping($dbType);

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
                        $match
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
                $match
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
            'unsigned'      => false,
            'autoincrement' => $autoincrement,
        ];

        if (isset($tableColumn['comment'])) {
            $options['comment'] = $tableColumn['comment'];
        }

        $column = new Column($tableColumn['field'], Type::getType($type), $options);

        if (isset($tableColumn['collation']) && ! empty($tableColumn['collation'])) {
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

    protected function selectDatabaseColumns(string $databaseName, ?string $tableName = null): Result
    {
        $sql = 'SELECT';

        if ($tableName === null) {
            $sql .= ' c.relname,';
        }

        $sql .= <<<'SQL'
            a.attnum,
            quote_ident(a.attname) AS field,
            t.typname AS type,
            format_type(a.atttypid, a.atttypmod) AS complete_type,
            (SELECT tc.collcollate FROM pg_catalog.pg_collation tc WHERE tc.oid = a.attcollation) AS collation,
            (SELECT t1.typname FROM pg_catalog.pg_type t1 WHERE t1.oid = t.typbasetype) AS domain_type,
            (SELECT format_type(t2.typbasetype, t2.typtypmod) FROM
              pg_catalog.pg_type t2 WHERE t2.typtype = 'd' AND t2.oid = a.atttypid) AS domain_complete_type,
            a.attnotnull AS isnotnull,
            (SELECT 't'
             FROM pg_index
             WHERE c.oid = pg_index.indrelid
                AND pg_index.indkey[0] = a.attnum
                AND pg_index.indisprimary = 't'
            ) AS pri,
            (SELECT pg_get_expr(adbin, adrelid)
             FROM pg_attrdef
             WHERE c.oid = pg_attrdef.adrelid
                AND pg_attrdef.adnum=a.attnum
            ) AS default,
            (SELECT pg_description.description
                FROM pg_description WHERE pg_description.objoid = c.oid AND a.attnum = pg_description.objsubid
            ) AS comment
            FROM pg_attribute a, pg_class c, pg_type t, pg_namespace n
SQL;

        $conditions = [
            'a.attnum > 0',
            'a.attrelid = c.oid',
            'a.atttypid = t.oid',
            'n.oid = c.relnamespace',
            "c.relkind = 'r'",
        ];
        $params     = [];

        if ($tableName !== null) {
            $conditions[] = $this->getTableWhereClause($tableName, 'c', 'n');
        } else {
            $conditions[] = "n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')";
            $conditions[] = 'n.nspname = ANY(current_schemas(false))';
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY a.attnum';

        return $this->_conn->executeQuery($sql, $params);
    }

    protected function selectDatabaseIndexes(string $databaseName, ?string $tableName = null): Result
    {
        $sql = 'SELECT';

        if ($tableName === null) {
            $sql .= ' pg_index.indrelid::REGCLASS AS tablename,';
        }

        $sql .= <<<'SQL'
                   quote_ident(relname) AS relname,
                   pg_index.indisunique,
                   pg_index.indisprimary,
                   pg_index.indkey,
                   pg_index.indrelid,
                   pg_get_expr(indpred, indrelid) AS where
              FROM pg_class, pg_index
             WHERE oid IN (
                SELECT indexrelid
                FROM pg_index si, pg_class sc, pg_namespace sn
SQL;

        $conditions = ['sc.oid=si.indrelid', 'sc.relnamespace = sn.oid'];
        $params     = [];

        if ($tableName !== null) {
            $conditions[] = $this->getTableWhereClause($tableName, 'sc', 'sn');
        } else {
            $conditions[] = "sn.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')";
            $conditions[] = 'sn.nspname = ANY(current_schemas(false))';
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions) . ') AND pg_index.indexrelid = oid';

        return $this->_conn->executeQuery($sql, $params);
    }

    protected function selectDatabaseForeignKeys(string $databaseName, ?string $tableName = null): Result
    {
        $sql = 'SELECT';

        if ($tableName === null) {
            $sql .= ' r.conrelid :: REGCLASS as tablename,';
        }

        $sql .= <<<'SQL'
                  quote_ident(r.conname) as conname, pg_catalog.pg_get_constraintdef(r.oid, true) as condef
                  FROM pg_catalog.pg_constraint r
                  WHERE r.conrelid IN
                  (
                      SELECT c.oid
                      FROM pg_catalog.pg_class c, pg_catalog.pg_namespace n
SQL;

        $conditions = ['n.oid = c.relnamespace'];
        $params     = [];

        if ($tableName !== null) {
            $conditions[] = $this->getTableWhereClause($tableName);
        } else {
            $conditions[] = "n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')";
            $conditions[] = 'n.nspname = ANY(current_schemas(false))';
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions) . ") AND r.contype = 'f'";

        return $this->_conn->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDatabaseTableOptions(string $databaseName, ?string $tableName = null): array
    {
        if ($tableName === null) {
            $tables = $this->listTableNames();
        } else {
            $tables = [$tableName];
        }

        $tableOptions = [];
        foreach ($tables as $table) {
            $sql     = 'SELECT obj_description(?::regclass) AS table_comment;';
            $comment = $this->_conn->executeQuery($sql, [$table])->fetchOne();

            if ($comment === null) {
                continue;
            }

            $tableOptions[$table]['comment'] = $comment;
        }

        return $tableOptions;
    }

    private function getTableWhereClause(string $table, string $classAlias = 'c', string $namespaceAlias = 'n'): string
    {
        $whereClause = $namespaceAlias . ".nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast') AND ";
        if (strpos($table, '.') !== false) {
            [$schema, $table] = explode('.', $table);
            $schema           = $this->_platform->quoteStringLiteral($schema);
        } else {
            $schema = 'ANY(current_schemas(false))';
        }

        $table = new Identifier($table);
        $table = $this->_platform->quoteStringLiteral($table->getName());

        return $whereClause . sprintf(
            '%s.relname = %s AND %s.nspname = %s',
            $classAlias,
            $table,
            $namespaceAlias,
            $schema
        );
    }
}
