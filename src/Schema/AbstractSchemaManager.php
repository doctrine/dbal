<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseRequired;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;

use function array_filter;
use function array_intersect;
use function array_map;
use function array_values;
use function count;
use function strtolower;

/**
 * Base class for schema managers. Schema managers are used to inspect and/or
 * modify the database schema/structure.
 *
 * @template T of AbstractPlatform
 */
abstract class AbstractSchemaManager
{
    /** @param T $platform */
    public function __construct(protected Connection $connection, protected AbstractPlatform $platform)
    {
    }

    /**
     * Lists the available databases for this connection.
     *
     * @return array<int, string>
     *
     * @throws Exception
     */
    public function listDatabases(): array
    {
        return array_map(function (array $row): string {
            return $this->_getPortableDatabaseDefinition($row);
        }, $this->connection->fetchAllAssociative(
            $this->platform->getListDatabasesSQL(),
        ));
    }

    /**
     * Returns a list of the names of all schemata in the current database.
     *
     * @return list<string>
     *
     * @throws Exception
     */
    public function listSchemaNames(): array
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Lists the available sequences for this connection.
     *
     * @return array<int, Sequence>
     *
     * @throws Exception
     */
    public function listSequences(): array
    {
        return $this->filterAssetNames(
            array_map(function (array $row): Sequence {
                return $this->_getPortableSequenceDefinition($row);
            }, $this->connection->fetchAllAssociative(
                $this->platform->getListSequencesSQL(
                    $this->getDatabase(__METHOD__),
                ),
            )),
        );
    }

    /**
     * Lists the columns for a given table.
     *
     * In contrast to other libraries and to the old version of Doctrine,
     * this column definition does try to contain the 'primary' column for
     * the reason that it is not portable across different RDBMS. Use
     * {@see listTableIndexes($tableName)} to retrieve the primary key
     * of a table. Where a RDBMS specifies more details, these are held
     * in the platformDetails array.
     *
     * @return array<string, Column>
     *
     * @throws Exception
     */
    public function listTableColumns(string $table): array
    {
        $database = $this->getDatabase(__METHOD__);

        return $this->_getPortableTableColumnList(
            $table,
            $database,
            $this->selectTableColumns($database, $this->normalizeName($table))
                ->fetchAllAssociative(),
        );
    }

    /**
     * Lists the indexes for a given table returning an array of Index instances.
     *
     * Keys of the portable indexes list are all lower-cased.
     *
     * @return array<string, Index>
     *
     * @throws Exception
     */
    public function listTableIndexes(string $table): array
    {
        $database = $this->getDatabase(__METHOD__);
        $table    = $this->normalizeName($table);

        return $this->_getPortableTableIndexesList(
            $this->selectIndexColumns(
                $database,
                $table,
            )->fetchAllAssociative(),
            $table,
        );
    }

    /**
     * Returns true if all the given tables exist.
     *
     * @param array<int, string> $names
     *
     * @throws Exception
     */
    public function tablesExist(array $names): bool
    {
        $names = array_map('strtolower', $names);

        return count($names) === count(array_intersect($names, array_map('strtolower', $this->listTableNames())));
    }

    public function tableExists(string $tableName): bool
    {
        return $this->tablesExist([$tableName]);
    }

    /**
     * Returns a list of all tables in the current database.
     *
     * @return array<int, string>
     *
     * @throws Exception
     */
    public function listTableNames(): array
    {
        return $this->filterAssetNames(
            array_map(function (array $row): string {
                return $this->_getPortableTableDefinition($row);
            }, $this->selectTableNames(
                $this->getDatabase(__METHOD__),
            )->fetchAllAssociative()),
        );
    }

    /**
     * Filters asset names if they are configured to return only a subset of all
     * the found elements.
     *
     * @param array<int, mixed> $assetNames
     *
     * @return array<int, mixed>
     */
    private function filterAssetNames(array $assetNames): array
    {
        $filter = $this->connection->getConfiguration()->getSchemaAssetsFilter();

        return array_values(array_filter($assetNames, $filter));
    }

