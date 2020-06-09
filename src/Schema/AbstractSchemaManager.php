<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs;
use Doctrine\DBAL\Event\SchemaIndexDefinitionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception\DatabaseRequired;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Throwable;

use function array_filter;
use function array_intersect;
use function array_map;
use function array_shift;
use function array_values;
use function assert;
use function count;
use function preg_match;
use function strtolower;

/**
 * Base class for schema managers. Schema managers are used to inspect and/or
 * modify the database schema/structure.
 */
abstract class AbstractSchemaManager
{
    /**
     * Holds instance of the Doctrine connection for this schema manager.
     *
     * @var Connection
     */
    protected $_conn;

    /**
     * Holds instance of the database platform used for this schema manager.
     *
     * @var AbstractPlatform
     */
    protected $_platform;

    /**
     * Constructor. Accepts the Connection instance to manage the schema for.
     */
    public function __construct(Connection $conn, ?AbstractPlatform $platform = null)
    {
        $this->_conn     = $conn;
        $this->_platform = $platform ?? $this->_conn->getDatabasePlatform();
    }

    /**
     * Returns the associated platform.
     */
    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->_platform;
    }

    /**
     * Tries any method on the schema manager. Normally a method throws an
     * exception when your DBMS doesn't support it or if an error occurs.
     * This method allows you to try and method on your SchemaManager
     * instance and will return false if it does not work or is not supported.
     *
     * <code>
     * $result = $sm->tryMethod('dropView', 'view_name');
     * </code>
     *
     * @param mixed ...$arguments
     *
     * @return mixed
     */
    public function tryMethod(string $method, ...$arguments)
    {
        try {
            return $this->$method(...$arguments);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Lists the available databases for this connection.
     *
     * @return array<int, string>
     */
    public function listDatabases(): array
    {
        $sql = $this->_platform->getListDatabasesSQL();

        $databases = $this->_conn->fetchAllAssociative($sql);

        return $this->_getPortableDatabasesList($databases);
    }

    /**
     * Returns a list of all namespaces in the current database.
     *
     * @return array<int, string>
     */
    public function listNamespaceNames(): array
    {
        $sql = $this->_platform->getListNamespacesSQL();

        $namespaces = $this->_conn->fetchAllAssociative($sql);

        return $this->getPortableNamespacesList($namespaces);
    }

    /**
     * Lists the available sequences for this connection.
     *
     * @return array<int, Sequence>
     */
    public function listSequences(?string $database = null): array
    {
        $database = $this->ensureDatabase(
            $database ?? $this->_conn->getDatabase(),
            __METHOD__
        );

        $sql = $this->_platform->getListSequencesSQL($database);

        $sequences = $this->_conn->fetchAllAssociative($sql);

        return $this->filterAssetNames($this->_getPortableSequencesList($sequences));
    }

    /**
     * Lists the columns for a given table.
     *
     * In contrast to other libraries and to the old version of Doctrine,
     * this column definition does try to contain the 'primary' field for
     * the reason that it is not portable across different RDBMS. Use
     * {@see listTableIndexes($tableName)} to retrieve the primary key
     * of a table. Where a RDBMS specifies more details, these are held
     * in the platformDetails array.
     *
     * @return array<string, Column>
     */
    public function listTableColumns(string $table, ?string $database = null): array
    {
        $database = $this->ensureDatabase(
            $database ?? $this->_conn->getDatabase(),
            __METHOD__
        );

        $sql = $this->_platform->getListTableColumnsSQL($table, $database);

        $tableColumns = $this->_conn->fetchAllAssociative($sql);

        return $this->_getPortableTableColumnList($table, $database, $tableColumns);
    }

    /**
     * Lists the indexes for a given table returning an array of Index instances.
     *
     * Keys of the portable indexes list are all lower-cased.
     *
     * @return array<string, Index>
     */
    public function listTableIndexes(string $table): array
    {
        $sql = $this->_platform->getListTableIndexesSQL($table, $this->_conn->getDatabase());

        $tableIndexes = $this->_conn->fetchAllAssociative($sql);

        return $this->_getPortableTableIndexesList($tableIndexes, $table);
    }

    /**
     * Returns true if all the given tables exist.
     *
     * @param array<int, string> $tableNames
     */
    public function tablesExist(array $tableNames): bool
    {
        $tableNames = array_map('strtolower', $tableNames);

        return count($tableNames) === count(array_intersect($tableNames, array_map('strtolower', $this->listTableNames())));
    }

    public function tableExists(string $tableName): bool
    {
        return $this->tablesExist([$tableName]);
    }

    /**
     * Returns a list of all tables in the current database.
     *
     * @return array<int, string>
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
     */
    public function listTables(): array
    {
        $tableNames = $this->listTableNames();

        $tables = [];
        foreach ($tableNames as $tableName) {
            $tables[] = $this->listTableDetails($tableName);
        }

        return $tables;
    }

    public function listTableDetails(string $tableName): Table
    {
        $columns     = $this->listTableColumns($tableName);
        $foreignKeys = [];

        if ($this->_platform->supportsForeignKeyConstraints()) {
            $foreignKeys = $this->listTableForeignKeys($tableName);
        }

        $indexes = $this->listTableIndexes($tableName);

        return new Table($tableName, $columns, $indexes, [], $foreignKeys, []);
    }

    /**
     * Lists the views this connection has.
     *
     * @return array<string, View>
     */
    public function listViews(): array
    {
        $database = $this->ensureDatabase(
            $this->_conn->getDatabase(),
            __METHOD__
        );

        $sql   = $this->_platform->getListViewsSQL($database);
        $views = $this->_conn->fetchAllAssociative($sql);

        return $this->_getPortableViewsList($views);
    }

    /**
     * Lists the foreign keys for the given table.
     *
     * @return array<int|string, ForeignKeyConstraint>
     */
    public function listTableForeignKeys(string $table, ?string $database = null): array
    {
        if ($database === null) {
            $database = $this->_conn->getDatabase();
        }

        $sql              = $this->_platform->getListTableForeignKeysSQL($table, $database);
        $tableForeignKeys = $this->_conn->fetchAllAssociative($sql);

        return $this->_getPortableTableForeignKeysList($tableForeignKeys);
    }

    /* drop*() Methods */

    /**
     * Drops a database.
     *
     * NOTE: You can not drop the database this SchemaManager is currently connected to.
     */
    public function dropDatabase(string $database): void
    {
        $this->_execSql($this->_platform->getDropDatabaseSQL($database));
    }

    /**
     * Drops the given table.
     */
    public function dropTable(string $tableName): void
    {
        $this->_execSql($this->_platform->getDropTableSQL($tableName));
    }

    /**
     * Drops the index from the given table.
     *
     * @param Index|string $index The name of the index.
     * @param Table|string $table The name of the table.
     */
    public function dropIndex($index, $table): void
    {
        if ($index instanceof Index) {
            $index = $index->getQuotedName($this->_platform);
        }

        $this->_execSql($this->_platform->getDropIndexSQL($index, $table));
    }

    /**
     * Drops the constraint from the given table.
     *
     * @param Table|string $table The name of the table.
     */
    public function dropConstraint(Constraint $constraint, $table): void
    {
        $this->_execSql($this->_platform->getDropConstraintSQL($constraint, $table));
    }

    /**
     * Drops a foreign key from a table.
     *
     * @param ForeignKeyConstraint|string $foreignKey The name of the foreign key.
     * @param Table|string                $table      The name of the table with the foreign key.
     */
    public function dropForeignKey($foreignKey, $table): void
    {
        $this->_execSql($this->_platform->getDropForeignKeySQL($foreignKey, $table));
    }

    /**
     * Drops a sequence with a given name.
     */
    public function dropSequence(string $name): void
    {
        $this->_execSql($this->_platform->getDropSequenceSQL($name));
    }

    /**
     * Drops a view.
     */
    public function dropView(string $name): void
    {
        $this->_execSql($this->_platform->getDropViewSQL($name));
    }

    /* create*() Methods */

    /**
     * Creates a new database.
     */
    public function createDatabase(string $database): void
    {
        $this->_execSql($this->_platform->getCreateDatabaseSQL($database));
    }

    /**
     * Creates a new table.
     */
    public function createTable(Table $table): void
    {
        $createFlags = AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS;
        $this->_execSql($this->_platform->getCreateTableSQL($table, $createFlags));
    }

    /**
     * Creates a new sequence.
     *
     * @throws ConnectionException If something fails at database level.
     */
    public function createSequence(Sequence $sequence): void
    {
        $this->_execSql($this->_platform->getCreateSequenceSQL($sequence));
    }

    /**
     * Creates a constraint on a table.
     *
     * @param Table|string $table
     */
    public function createConstraint(Constraint $constraint, $table): void
    {
        $this->_execSql($this->_platform->getCreateConstraintSQL($constraint, $table));
    }

    /**
     * Creates a new index on a table.
     *
     * @param Table|string $table The name of the table on which the index is to be created.
     */
    public function createIndex(Index $index, $table): void
    {
        $this->_execSql($this->_platform->getCreateIndexSQL($index, $table));
    }

    /**
     * Creates a new foreign key.
     *
     * @param ForeignKeyConstraint $foreignKey The ForeignKey instance.
     * @param Table|string         $table      The name of the table on which the foreign key is to be created.
     */
    public function createForeignKey(ForeignKeyConstraint $foreignKey, $table): void
    {
        $this->_execSql($this->_platform->getCreateForeignKeySQL($foreignKey, $table));
    }

    /**
     * Creates a new view.
     */
    public function createView(View $view): void
    {
        $this->_execSql($this->_platform->getCreateViewSQL($view->getQuotedName($this->_platform), $view->getSql()));
    }

    /* dropAndCreate*() Methods */

    /**
     * Drops and creates a constraint.
     *
     * @see dropConstraint()
     * @see createConstraint()
     *
     * @param Table|string $table
     */
    public function dropAndCreateConstraint(Constraint $constraint, $table): void
    {
        $this->tryMethod('dropConstraint', $constraint, $table);
        $this->createConstraint($constraint, $table);
    }

    /**
     * Drops and creates a new index on a table.
     *
     * @param Table|string $table The name of the table on which the index is to be created.
     */
    public function dropAndCreateIndex(Index $index, $table): void
    {
        $this->tryMethod('dropIndex', $index->getQuotedName($this->_platform), $table);
        $this->createIndex($index, $table);
    }

    /**
     * Drops and creates a new foreign key.
     *
     * @param ForeignKeyConstraint $foreignKey An associative array that defines properties of the foreign key to be created.
     * @param Table|string         $table      The name of the table on which the foreign key is to be created.
     */
    public function dropAndCreateForeignKey(ForeignKeyConstraint $foreignKey, $table): void
    {
        $this->tryMethod('dropForeignKey', $foreignKey, $table);
        $this->createForeignKey($foreignKey, $table);
    }

    /**
     * Drops and create a new sequence.
     *
     * @throws ConnectionException If something fails at database level.
     */
    public function dropAndCreateSequence(Sequence $sequence): void
    {
        $this->tryMethod('dropSequence', $sequence->getQuotedName($this->_platform));
        $this->createSequence($sequence);
    }

    /**
     * Drops and creates a new table.
     */
    public function dropAndCreateTable(Table $table): void
    {
        $this->tryMethod('dropTable', $table->getQuotedName($this->_platform));
        $this->createTable($table);
    }

    /**
     * Drops and creates a new database.
     */
    public function dropAndCreateDatabase(string $database): void
    {
        $this->tryMethod('dropDatabase', $database);
        $this->createDatabase($database);
    }

    /**
     * Drops and creates a new view.
     */
    public function dropAndCreateView(View $view): void
    {
        $this->tryMethod('dropView', $view->getQuotedName($this->_platform));
        $this->createView($view);
    }

    /* alterTable() Methods */

    /**
     * Alters an existing tables schema.
     */
    public function alterTable(TableDiff $tableDiff): void
    {
        $queries = $this->_platform->getAlterTableSQL($tableDiff);

        foreach ($queries as $ddlQuery) {
            $this->_execSql($ddlQuery);
        }
    }

    /**
     * Renames a given table to another name.
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
     * Converts a list of namespace names from the native DBMS data definition to a portable Doctrine definition.
     *
     * @param array<int, array<string, mixed>> $namespaces The list of namespace names in the native DBMS data definition.
     *
     * @return array<int, string>
     */
    protected function getPortableNamespacesList(array $namespaces): array
    {
        $namespacesList = [];

        foreach ($namespaces as $namespace) {
            $namespacesList[] = $this->getPortableNamespaceDefinition($namespace);
        }

        return $namespacesList;
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
     * Converts a namespace definition from the native DBMS data definition to a portable Doctrine definition.
     *
     * @param array<string, mixed> $namespace The native DBMS namespace definition.
     */
    protected function getPortableNamespaceDefinition(array $namespace): string
    {
        return array_shift($namespace);
    }

    /**
     * @param array<int, array<string, mixed>> $sequences
     *
     * @return array<int, Sequence>
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
     * @throws DBALException
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
     */
    abstract protected function _getPortableTableColumnDefinition(array $tableColumn): Column;

    /**
     * Aggregates and groups the index results according to the required data result.
     *
     * @param array<int, array<string, mixed>> $tableIndexRows
     *
     * @return array<string, Index>
     */
    protected function _getPortableTableIndexesList(array $tableIndexRows, string $tableName): array
    {
        $result = [];
        foreach ($tableIndexRows as $tableIndex) {
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
                $index = new Index($data['name'], $data['columns'], $data['unique'], $data['primary'], $data['flags'], $data['options']);
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
     * @param array<int, array<string, mixed>> $users
     *
     * @return array<int, array<string, mixed>>
     */
    protected function _getPortableUsersList(array $users): array
    {
        $list = [];
        foreach ($users as $value) {
            $list[] = $this->_getPortableUserDefinition($value);
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    protected function _getPortableUserDefinition(array $user): array
    {
        return $user;
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
     */
    protected function _execSql($sql): void
    {
        foreach ((array) $sql as $query) {
            $this->_conn->executeUpdate($query);
        }
    }

    /**
     * Creates a schema instance for the current database.
     */
    public function createSchema(): Schema
    {
        $namespaces = [];

        if ($this->_platform->supportsSchemas()) {
            $namespaces = $this->listNamespaceNames();
        }

        $sequences = [];

        if ($this->_platform->supportsSequences()) {
            $sequences = $this->listSequences();
        }

        $tables = $this->listTables();

        return new Schema($tables, $sequences, $this->createSchemaConfig(), $namespaces);
    }

    /**
     * Creates the configuration for this schema.
     */
    public function createSchemaConfig(): SchemaConfig
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setMaxIdentifierLength($this->_platform->getMaxIdentifierLength());

        $searchPaths = $this->getSchemaSearchPaths();
        if (isset($searchPaths[0])) {
            $schemaConfig->setName($searchPaths[0]);
        }

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
     * The search path for namespaces in the currently connected database.
     *
     * The first entry is usually the default namespace in the Schema. All
     * further namespaces contain tables/sequences which can also be addressed
     * with a short, not full-qualified name.
     *
     * For databases that don't support subschema/namespaces this method
     * returns the name of the currently connected database.
     *
     * @return array<int, string>
     */
    public function getSchemaSearchPaths(): array
    {
        $database = $this->_conn->getDatabase();

        if ($database !== null) {
            return [$database];
        }

        return [];
    }

    /**
     * Given a table comment this method tries to extract a type hint for Doctrine Type. If the type hint is found,
     * it's removed from the comment.
     *
     * @return string|null The extracted Doctrine type or NULL of the type hint was not found.
     */
    final protected function extractDoctrineTypeFromComment(?string &$comment): ?string
    {
        if ($comment === null || preg_match('/(.*)\(DC2Type:(((?!\)).)+)\)(.*)/', $comment, $match) === 0) {
            return null;
        }

        $comment = $match[1] . $match[4];

        return $match[2];
    }

    /**
     * @throws DatabaseRequired
     */
    private function ensureDatabase(?string $database, string $methodName): string
    {
        if ($database === null) {
            throw DatabaseRequired::new($methodName);
        }

        return $database;
    }
}
