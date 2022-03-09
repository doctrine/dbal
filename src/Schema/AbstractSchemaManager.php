<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs;
use Doctrine\DBAL\Event\SchemaIndexDefinitionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseRequired;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Result;

use function array_filter;
use function array_intersect;
use function array_map;
use function array_shift;
use function array_values;
use function assert;
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
    /**
     * Holds instance of the Doctrine connection for this schema manager.
     */
    protected Connection $_conn;

    /**
     * Holds instance of the database platform used for this schema manager.
     *
     * @var T
     */
    protected AbstractPlatform $_platform;

    /**
     * @param T $platform
     */
    public function __construct(Connection $connection, AbstractPlatform $platform)
    {
        $this->_conn     = $connection;
        $this->_platform = $platform;
    }

    /**
     * Returns the associated platform.
     *
     * @return T
     */
    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->_platform;
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
        $sql = $this->_platform->getListDatabasesSQL();

        $databases = $this->_conn->fetchAllAssociative($sql);

        return $this->_getPortableDatabasesList($databases);
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
        $database = $this->getDatabase(__METHOD__);

        $sql = $this->_platform->getListSequencesSQL($database);

        $sequences = $this->_conn->fetchAllAssociative($sql);

        return $this->filterAssetNames($this->_getPortableSequencesList($sequences));
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
            $this->selectDatabaseColumns($database, $this->normalizeName($table))
                ->fetchAllAssociative()
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

        return $this->_getPortableTableIndexesList(
            $this->selectDatabaseIndexes(
                $database,
                $this->normalizeName($table)
            )->fetchAllAssociative(),
            $table
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
        $sql = $this->_platform->getListTablesSQL();

        $tables     = $this->_conn->fetchAllAssociative($sql);
        $tableNames = $this->_getPortableTablesList($tables);

        return $this->filterAssetNames($tableNames);
    }

    /**
     * Filters asset names if they are configured to return only a subset of all
     * the found elements.
     *
     * @param array<int, mixed> $assetNames
     *
     * @return array<int, mixed>
     */
    protected function filterAssetNames(array $assetNames): array
    {
        $filter = $this->_conn->getConfiguration()->getSchemaAssetsFilter();
        if ($filter === null) {
            return $assetNames;
        }

        return array_values(array_filter($assetNames, $filter));
    }

    /**
     * Lists the tables for this connection.
     *
     * @return array<int, Table>
     *
     * @throws Exception
     */
    public function listTables(): array
    {
        $database = $this->getDatabase(__METHOD__);

        /** @var array<string,list<array<string,mixed>>> $columns */
        $columns = $this->fetchAllAssociativeGrouped(
            $this->selectDatabaseColumns($database)
        );

        $indexes = $this->fetchAllAssociativeGrouped(
            $this->selectDatabaseIndexes($database)
        );

        if ($this->_platform->supportsForeignKeyConstraints()) {
            $foreignKeys = $this->fetchAllAssociativeGrouped(
                $this->selectDatabaseForeignKeys($database)
            );
        } else {
            $foreignKeys = [];
        }

        $tableOptions = $this->getDatabaseTableOptions($database);

        $tables = [];

        foreach ($columns as $tableName => $tableColumns) {
            $tables[] = new Table(
                $tableName,
                $this->_getPortableTableColumnList($tableName, $database, $tableColumns),
                $this->_getPortableTableIndexesList($indexes[$tableName] ?? [], $tableName),
                [],
                $this->_getPortableTableForeignKeysList($foreignKeys[$tableName] ?? []),
                $tableOptions[$tableName] ?? []
            );
        }

        return $tables;
    }

    /**
     * @throws Exception
     */
    public function listTableDetails(string $name): Table
    {
        $database = $this->getDatabase(__METHOD__);

        $normalizedName = $this->normalizeName($name);

        $tableOptions = $this->getDatabaseTableOptions($database, $normalizedName);

        if ($this->_platform->supportsForeignKeyConstraints()) {
            $foreignKeys = $this->listTableForeignKeys($name);
        } else {
            $foreignKeys = [];
        }

        return new Table(
            $name,
            $this->listTableColumns($name),
            $this->listTableIndexes($name),
            [],
            $foreignKeys,
            $tableOptions[$normalizedName] ?? []
        );
    }

    /**
     * An extension point for those platforms where case sensitivity of the object name depends on whether it's quoted.
     *
     * Such platforms should convert a possibly quoted name into a value of the corresponding case.
     */
    protected function normalizeName(string $name): string
    {
        return $name;
    }

    /**
     * Selects column definitions of the tables in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * @throws Exception
     */
    abstract protected function selectDatabaseColumns(string $databaseName, ?string $tableName = null): Result;

    /**
     * Selects index definitions of the tables in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * @throws Exception
     */
    abstract protected function selectDatabaseIndexes(string $databaseName, ?string $tableName = null): Result;

    /**
     * Selects foreign key definitions of the tables in the specified database. If the table name is specified,
     * narrows down the selection to this table.
     *
     * @throws Exception
     */
    abstract protected function selectDatabaseForeignKeys(string $databaseName, ?string $tableName = null): Result;

    /**
     * Returns table options for the tables in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * @return array<string,array<string,mixed>>
     *
     * @throws Exception
     */
    abstract protected function getDatabaseTableOptions(string $databaseName, ?string $tableName = null): array;

    /**
     * Lists the views this connection has.
     *
     * @return array<string, View>
     *
     * @throws Exception
     */
    public function listViews(): array
    {
        $database = $this->getDatabase(__METHOD__);

        $sql   = $this->_platform->getListViewsSQL($database);
        $views = $this->_conn->fetchAllAssociative($sql);

        return $this->_getPortableViewsList($views);
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
            $this->selectDatabaseForeignKeys(
                $database,
                $this->normalizeName($table)
            )->fetchAllAssociative()
        );
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
        $this->_execSql($this->_platform->getDropDatabaseSQL($database));
    }

    /**
     * Drops a schema.
     *
     * @throws Exception
     */
    public function dropSchema(string $schemaName): void
    {
        $this->_execSql($this->_platform->getDropSchemaSQL($schemaName));
    }

    /**
     * Drops the given table.
     *
     * @throws Exception
     */
    public function dropTable(string $name): void
    {
        $this->_execSql($this->_platform->getDropTableSQL($name));
    }

    /**
     * Drops the index from the given table.
     *
     * @throws Exception
     */
    public function dropIndex(string $index, string $table): void
    {
        $this->_execSql($this->_platform->getDropIndexSQL($index, $table));
    }

    /**
     * Drops a foreign key from a table.
     *
     * @throws Exception
     */
    public function dropForeignKey(string $name, string $table): void
    {
        $this->_execSql($this->_platform->getDropForeignKeySQL($name, $table));
    }

    /**
     * Drops a sequence with a given name.
     *
     * @throws Exception
     */
    public function dropSequence(string $name): void
    {
        $this->_execSql($this->_platform->getDropSequenceSQL($name));
    }

    /**
     * Drops the unique constraint from the given table.
     *
     * @throws Exception
     */
    public function dropUniqueConstraint(string $name, string $tableName): void
    {
        $this->_execSql($this->_platform->getDropUniqueConstraintSQL($name, $tableName));
    }

    /**
     * Drops a view.
     *
     * @throws Exception
     */
    public function dropView(string $name): void
    {
        $this->_execSql($this->_platform->getDropViewSQL($name));
    }

    /* create*() Methods */

    /**
     * Creates a new database.
     *
     * @throws Exception
     */
    public function createDatabase(string $database): void
    {
        $this->_execSql($this->_platform->getCreateDatabaseSQL($database));
    }

    /**
     * Creates a new table.
     *
     * @throws Exception
     */
    public function createTable(Table $table): void
    {
        $createFlags = AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS;
        $this->_execSql($this->_platform->getCreateTableSQL($table, $createFlags));
    }

    /**
     * Creates a new sequence.
     *
     * @throws Exception
     */
    public function createSequence(Sequence $sequence): void
    {
        $this->_execSql($this->_platform->getCreateSequenceSQL($sequence));
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
        $this->_execSql($this->_platform->getCreateIndexSQL($index, $table));
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
        $this->_execSql($this->_platform->getCreateForeignKeySQL($foreignKey, $table));
    }

    /**
     * Creates a unique constraint on a table.
     *
     * @throws Exception
     */
    public function createUniqueConstraint(UniqueConstraint $uniqueConstraint, string $tableName): void
    {
        $this->_execSql($this->_platform->getCreateUniqueConstraintSQL($uniqueConstraint, $tableName));
    }

    /**
     * Creates a new view.
     *
     * @throws Exception
     */
    public function createView(View $view): void
    {
        $this->_execSql($this->_platform->getCreateViewSQL($view->getQuotedName($this->_platform), $view->getSql()));
    }

    /**
     * Alters an existing schema.
     *
     * @throws Exception
     */
    public function alterSchema(SchemaDiff $schemaDiff): void
    {
        $this->_execSql($schemaDiff->toSql($this->_platform));
    }

    /**
     * Migrates an existing schema to a new schema.
     *
     * @throws Exception
     */
    public function migrateSchema(Schema $toSchema): void
    {
        $schemaDiff = $this->createComparator()
            ->compareSchemas($this->createSchema(), $toSchema);

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
        foreach ($this->_platform->getAlterTableSQL($tableDiff) as $ddlQuery) {
            $this->_execSql($ddlQuery);
        }
    }

    /**
     * Renames a given table to another name.
     *
     * @throws Exception
     */
    public function renameTable(string $name, string $newName): void
    {
        $tableDiff          = new TableDiff($name);
        $tableDiff->newName = $newName;
        $this->alterTable($tableDiff);
    }

    /**
     * Methods for filtering return values of list*() methods to convert
     * the native DBMS data definition to a portable Doctrine definition
     */

    /**
     * @param array<int, mixed> $databases
     *
     * @return array<int, string>
     */
    protected function _getPortableDatabasesList(array $databases): array
    {
        $list = [];
        foreach ($databases as $value) {
            $list[] = $this->_getPortableDatabaseDefinition($value);
        }

        return $list;
    }

    /**
     * @param array<string, string> $database
     */
    protected function _getPortableDatabaseDefinition(array $database): string
    {
        assert(! empty($database));

        return array_shift($database);
    }

    /**
     * @param array<int, array<string, mixed>> $sequences
     *
     * @return array<int, Sequence>
     *
     * @throws Exception
     */
    protected function _getPortableSequencesList(array $sequences): array
    {
        $list = [];

        foreach ($sequences as $value) {
            $list[] = $this->_getPortableSequenceDefinition($value);
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $sequence
     *
     * @throws Exception
     */
    protected function _getPortableSequenceDefinition(array $sequence): Sequence
    {
        throw NotSupported::new('Sequences');
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
        $eventManager = $this->_platform->getEventManager();

        $list = [];
        foreach ($tableColumns as $tableColumn) {
            $column           = null;
            $defaultPrevented = false;

            if ($eventManager !== null && $eventManager->hasListeners(Events::onSchemaColumnDefinition)) {
                $eventArgs = new SchemaColumnDefinitionEventArgs($tableColumn, $table, $database, $this->_conn);
                $eventManager->dispatchEvent(Events::onSchemaColumnDefinition, $eventArgs);

                $defaultPrevented = $eventArgs->isDefaultPrevented();
                $column           = $eventArgs->getColumn();
            }

            if (! $defaultPrevented) {
                $column = $this->_getPortableTableColumnDefinition($tableColumn);
            }

            if ($column === null) {
                continue;
            }

            $name        = strtolower($column->getQuotedName($this->_platform));
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

        $eventManager = $this->_platform->getEventManager();

        $indexes = [];
        foreach ($result as $indexKey => $data) {
            $index            = null;
            $defaultPrevented = false;

            if ($eventManager !== null && $eventManager->hasListeners(Events::onSchemaIndexDefinition)) {
                $eventArgs = new SchemaIndexDefinitionEventArgs($data, $tableName, $this->_conn);
                $eventManager->dispatchEvent(Events::onSchemaIndexDefinition, $eventArgs);

                $defaultPrevented = $eventArgs->isDefaultPrevented();
                $index            = $eventArgs->getIndex();
            }

            if (! $defaultPrevented) {
                $index = new Index(
                    $data['name'],
                    $data['columns'],
                    $data['unique'],
                    $data['primary'],
                    $data['flags'],
                    $data['options']
                );
            }

            if ($index === null) {
                continue;
            }

            $indexes[$indexKey] = $index;
        }

        return $indexes;
    }

    /**
     * @param array<int, array<string, mixed>> $tables
     *
     * @return array<int, string>
     */
    protected function _getPortableTablesList(array $tables): array
    {
        $list = [];
        foreach ($tables as $value) {
            $list[] = $this->_getPortableTableDefinition($value);
        }

        return $list;
    }

    /**
     * @param array<string, string> $table
     */
    protected function _getPortableTableDefinition(array $table): string
    {
        assert(! empty($table));

        return array_shift($table);
    }

    /**
     * @param array<int, array<string, mixed>> $views
     *
     * @return array<string, View>
     */
    protected function _getPortableViewsList(array $views): array
    {
        $list = [];
        foreach ($views as $value) {
            $view        = $this->_getPortableViewDefinition($value);
            $name        = strtolower($view->getQuotedName($this->_platform));
            $list[$name] = $view;
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $view
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        throw NotSupported::new('Views');
    }

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

    /**
     * @param array<string, mixed> $tableForeignKey
     */
    protected function _getPortableTableForeignKeyDefinition(array $tableForeignKey): ForeignKeyConstraint
    {
        throw NotSupported::new('ForeignKey');
    }

    /**
     * @param array<int, string>|string $sql
     *
     * @throws Exception
     */
    protected function _execSql(array|string $sql): void
    {
        foreach ((array) $sql as $query) {
            $this->_conn->executeStatement($query);
        }
    }

    /**
     * Creates a schema instance for the current database.
     *
     * @throws Exception
     */
    public function createSchema(): Schema
    {
        $schemaNames = [];

        if ($this->_platform->supportsSchemas()) {
            $schemaNames = $this->listSchemaNames();
        }

        $sequences = [];

        if ($this->_platform->supportsSequences()) {
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
        $schemaConfig->setMaxIdentifierLength($this->_platform->getMaxIdentifierLength());

        $params = $this->_conn->getParams();
        if (! isset($params['defaultTableOptions'])) {
            $params['defaultTableOptions'] = [];
        }

        if (! isset($params['defaultTableOptions']['charset']) && isset($params['charset'])) {
            $params['defaultTableOptions']['charset'] = $params['charset'];
        }

        $schemaConfig->setDefaultTableOptions($params['defaultTableOptions']);

        return $schemaConfig;
    }

    /**
     * @throws Exception
     */
    private function getDatabase(string $methodName): string
    {
        $database = $this->_conn->getDatabase();

        if ($database === null) {
            throw DatabaseRequired::new($methodName);
        }

        return $database;
    }

    public function createComparator(): Comparator
    {
        return new Comparator($this->getDatabasePlatform());
    }

    /**
     * @return array<mixed,list<array<string,mixed>>>
     *
     * @throws Exception
     */
    private function fetchAllAssociativeGrouped(Result $result): array
    {
        $data = [];

        foreach ($result->fetchAllAssociative() as $row) {
            $data[array_shift($row)][] = $row;
        }

        return $data;
    }
}