    /**
     * Lists the tables for this connection.
     *
     * @return list<Table>
     *
     * @throws Exception
     */
    public function listTables(): array
    {
        $database = $this->getDatabase(__METHOD__);

        $tableColumnsByTable      = $this->fetchTableColumnsByTable($database);
        $indexColumnsByTable      = $this->fetchIndexColumnsByTable($database);
        $foreignKeyColumnsByTable = $this->fetchForeignKeyColumnsByTable($database);
        $tableOptionsByTable      = $this->fetchTableOptionsByTable($database);

        $filter = $this->connection->getConfiguration()->getSchemaAssetsFilter();
        $tables = [];

        foreach ($tableColumnsByTable as $tableName => $tableColumns) {
            if (! $filter($tableName)) {
                continue;
            }

            $tables[] = new Table(
                $tableName,
                $this->_getPortableTableColumnList($tableName, $database, $tableColumns),
                $this->_getPortableTableIndexesList($indexColumnsByTable[$tableName] ?? [], $tableName),
                [],
                $this->_getPortableTableForeignKeysList($foreignKeyColumnsByTable[$tableName] ?? []),
                $tableOptionsByTable[$tableName] ?? [],
            );
        }

        return $tables;
    }

    /**
     * An extension point for those platforms where case sensitivity of the object name depends on whether it's quoted.
     *
     * Such platforms should convert a possibly quoted name into a value of the corresponding case.
     */
    protected function normalizeName(string $name): string
    {
        $identifier = new Identifier($name);

        return $identifier->getName();
    }

    /**
     * Selects names of tables in the specified database.
     *
     * @throws Exception
     */
    abstract protected function selectTableNames(string $databaseName): Result;

    /**
     * Selects definitions of table columns in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * @throws Exception
     */
    abstract protected function selectTableColumns(string $databaseName, ?string $tableName = null): Result;

    /**
     * Selects definitions of index columns in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * @throws Exception
     */
    abstract protected function selectIndexColumns(string $databaseName, ?string $tableName = null): Result;

    /**
     * Selects definitions of foreign key columns in the specified database. If the table name is specified,
     * narrows down the selection to this table.
     *
     * @throws Exception
     */
    abstract protected function selectForeignKeyColumns(string $databaseName, ?string $tableName = null): Result;

    /**
     * Fetches definitions of table columns in the specified database and returns them grouped by table name.
     *
     * @return array<string,list<array<string,mixed>>>
     *
     * @throws Exception
     */
    protected function fetchTableColumnsByTable(string $databaseName): array
    {
        return $this->fetchAllAssociativeGrouped($this->selectTableColumns($databaseName));
    }

    /**
     * Fetches definitions of index columns in the specified database and returns them grouped by table name.
     *
     * @return array<string,list<array<string,mixed>>>
     *
     * @throws Exception
     */
    protected function fetchIndexColumnsByTable(string $databaseName): array
    {
        return $this->fetchAllAssociativeGrouped($this->selectIndexColumns($databaseName));
    }

    /**
     * Fetches definitions of foreign key columns in the specified database and returns them grouped by table name.
     *
     * @return array<string, list<array<string, mixed>>>
     *
     * @throws Exception
     */
    protected function fetchForeignKeyColumnsByTable(string $databaseName): array
    {
        return $this->fetchAllAssociativeGrouped(
            $this->selectForeignKeyColumns($databaseName),
        );
    }

    /**
     * Fetches table options for the tables in the specified database and returns them grouped by table name.
     * If the table name is specified, narrows down the selection to this table.
     *
     * @return array<string,array<string,mixed>>
     *
     * @throws Exception
     */
    abstract protected function fetchTableOptionsByTable(string $databaseName, ?string $tableName = null): array;

