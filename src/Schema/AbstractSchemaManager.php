<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs;
use Doctrine\DBAL\Event\SchemaIndexDefinitionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseRequired;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Doctrine\Deprecations\Deprecation;
use Throwable;

use function array_filter;
use function array_intersect;
use function array_map;
use function array_shift;
use function array_values;
use function assert;
use function call_user_func_array;
use function count;
use function func_get_args;
use function is_callable;
use function is_string;
use function preg_match;
use function str_replace;
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
     *
     * @var Connection
     */
    protected $_conn;

    /**
     * Holds instance of the database platform used for this schema manager.
     *
     * @var T
     */
    protected $_platform;

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
    public function getDatabasePlatform()
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
     * @deprecated
     *
     * @return mixed
     */
    public function tryMethod()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4897',
            'AbstractSchemaManager::tryMethod() is deprecated.'
        );

        $args   = func_get_args();
        $method = $args[0];
        unset($args[0]);
        $args = array_values($args);

        $callback = [$this, $method];
        assert(is_callable($callback));

        try {
            return call_user_func_array($callback, $args);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Lists the available databases for this connection.
     *
     * @return string[]
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
     * Returns a list of all namespaces in the current database.
     *
     * @deprecated Use {@see listSchemaNames()} instead.
     *
     * @return string[]
     *
     * @throws Exception
     */
    public function listNamespaceNames(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4503',
            'AbstractSchemaManager::listNamespaceNames() is deprecated,'
                . ' use AbstractSchemaManager::listSchemaNames() instead.'
        );

        $sql = $this->_platform->getListNamespacesSQL();

        $namespaces = $this->_conn->fetchAllAssociative($sql);

        return $this->getPortableNamespacesList($namespaces);
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
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Lists the available sequences for this connection.
     *
     * @param string|null $database
     *
     * @return Sequence[]
     *
     * @throws Exception
     */
    public function listSequences($database = null): array
    {
        if ($database === null) {
            $database = $this->getDatabase(__METHOD__);
        } else {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/5284',
                'Passing $database to AbstractSchemaManager::listSequences() is deprecated.'
            );
        }

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
     * @param string      $table    The name of the table.
     * @param string|null $database
     *
     * @return Column[]
     *
     * @throws Exception
     */
    public function listTableColumns($table, $database = null): array
    {
        if ($database === null) {
            $database = $this->getDatabase(__METHOD__);
        } else {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/5284',
                'Passing $database to AbstractSchemaManager::listTableColumns() is deprecated.'
            );
        }

        $sql = $this->_platform->getListTableColumnsSQL($table, $database);

        $tableColumns = $this->_conn->fetchAllAssociative($sql);

        return $this->_getPortableTableColumnList($table, $database, $tableColumns);
    }

    /**
     * @param string      $table
     * @param string|null $database
     *
     * @return Column[]
     *
     * @throws Exception
     */
    protected function doListTableColumns($table, $database = null): array
    {
        if ($database === null) {
            $database = $this->getDatabase(__METHOD__);
        } else {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/5284',
                'Passing $database to AbstractSchemaManager::listTableColumns() is deprecated.'
            );
        }

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
     * @param string $table The name of the table.
     *
     * @return Index[]
     *
     * @throws Exception
     */
    public function listTableIndexes($table): array
    {
        $sql = $this->_platform->getListTableIndexesSQL($table, $this->_conn->getDatabase());

        $tableIndexes = $this->_conn->fetchAllAssociative($sql);

        return $this->_getPortableTableIndexesList($tableIndexes, $table);
    }

    /**
     * @param string $table
     *
     * @return Index[]
     *
     * @throws Exception
     */
    protected function doListTableIndexes($table): array
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
     * The usage of a string $tableNames is deprecated. Pass a one-element array instead.
     *
     * @param string|string[] $names
     *
     * @throws Exception
     */
    public function tablesExist($names): bool
    {
        if (is_string($names)) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/3580',
                'The usage of a string $tableNames in AbstractSchemaManager::tablesExist() is deprecated. ' .
                'Pass a one-element array instead.'
            );
        }

        $names = array_map('strtolower', (array) $names);

        return count($names) === count(array_intersect($names, array_map('strtolower', $this->listTableNames())));
    }

    /**
     * Returns a list of all tables in the current database.
     *
     * @return string[]
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
     * @param mixed[] $assetNames
     *
     * @return mixed[]
     */
    protected function filterAssetNames($assetNames): array
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
     * @return Table[]
     *
     * @throws Exception
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

    /**
     * @return list<Table>
     *
     * @throws Exception
     */
    protected function doListTables(): array
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
     * @param string $name
     *
     * @throws Exception
     */
    public function listTableDetails($name): Table
    {
        $columns     = $this->listTableColumns($name);
        $foreignKeys = [];

        if ($this->_platform->supportsForeignKeyConstraints()) {
            $foreignKeys = $this->listTableForeignKeys($name);
        }

        $indexes = $this->listTableIndexes($name);

        return new Table($name, $columns, $indexes, [], $foreignKeys);
    }

    /**
     * @param string $name
     *
     * @throws Exception
     */
    protected function doListTableDetails($name): Table
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
            $this->listTableColumns($name, $database),
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
     *
     * @abstract
     */
    protected function selectDatabaseColumns(string $databaseName, ?string $tableName = null): Result
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Selects index definitions of the tables in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * @throws Exception
     */
    protected function selectDatabaseIndexes(string $databaseName, ?string $tableName = null): Result
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Selects foreign key definitions of the tables in the specified database. If the table name is specified,
     * narrows down the selection to this table.
     *
     * @throws Exception
     */
    protected function selectDatabaseForeignKeys(string $databaseName, ?string $tableName = null): Result
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Returns table options for the tables in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * @return array<string,array<string,mixed>>
     *
     * @throws Exception
     */
    protected function getDatabaseTableOptions(string $databaseName, ?string $tableName = null): array
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Lists the views this connection has.
     *
     * @return View[]
     *
     * @throws Exception
     */
    public function listViews(): array
    {
        $database = $this->_conn->getDatabase();
        $sql      = $this->_platform->getListViewsSQL($database);
        $views    = $this->_conn->fetchAllAssociative($sql);

        return $this->_getPortableViewsList($views);
    }

    /**
     * Lists the foreign keys for the given table.
     *
     * @param string      $table    The name of the table.
     * @param string|null $database
     *
     * @return ForeignKeyConstraint[]
     *
     * @throws Exception
     */
    public function listTableForeignKeys($table, $database = null): array
    {
        if ($database === null) {
            $database = $this->getDatabase(__METHOD__);
        } else {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/5284',
                'Passing $database to AbstractSchemaManager::listTableForeignKeys() is deprecated.'
            );
        }

        $sql              = $this->_platform->getListTableForeignKeysSQL($table, $database);
        $tableForeignKeys = $this->_conn->fetchAllAssociative($sql);

        return $this->_getPortableTableForeignKeysList($tableForeignKeys);
    }

    /**
     * @param string      $table
     * @param string|null $database
     *
     * @return ForeignKeyConstraint[]
     *
     * @throws Exception
     */
    protected function doListTableForeignKeys($table, $database = null): array
    {
        if ($database === null) {
            $database = $this->getDatabase(__METHOD__);
        } else {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/5284',
                'Passing $database to AbstractSchemaManager::listTableForeignKeys() is deprecated.'
            );
        }

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
     * @param string $database The name of the database to drop.
     *
     * @throws Exception
     */
    public function dropDatabase($database): void
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
     * @param string $name The name of the table to drop.
     *
     * @throws Exception
     */
    public function dropTable($name): void
    {
        $this->_execSql($this->_platform->getDropTableSQL($name));
    }

    /**
     * Drops the index from the given table.
     *
     * @param Index|string $index The name of the index.
     * @param Table|string $table The name of the table.
     *
     * @throws Exception
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
     * @deprecated Use {@see dropIndex()}, {@see dropForeignKey()} or {@see dropUniqueConstraint()} instead.
     *
     * @param Table|string $table The name of the table.
     *
     * @throws Exception
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
     *
     * @throws Exception
     */
    public function dropForeignKey($foreignKey, $table): void
    {
        $this->_execSql($this->_platform->getDropForeignKeySQL($foreignKey, $table));
    }

    /**
     * Drops a sequence with a given name.
     *
     * @param string $name The name of the sequence to drop.
     *
     * @throws Exception
     */
    public function dropSequence($name): void
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
     * @param string $name The name of the view.
     *
     * @throws Exception
     */
    public function dropView($name): void
    {
        $this->_execSql($this->_platform->getDropViewSQL($name));
    }

    /* create*() Methods */

    /**
     * Creates a new database.
     *
     * @param string $database The name of the database to create.
     *
     * @throws Exception
     */
    public function createDatabase($database): void
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
     * @param Sequence $sequence
     *
     * @throws Exception
     */
    public function createSequence($sequence): void
    {
        $this->_execSql($this->_platform->getCreateSequenceSQL($sequence));
    }

    /**
     * Creates a constraint on a table.
     *
     * @deprecated Use {@see createIndex()}, {@see createForeignKey()} or {@see createUniqueConstraint()} instead.
     *
     * @param Table|string $table
     *
     * @throws Exception
     */
    public function createConstraint(Constraint $constraint, $table): void
    {
        $this->_execSql($this->_platform->getCreateConstraintSQL($constraint, $table));
    }

    /**
     * Creates a new index on a table.
     *
     * @param Table|string $table The name of the table on which the index is to be created.
     *
     * @throws Exception
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
     *
     * @throws Exception
     */
    public function createForeignKey(ForeignKeyConstraint $foreignKey, $table): void
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

    /* dropAndCreate*() Methods */

    /**
     * Drops and creates a constraint.
     *
     * @deprecated Use {@see dropIndex()} and {@see createIndex()},
     *             {@see dropForeignKey()} and {@see createForeignKey()}
     *             or {@see dropUniqueConstraint()} and {@see createUniqueConstraint()} instead.
     *
     * @see dropConstraint()
     * @see createConstraint()
     *
     * @param Table|string $table
     *
     * @throws Exception
     */
    public function dropAndCreateConstraint(Constraint $constraint, $table): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4897',
            'AbstractSchemaManager::dropAndCreateConstraint() is deprecated.'
                . ' Use AbstractSchemaManager::dropIndex() and AbstractSchemaManager::createIndex(),'
                . ' AbstractSchemaManager::dropForeignKey() and AbstractSchemaManager::createForeignKey()'
                . ' or AbstractSchemaManager::dropUniqueConstraint()'
                . ' and AbstractSchemaManager::createUniqueConstraint() instead.'
        );

        $this->tryMethod('dropConstraint', $constraint, $table);
        $this->createConstraint($constraint, $table);
    }

    /**
     * Drops and creates a new index on a table.
     *
     * @deprecated Use {@see dropIndex()} and {@see createIndex()} instead.
     *
     * @param Table|string $table The name of the table on which the index is to be created.
     *
     * @throws Exception
     */
    public function dropAndCreateIndex(Index $index, $table): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4897',
            'AbstractSchemaManager::dropAndCreateIndex() is deprecated.'
            . ' Use AbstractSchemaManager::dropIndex() and AbstractSchemaManager::createIndex() instead.'
        );

        $this->tryMethod('dropIndex', $index->getQuotedName($this->_platform), $table);
        $this->createIndex($index, $table);
    }

    /**
     * Drops and creates a new foreign key.
     *
     * @deprecated Use {@see dropForeignKey()} and {@see createForeignKey()} instead.
     *
     * @param ForeignKeyConstraint $foreignKey An associative array that defines properties
     *                                         of the foreign key to be created.
     * @param Table|string         $table      The name of the table on which the foreign key is to be created.
     *
     * @throws Exception
     */
    public function dropAndCreateForeignKey(ForeignKeyConstraint $foreignKey, $table): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4897',
            'AbstractSchemaManager::dropAndCreateForeignKey() is deprecated.'
            . ' Use AbstractSchemaManager::dropForeignKey() and AbstractSchemaManager::createForeignKey() instead.'
        );

        $this->tryMethod('dropForeignKey', $foreignKey, $table);
        $this->createForeignKey($foreignKey, $table);
    }

    /**
     * Drops and create a new sequence.
     *
     * @deprecated Use {@see dropSequence()} and {@see createSequence()} instead.
     *
     * @throws Exception
     */
    public function dropAndCreateSequence(Sequence $sequence): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4897',
            'AbstractSchemaManager::dropAndCreateSequence() is deprecated.'
            . ' Use AbstractSchemaManager::dropSequence() and AbstractSchemaManager::createSequence() instead.'
        );

        $this->tryMethod('dropSequence', $sequence->getQuotedName($this->_platform));
        $this->createSequence($sequence);
    }

    /**
     * Drops and creates a new table.
     *
     * @deprecated Use {@see dropTable()} and {@see createTable()} instead.
     *
     * @throws Exception
     */
    public function dropAndCreateTable(Table $table): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4897',
            'AbstractSchemaManager::dropAndCreateTable() is deprecated.'
            . ' Use AbstractSchemaManager::dropTable() and AbstractSchemaManager::createTable() instead.'
        );

        $this->tryMethod('dropTable', $table->getQuotedName($this->_platform));
        $this->createTable($table);
    }

    /**
     * Drops and creates a new database.
     *
     * @deprecated Use {@see dropDatabase()} and {@see createDatabase()} instead.
     *
     * @param string $database The name of the database to create.
     *
     * @throws Exception
     */
    public function dropAndCreateDatabase($database): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4897',
            'AbstractSchemaManager::dropAndCreateDatabase() is deprecated.'
            . ' Use AbstractSchemaManager::dropDatabase() and AbstractSchemaManager::createDatabase() instead.'
        );

        $this->tryMethod('dropDatabase', $database);
        $this->createDatabase($database);
    }

    /**
     * Drops and creates a new view.
     *
     * @deprecated Use {@see dropView()} and {@see createView()} instead.
     *
     * @throws Exception
     */
    public function dropAndCreateView(View $view): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4897',
            'AbstractSchemaManager::dropAndCreateView() is deprecated.'
            . ' Use AbstractSchemaManager::dropView() and AbstractSchemaManager::createView() instead.'
        );

        $this->tryMethod('dropView', $view->getQuotedName($this->_platform));
        $this->createView($view);
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
     * @param string $name    The current name of the table.
     * @param string $newName The new name of the table.
     *
     * @throws Exception
     */
    public function renameTable($name, $newName): void
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
     * @param mixed[] $databases
     *
     * @return string[]
     */
    protected function _getPortableDatabasesList($databases): array
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
     * @deprecated Use {@see listSchemaNames()} instead.
     *
     * @param array<int, array<string, mixed>> $namespaces The list of namespace names
     *                                                     in the native DBMS data definition.
     *
     * @return string[]
     */
    protected function getPortableNamespacesList(array $namespaces): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4503',
            'AbstractSchemaManager::getPortableNamespacesList() is deprecated,'
                . ' use AbstractSchemaManager::listSchemaNames() instead.'
        );

        $namespacesList = [];

        foreach ($namespaces as $namespace) {
            $namespacesList[] = $this->getPortableNamespaceDefinition($namespace);
        }

        return $namespacesList;
    }

    /**
     * @param mixed $database
     *
     * @return mixed
     */
    protected function _getPortableDatabaseDefinition($database)
    {
        return $database;
    }

    /**
     * Converts a namespace definition from the native DBMS data definition to a portable Doctrine definition.
     *
     * @deprecated Use {@see listSchemaNames()} instead.
     *
     * @param array<string, mixed> $namespace The native DBMS namespace definition.
     *
     * @return mixed
     */
    protected function getPortableNamespaceDefinition(array $namespace)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4503',
            'AbstractSchemaManager::getPortableNamespaceDefinition() is deprecated,'
                . ' use AbstractSchemaManager::listSchemaNames() instead.'
        );

        return $namespace;
    }

    /**
     * @param mixed[][] $sequences
     *
     * @return Sequence[]
     *
     * @throws Exception
     */
    protected function _getPortableSequencesList($sequences): array
    {
        $list = [];

        foreach ($sequences as $value) {
            $list[] = $this->_getPortableSequenceDefinition($value);
        }

        return $list;
    }

    /**
     * @param mixed[] $sequence
     *
     * @throws Exception
     */
    protected function _getPortableSequenceDefinition($sequence): Sequence
    {
        throw Exception::notSupported('Sequences');
    }

    /**
     * Independent of the database the keys of the column list result are lowercased.
     *
     * The name of the created column instance however is kept in its case.
     *
     * @param string    $table        The name of the table.
     * @param string    $database
     * @param mixed[][] $tableColumns
     *
     * @return Column[]
     *
     * @throws Exception
     */
    protected function _getPortableTableColumnList($table, $database, $tableColumns): array
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
     * @param mixed[] $tableColumn
     *
     * @throws Exception
     */
    abstract protected function _getPortableTableColumnDefinition($tableColumn): Column;

    /**
     * Aggregates and groups the index results according to the required data result.
     *
     * @param mixed[][]   $tableIndexes
     * @param string|null $tableName
     *
     * @return Index[]
     *
     * @throws Exception
     */
    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null): array
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
     * @param mixed[][] $tables
     *
     * @return string[]
     */
    protected function _getPortableTablesList($tables): array
    {
        $list = [];
        foreach ($tables as $value) {
            $list[] = $this->_getPortableTableDefinition($value);
        }

        return $list;
    }

    /**
     * @param mixed $table
     */
    protected function _getPortableTableDefinition($table): string
    {
        return $table;
    }

    /**
     * @param mixed[][] $views
     *
     * @return View[]
     */
    protected function _getPortableViewsList($views): array
    {
        $list = [];
        foreach ($views as $value) {
            $view = $this->_getPortableViewDefinition($value);

            if ($view === false) {
                continue;
            }

            $viewName        = strtolower($view->getQuotedName($this->_platform));
            $list[$viewName] = $view;
        }

        return $list;
    }

    /**
     * @param mixed[] $view
     *
     * @return View|false
     */
    protected function _getPortableViewDefinition($view)
    {
        return false;
    }

    /**
     * @param mixed[][] $tableForeignKeys
     *
     * @return ForeignKeyConstraint[]
     */
    protected function _getPortableTableForeignKeysList($tableForeignKeys): array
    {
        $list = [];

        foreach ($tableForeignKeys as $value) {
            $list[] = $this->_getPortableTableForeignKeyDefinition($value);
        }

        return $list;
    }

    /**
     * @param mixed $tableForeignKey
     */
    protected function _getPortableTableForeignKeyDefinition($tableForeignKey): ForeignKeyConstraint
    {
        return $tableForeignKey;
    }

    /**
     * @param string[]|string $sql
     *
     * @throws Exception
     */
    protected function _execSql($sql): void
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
            $schemaNames = $this->listNamespaceNames();
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
     * @deprecated
     *
     * @return string[]
     *
     * @throws Exception
     */
    public function getSchemaSearchPaths(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4821',
            'AbstractSchemaManager::getSchemaSearchPaths() is deprecated.'
        );

        $database = $this->_conn->getDatabase();

        if ($database !== null) {
            return [$database];
        }

        return [];
    }

    /**
     * Given a table comment this method tries to extract a typehint for Doctrine Type, or returns
     * the type given as default.
     *
     * @internal This method should be only used from within the AbstractSchemaManager class hierarchy.
     *
     * @param string|null $comment
     * @param string      $currentType
     */
    public function extractDoctrineTypeFromComment($comment, $currentType): string
    {
        if ($comment !== null && preg_match('(\(DC2Type:(((?!\)).)+)\))', $comment, $match) === 1) {
            return $match[1];
        }

        return $currentType;
    }

    /**
     * @internal This method should be only used from within the AbstractSchemaManager class hierarchy.
     *
     * @param string|null $comment
     * @param string|null $type
     */
    public function removeDoctrineTypeFromComment($comment, $type): ?string
    {
        if ($comment === null) {
            return null;
        }

        return str_replace('(DC2Type:' . $type . ')', '', $comment);
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