    /**
     * Introspects the table with the given name.
     *
     * @throws Exception
     */
    public function introspectTable(string $name): Table
    {
        $columns = $this->listTableColumns($name);

        if ($columns === []) {
            throw TableDoesNotExist::new($name);
        }

        return new Table(
            $name,
            $columns,
            $this->listTableIndexes($name),
            [],
            $this->listTableForeignKeys($name),
            $this->getTableOptions($name),
        );
    }

    /**
     * Lists the views this connection has.
     *
     * @return list<View>
     *
     * @throws Exception
     */
    public function listViews(): array
    {
        return array_map(function (array $row): View {
            return $this->_getPortableViewDefinition($row);
        }, $this->connection->fetchAllAssociative(
            $this->platform->getListViewsSQL(
                $this->getDatabase(__METHOD__),
            ),
        ));
    }

    /**
     * Lists the foreign keys for the given table.
     *
     * @return array<int|string, ForeignKeyConstraint>
     *
     * @throws Exception
     */
    public function listTableForeignKeys(string $table): array
    {
        $database = $this->getDatabase(__METHOD__);

        return $this->_getPortableTableForeignKeysList(
            $this->selectForeignKeyColumns(
                $database,
                $this->normalizeName($table),
            )->fetchAllAssociative(),
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function getTableOptions(string $name): array
    {
        $normalizedName = $this->normalizeName($name);

        return $this->fetchTableOptionsByTable(
            $this->getDatabase(__METHOD__),
            $normalizedName,
        )[$normalizedName] ?? [];
    }

    /* drop*() Methods */

    /**
     * Drops a database.
     *
     * NOTE: You can not drop the database this SchemaManager is currently connected to.
     *
     * @throws Exception
     */
    public function dropDatabase(string $database): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropDatabaseSQL($database),
        );
    }

    /**
     * Drops a schema.
     *
     * @throws Exception
     */
    public function dropSchema(string $schemaName): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropSchemaSQL($schemaName),
        );
    }

    /**
     * Drops the given table.
     *
     * @throws Exception
     */
    public function dropTable(string $name): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropTableSQL($name),
        );
    }

    /**
     * Drops the index from the given table.
     *
     * @throws Exception
     */
    public function dropIndex(string $index, string $table): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropIndexSQL($index, $table),
        );
    }

    /**
     * Drops a foreign key from a table.
     *
     * @throws Exception
     */
    public function dropForeignKey(string $name, string $table): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropForeignKeySQL($name, $table),
        );
    }

    /**
     * Drops a sequence with a given name.
     *
     * @throws Exception
     */
    public function dropSequence(string $name): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropSequenceSQL($name),
        );
    }

    /**
     * Drops the unique constraint from the given table.
     *
     * @throws Exception
     */
    public function dropUniqueConstraint(string $name, string $tableName): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropUniqueConstraintSQL($name, $tableName),
        );
    }

    /**
     * Drops a view.
     *
     * @throws Exception
     */
    public function dropView(string $name): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropViewSQL($name),
        );
    }

    /* create*() Methods */

    /** @throws Exception */
    public function createSchemaObjects(Schema $schema): void
    {
        $this->executeStatements($schema->toSql($this->platform));
    }

    /**
     * Creates a new database.
     *
     * @throws Exception
     */
    public function createDatabase(string $database): void
    {
        $this->connection->executeStatement(
            $this->platform->getCreateDatabaseSQL($database),
        );
    }

    /**
     * Creates a new table.
     *
     * @throws Exception
     */
    public function createTable(Table $table): void
    {
        $this->executeStatements($this->platform->getCreateTableSQL($table));
    }

    /**
     * Creates a new sequence.
     *
     * @throws Exception
     */
    public function createSequence(Sequence $sequence): void
    {
        $this->connection->executeStatement(
            $this->platform->getCreateSequenceSQL($sequence),
        );
    }

    /**
     * Creates a new index on a table.
     *
     * @param string $table The name of the table on which the index is to be created.
     *
     * @throws Exception
     */
    public function createIndex(Index $index, string $table): void
    {
        $this->connection->executeStatement(
            $this->platform->getCreateIndexSQL($index, $table),
        );
    }

    /**
     * Creates a new foreign key.
     *
     * @param ForeignKeyConstraint $foreignKey The ForeignKey instance.
     * @param string               $table      The name of the table on which the foreign key is to be created.
     *
     * @throws Exception
     */
    public function createForeignKey(ForeignKeyConstraint $foreignKey, string $table): void
    {
        $this->connection->executeStatement(
            $this->platform->getCreateForeignKeySQL($foreignKey, $table),
        );
    }

    /**
     * Creates a unique constraint on a table.
     *
     * @throws Exception
     */
    public function createUniqueConstraint(UniqueConstraint $uniqueConstraint, string $tableName): void
    {
        $this->connection->executeStatement(
            $this->platform->getCreateUniqueConstraintSQL($uniqueConstraint, $tableName),
        );
    }

    /**
     * Creates a new view.
     *
     * @throws Exception
     */
    public function createView(View $view): void
    {
        $this->connection->executeStatement(
            $this->platform->getCreateViewSQL(
                $view->getQuotedName($this->platform),
                $view->getSql(),
            ),
        );
    }

    /** @throws Exception */
    public function dropSchemaObjects(Schema $schema): void
    {
        $this->executeStatements($schema->toDropSql($this->platform));
    }

    /**
     * Alters an existing schema.
     *
     * @throws Exception
     */
    public function alterSchema(SchemaDiff $schemaDiff): void
    {
        $this->executeStatements($this->platform->getAlterSchemaSQL($schemaDiff));
    }

    /**
     * Migrates an existing schema to a new schema.
     *
     * @throws Exception
     */
    public function migrateSchema(Schema $newSchema): void
    {
        $schemaDiff = $this->createComparator()
            ->compareSchemas($this->introspectSchema(), $newSchema);

        $this->alterSchema($schemaDiff);
    }

    /* alterTable() Methods */

    /**
     * Alters an existing tables schema.
     *
     * @throws Exception
     */
    public function alterTable(TableDiff $tableDiff): void
    {
        $this->executeStatements($this->platform->getAlterTableSQL($tableDiff));
    }

    /**
     * Renames a given table to another name.
     *
     * @throws Exception
     */
    public function renameTable(string $name, string $newName): void
    {
        $this->connection->executeStatement(
            $this->platform->getRenameTableSQL($name, $newName),
        );
    }

    /**
     * Methods for filtering return values of list*() methods to convert
     * the native DBMS data definition to a portable Doctrine definition
     */

    /** @param array<string, string> $database */
    protected function _getPortableDatabaseDefinition(array $database): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /** @param array<string, mixed> $sequence */
    protected function _getPortableSequenceDefinition(array $sequence): Sequence
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Independent of the database the keys of the column list result are lowercased.
     *
     * The name of the created column instance however is kept in its case.
     *
     * @param array<int, array<string, mixed>> $tableColumns
     *
     * @return array<string, Column>
     *
     * @throws Exception
     */
    protected function _getPortableTableColumnList(string $table, string $database, array $tableColumns): array
    {
        $list = [];
        foreach ($tableColumns as $tableColumn) {
            $column = $this->_getPortableTableColumnDefinition($tableColumn);

            $name        = strtolower($column->getQuotedName($this->platform));
            $list[$name] = $column;
        }

        return $list;
    }

    /**
     * Gets Table Column Definition.
     *
     * @param array<string, mixed> $tableColumn
     *
     * @throws Exception
     */
    abstract protected function _getPortableTableColumnDefinition(array $tableColumn): Column;

    /**
     * Aggregates and groups the index results according to the required data result.
     *
     * @param array<int, array<string, mixed>> $tableIndexes
     *
     * @return array<string, Index>
     *
     * @throws Exception
     */
    protected function _getPortableTableIndexesList(array $tableIndexes, string $tableName): array
    {
        $result = [];
        foreach ($tableIndexes as $tableIndex) {
            $indexName = $keyName = $tableIndex['key_name'];
            if ($tableIndex['primary']) {
                $keyName = 'primary';
            }

            $keyName = strtolower($keyName);

            if (! isset($result[$keyName])) {
                $options = [
                    'lengths' => [],
                ];

                if (isset($tableIndex['where'])) {
                    $options['where'] = $tableIndex['where'];
                }

                $result[$keyName] = [
                    'name' => $indexName,
                    'columns' => [],
                    'unique' => ! $tableIndex['non_unique'],
                    'primary' => $tableIndex['primary'],
                    'flags' => $tableIndex['flags'] ?? [],
                    'options' => $options,
                ];
            }

            $result[$keyName]['columns'][]            = $tableIndex['column_name'];
            $result[$keyName]['options']['lengths'][] = $tableIndex['length'] ?? null;
        }

        $indexes = [];
        foreach ($result as $indexKey => $data) {
            $indexes[$indexKey] = new Index(
                $data['name'],
                $data['columns'],
                $data['unique'],
                $data['primary'],
                $data['flags'],
                $data['options'],
            );
        }

        return $indexes;
    }

    /** @param array<string, string> $table */
    abstract protected function _getPortableTableDefinition(array $table): string;

    /** @param array<string, mixed> $view */
    abstract protected function _getPortableViewDefinition(array $view): View;

    /**
     * @param array<int|string, array<string, mixed>> $tableForeignKeys
     *
     * @return array<int, ForeignKeyConstraint>
     */
    protected function _getPortableTableForeignKeysList(array $tableForeignKeys): array
    {
        $list = [];

        foreach ($tableForeignKeys as $value) {
            $list[] = $this->_getPortableTableForeignKeyDefinition($value);
        }

        return $list;
    }

    /** @param array<string, mixed> $tableForeignKey */
    abstract protected function _getPortableTableForeignKeyDefinition(array $tableForeignKey): ForeignKeyConstraint;

    /**
     * @param array<int, string> $sql
     *
     * @throws Exception
     */
    private function executeStatements(array $sql): void
    {
        foreach ($sql as $query) {
            $this->connection->executeStatement($query);
        }
    }

    /**
     * Returns a {@see Schema} instance representing the current database schema.
     *
     * @throws Exception
     */
    public function introspectSchema(): Schema
    {
        $schemaNames = [];

        if ($this->platform->supportsSchemas()) {
            $schemaNames = $this->listSchemaNames();
        }

        $sequences = [];

        if ($this->platform->supportsSequences()) {
            $sequences = $this->listSequences();
        }

        $tables = $this->listTables();

        return new Schema($tables, $sequences, $this->createSchemaConfig(), $schemaNames);
    }

    /**
     * Creates the configuration for this schema.
     *
     * @throws Exception
     */
    public function createSchemaConfig(): SchemaConfig
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setMaxIdentifierLength($this->platform->getMaxIdentifierLength());

        $params = $this->connection->getParams();
        if (! isset($params['defaultTableOptions'])) {
            $params['defaultTableOptions'] = [];
        }

        if (! isset($params['defaultTableOptions']['charset']) && isset($params['charset'])) {
            $params['defaultTableOptions']['charset'] = $params['charset'];
        }

        $schemaConfig->setDefaultTableOptions($params['defaultTableOptions']);

        return $schemaConfig;
    }

    /** @throws Exception */
    private function getDatabase(string $methodName): string
    {
        $database = $this->connection->getDatabase();

        if ($database === null) {
            throw DatabaseRequired::new($methodName);
        }

        return $database;
    }

    public function createComparator(): Comparator
    {
        return new Comparator($this->platform);
    }

    /**
     * @return array<string,list<array<string,mixed>>>
     *
     * @throws Exception
     */
    private function fetchAllAssociativeGrouped(Result $result): array
    {
        $data = [];

        foreach ($result->fetchAllAssociative() as $row) {
            $tableName          = $this->_getPortableTableDefinition($row);
            $data[$tableName][] = $row;
        }

        return $data;
    }
}
