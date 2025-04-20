<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseRequired;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Doctrine\DBAL\Schema\Exception\UnsupportedName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\Exception\TypesException;

use function array_change_key_case;
use function array_filter;
use function array_intersect;
use function array_map;
use function array_values;
use function assert;
use function count;
use function func_get_arg;
use function func_num_args;
use function strtolower;

/**
 * Base class for schema managers. Schema managers are used to inspect and/or
 * modify the database schema/structure.
 *
 * @template-covariant T of AbstractPlatform
 */
abstract class AbstractSchemaManager
{
    /**
     * The value representing the <code>NULL</code> schema key. Should be populated only by the schema managers
     * corresponding to the database platforms that don't support schemas.
     */
    protected const NULL_SCHEMA_KEY = "\x00";

    /**
     * The name of the column of the schema introspection result set that holds the schema name, if the underlying
     * platform supports schemas.
     */
    protected const SCHEMA_NAME_COLUMN = 'SCHEMA_NAME';

    /**
     * The name of the column of the schema introspection result set that holds the table name.
     */
    protected const TABLE_NAME_COLUMN = 'TABLE_NAME';

    /**
     * The current schema name determined from the connection. The <code>null</code> value means that there is no
     * schema currently selected within the connection.
     *
     * The property should be accessed only when {@link $currentSchemaDetermined} is set to <code>true</code>. If the
     * currently used database platform doesn't support schemas, the property will remain uninitialized.
     *
     * The property is initialized only once. If the underlying connection switches to a different schema, a new schema
     * manager instance will have to be created to reflect this change.
     *
     * @var ?non-empty-string
     */
    private ?string $currentSchemaName;

    /**
     * Indicates whether the current schema has been determined.
     */
    private bool $currentSchemaDetermined = false;

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
    public function listTableColumns(string $tableName): array
    {
        return $this->_getPortableTableColumnList(
            $this->fetchTableColumns(
                $this->getDatabase(__METHOD__),
                $this->parseOptionallyQualifiedName($tableName),
            ),
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
    public function listTableIndexes(string $tableName): array
    {
        return $this->_getPortableTableIndexesList(
            $this->fetchIndexColumns(
                $this->getDatabase(__METHOD__),
                $this->parseOptionallyQualifiedName($tableName),
            ),
        );
    }

    /**
     * Returns the primary key constraint definition for a given table.
     *
     * @throws Exception
     */
    public function getTablePrimaryKeyConstraint(string $tableName): ?PrimaryKeyConstraint
    {
        return $this->parsePrimaryKeyConstraint(
            $this->fetchPrimaryKeyConstraintColumns(
                $this->getDatabase(__METHOD__),
                $this->parseOptionallyQualifiedName($tableName),
            ),
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

    /** @throws Exception */
    public function tableExists(string $tableName): bool
    {
        return $this->tablesExist([$tableName]);
    }

    /**
     * Returns a list of all tables in the current database.
     *
     * @return array<int, non-empty-string>
     *
     * @throws Exception
     */
    public function listTableNames(): array
    {
        $supportsSchemas   = $this->platform->supportsSchemas();
        $currentSchemaName = $this->getCurrentSchemaName();

        return $this->filterAssetNames(
            array_map(static function (array $row) use ($supportsSchemas, $currentSchemaName): string {
                $name = $row[self::TABLE_NAME_COLUMN];

                if ($supportsSchemas && $row[self::SCHEMA_NAME_COLUMN] !== $currentSchemaName) {
                    $name = $row[self::SCHEMA_NAME_COLUMN] . '.' . $name;
                }

                return $name;
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
        $primaryKeyColumnsByTable = $this->fetchPrimaryKeyConstraintColumnsByTable($database);
        $tableOptionsByTable      = $this->fetchTableOptionsByTable($database);

        $currentSchemaName = $this->getCurrentSchemaName();

        $filter = $this->connection->getConfiguration()->getSchemaAssetsFilter();
        $tables = [];

        $configuration = $this->createSchemaConfig()
            ->toTableConfiguration();

        foreach ($tableColumnsByTable as $schemaNameKey => $schemaTables) {
            if ($schemaNameKey !== self::NULL_SCHEMA_KEY && $schemaNameKey !== $currentSchemaName) {
                $qualifier = $schemaNameKey;
                $prefix    = $schemaNameKey . '.';
            } else {
                $qualifier = null;
                $prefix    = '';
            }

            foreach ($schemaTables as $unqualifiedName => $tableColumns) {
                if (! $filter($prefix . $unqualifiedName)) {
                    continue;
                }

                $editor = Table::editor()
                    ->setName(
                        OptionallyQualifiedName::quoted($unqualifiedName, $qualifier),
                    )
                    ->setColumns($this->_getPortableTableColumnList($tableColumns))
                    ->setIndexes(
                        $this->_getPortableTableIndexesList(
                            $indexColumnsByTable[$schemaNameKey][$unqualifiedName] ?? [],
                        ),
                    );

                if (isset($primaryKeyColumnsByTable[$schemaNameKey][$unqualifiedName])) {
                    $editor->setPrimaryKeyConstraint(
                        $this->parsePrimaryKeyConstraint(
                            $primaryKeyColumnsByTable[$schemaNameKey][$unqualifiedName],
                        ),
                    );
                }

                if (isset($foreignKeyColumnsByTable[$schemaNameKey][$unqualifiedName])) {
                    $editor->setForeignKeyConstraints(
                        $this->_getPortableTableForeignKeysList(
                            $foreignKeyColumnsByTable[$schemaNameKey][$unqualifiedName],
                        ),
                    );
                }

                if (isset($tableOptionsByTable[$schemaNameKey][$unqualifiedName])) {
                    $editor->setOptions($tableOptionsByTable[$schemaNameKey][$unqualifiedName]);
                }

                $tables[] = $editor
                    ->setConfiguration($configuration)
                    ->create();
            }
        }

        return $tables;
    }

    /**
     * Returns the current schema name used by the schema manager connection.
     *
     * The <code>null</code> value means that there is no schema currently selected within the connection or the
     * corresponding database platform doesn't support schemas.
     *
     * @return ?non-empty-string
     *
     * @throws Exception
     */
    final protected function getCurrentSchemaName(): ?string
    {
        if (! $this->platform->supportsSchemas()) {
            return null;
        }

        if (! $this->currentSchemaDetermined) {
            $this->currentSchemaName       = $this->determineCurrentSchemaName();
            $this->currentSchemaDetermined = true;
        }

        return $this->currentSchemaName;
    }

    /**
     * Determines the name of the current schema.
     *
     * If the corresponding database platform supports schemas, the schema manager must implement this method.
     *
     * @return ?non-empty-string
     *
     * @throws Exception
     */
    protected function determineCurrentSchemaName(): ?string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Selects names of tables in the specified database.
     *
     * Implementations must project the table name as quoted {@see TABLE_NAME_COLUMN}. If the corresponding
     * database platform supports schemas, the schema name must be projected as quoted {@see SCHEMA_NAME_COLUMN}.
     *
     * @throws Exception
     */
    abstract protected function selectTableNames(string $databaseName): Result;

    /**
     * Selects definitions of table columns in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * Implementations must project the name of the table the column belongs to as quoted {@see TABLE_NAME_COLUMN}.
     * If the corresponding database platform supports schemas, the schema name of the column's table should be
     * projected as quoted {@see SCHEMA_NAME_COLUMN}.
     *
     * @throws Exception
     */
    abstract protected function selectTableColumns(
        string $databaseName,
        ?OptionallyQualifiedName $tableName = null,
    ): Result;

    /**
     * Selects definitions of index columns in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * Implementations must project the name of the table the index belongs to as quoted {@see TABLE_NAME_COLUMN}.
     * If the corresponding database platform supports schemas, the schema name of the indexes table should be
     * projected as quoted {@see SCHEMA_NAME_COLUMN}.
     *
     * @throws Exception
     */
    abstract protected function selectIndexColumns(
        string $databaseName,
        ?OptionallyQualifiedName $tableName = null,
    ): Result;

    /**
     * Selects definitions of foreign key columns in the specified database. If the table name is specified,
     * narrows down the selection to this table.
     *
     * Implementations must project the name of the referencing table of the constraint as quoted
     * {@see TABLE_NAME_COLUMN}. If the corresponding database platform supports schemas, the schema name of the
     * referencing table should be projected as quoted {@see SCHEMA_NAME_COLUMN}.
     *
     * @throws Exception
     */
    abstract protected function selectForeignKeyColumns(
        string $databaseName,
        ?OptionallyQualifiedName $tableName = null,
    ): Result;

    /**
     * Fetches definitions of table columns in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    protected function fetchTableColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): array
    {
        return $this->selectTableColumns($databaseName, $tableName)->fetchAllAssociative();
    }

    /**
     * Fetches definitions of index columns in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    protected function fetchIndexColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): array
    {
        return $this->selectIndexColumns($databaseName, $tableName)->fetchAllAssociative();
    }

    /**
     * Fetches definitions of primary key columns in the specified database. If the table name is specified, narrows
     * down the selection to this table.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    protected function fetchPrimaryKeyConstraintColumns(
        string $databaseName,
        ?OptionallyQualifiedName $tableName = null,
    ): array {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Fetches definitions of foreign key columns in the specified database. If the table name is specified,
     * narrows down the selection to this table.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    protected function fetchForeignKeyColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): array
    {
        return $this->selectForeignKeyColumns($databaseName, $tableName)->fetchAllAssociative();
    }

    /**
     * Fetches definitions of table columns in the specified database and returns them grouped by schema name and table
     * name.
     *
     * If the corresponding database platform doesn't support schemas, the schema name key will be the
     * {@see NULL_SCHEMA_KEY}.
     *
     * @return array<non-empty-string,array<non-empty-string,list<array<string,mixed>>>>
     *
     * @throws Exception
     */
    protected function fetchTableColumnsByTable(string $databaseName): array
    {
        return $this->groupByTable($this->fetchTableColumns($databaseName));
    }

    /**
     * Fetches definitions of index columns in the specified database and returns them grouped by schema name and table
     * name.
     *
     * If the corresponding database platform doesn't support schemas, the schema name key will be the
     * {@see NULL_SCHEMA_KEY}.
     *
     * @return array<non-empty-string,array<non-empty-string,list<array<string,mixed>>>>
     *
     * @throws Exception
     */
    protected function fetchIndexColumnsByTable(string $databaseName): array
    {
        return $this->groupByTable($this->fetchIndexColumns($databaseName));
    }

    /**
     * Fetches definitions of primary key constraint columns in the specified database and returns them grouped by
     * schema name and table name.
     *
     * If the corresponding database platform doesn't support schemas, the schema name key will be the
     * {@see NULL_SCHEMA_KEY}.
     *
     * @return array<non-empty-string,array<string,list<array<string,mixed>>>>
     *
     * @throws Exception
     */
    protected function fetchPrimaryKeyConstraintColumnsByTable(string $databaseName): array
    {
        return $this->groupByTable($this->fetchPrimaryKeyConstraintColumns($databaseName));
    }

    /**
     * Fetches definitions of foreign key columns in the specified database and returns them grouped by schema name and
     * table name.
     *
     * If the corresponding database platform doesn't support schemas, the schema name key will be the
     * {@see NULL_SCHEMA_KEY}.
     *
     * @return array<non-empty-string,array<non-empty-string,list<array<string,mixed>>>>
     *
     * @throws Exception
     */
    protected function fetchForeignKeyColumnsByTable(string $databaseName): array
    {
        return $this->groupByTable($this->fetchForeignKeyColumns($databaseName));
    }

    /**
     * Fetches table options for the tables in the specified database and returns them grouped by schema name and table
     * name. If the table name is specified, narrows down the selection to this table.
     *
     * If the corresponding database platform doesn't support schemas, the schema name key will be the
     * {@see NULL_SCHEMA_KEY}.
     *
     * @return array<non-empty-string,array<non-empty-string, array<string,mixed>>>
     *
     * @throws Exception
     */
    abstract protected function fetchTableOptionsByTable(
        string $databaseName,
        ?OptionallyQualifiedName $tableName = null,
    ): array;

    final protected function parseOptionallyQualifiedName(string $input): OptionallyQualifiedName
    {
        $parser = Parsers::getOptionallyQualifiedNameParser();

        try {
            return $parser->parse($input);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($input, $e);
        }
    }

    /**
     * Ensures that the given optionally qualified name is in fact unqualified.
     *
     * Schema managers for the platforms that don't support schemas, before using the unqualified part of the name,
     * should use this method to ensure that the name doesn't have a qualifier, which, if it was present, they would be
     * unable to bind to the schema introspection query.
     */
    final protected function ensureUnqualifiedName(OptionallyQualifiedName $name, string $methodName): void
    {
        if ($name->getQualifier() !== null) {
            throw UnsupportedName::fromQualifiedName($name, $methodName);
        }
    }

    /**
     * Introspects the table with the given name.
     *
     * @throws Exception
     */
    public function introspectTable(string $name): Table
    {
        $tableName = $this->parseOptionallyQualifiedName($name);

        $columns = $this->listTableColumns($name);

        if ($columns === []) {
            throw TableDoesNotExist::new($name);
        }

        return Table::editor()
            ->setName($tableName)
            ->setColumns($columns)
            ->setPrimaryKeyConstraint($this->getTablePrimaryKeyConstraint($name))
            ->setIndexes($this->listTableIndexes($name))
            ->setForeignKeyConstraints($this->listTableForeignKeys($name))
            ->setOptions($this->getTableOptions($tableName))
            ->create();
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
    public function listTableForeignKeys(string $tableName): array
    {
        return $this->_getPortableTableForeignKeysList(
            $this->fetchForeignKeyColumns(
                $this->getDatabase(__METHOD__),
                $this->parseOptionallyQualifiedName($tableName),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function getTableOptions(OptionallyQualifiedName $tableName): array
    {
        $qualifier = $tableName->getQualifier();
        $folding   = $this->platform->getUnquotedIdentifierFolding();

        if ($qualifier !== null) {
            $schemaNameKey = $qualifier->toNormalizedValue($folding);
        } else {
            $schemaNameKey = $this->getCurrentSchemaName() ?? self::NULL_SCHEMA_KEY;
        }

        $unqualifiedTableName = $tableName->getUnqualifiedName()->toNormalizedValue($folding);

        return $this->fetchTableOptionsByTable(
            $this->getDatabase(__METHOD__),
            $tableName,
        )[$schemaNameKey][$unqualifiedTableName] ?? [];
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
                $view->getObjectName()->toSQL($this->platform),
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
     * @param array<array<string, mixed>> $rows
     *
     * @return array<string, Column>
     *
     * @throws TypesException
     */
    protected function _getPortableTableColumnList(array $rows): array
    {
        $list = [];
        foreach ($rows as $row) {
            $column = $this->_getPortableTableColumnDefinition($row);

            $list[strtolower($column->getName())] = $column;
        }

        return $list;
    }

    /** @param list<array<string, mixed>> $rows */
    protected function parsePrimaryKeyConstraint(array $rows): ?PrimaryKeyConstraint
    {
        if (count($rows) < 1) {
            return null;
        }

        $constraintName = null;
        $isClustered    = true;
        $columnNames    = [];

        foreach ($rows as $i => $row) {
            $row = array_change_key_case($row);

            if ($i === 0) {
                $constraintName = $row['constraint_name'];

                if (isset($row['is_clustered'])) {
                    $isClustered = $row['is_clustered'];
                }
            } else {
                assert($row['constraint_name'] === $constraintName);
            }

            $columnNames[] = $row['column_name'];
        }

         $editor = PrimaryKeyConstraint::editor();

        if ($constraintName !== null) {
            $editor->setQuotedName($constraintName);
        }

        return $editor
            ->setQuotedColumnNames(...$columnNames)
            ->setIsClustered($isClustered)
            ->create();
    }

    /**
     * Gets Table Column Definition.
     *
     * @param array<string, mixed> $tableColumn
     *
     * @throws TypesException
     */
    abstract protected function _getPortableTableColumnDefinition(array $tableColumn): Column;

    /**
     * Aggregates and groups the index results according to the required data result.
     *
     * @param array<array<string, mixed>> $rows
     *
     * @return array<string, Index>
     */
    protected function _getPortableTableIndexesList(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $indexName = $row['key_name'];
            $keyName   = strtolower($indexName);

            if (! isset($result[$keyName])) {
                $result[$keyName] = [
                    'name' => $indexName,
                    'type' => $row['type'],
                ];

                if (isset($row['predicate'])) {
                    $result[$keyName]['predicate'] = $row['predicate'];
                }

                if (isset($row['is_clustered'])) {
                    $result[$keyName]['is_clustered'] = $row['is_clustered'];
                }
            }

            $result[$keyName]['columns'][$row['column_name']] = $row['length'] ?? null;
        }

        return array_map(static function ($data) {
            $editor = Index::editor()
                ->setQuotedName($data['name'])
                ->setType($data['type']);

            $columns = [];
            foreach ($data['columns'] as $name => $length) {
                /** @phpstan-ignore argument.type */
                $columns[] = new IndexedColumn(UnqualifiedName::quoted($name), $length);
            }

            $editor->setColumns(...$columns);

            if (isset($data['is_clustered'])) {
                $editor->setIsClustered($data['is_clustered']);
            }

            if (isset($data['predicate'])) {
                $editor->setPredicate($data['predicate']);
            }

            return $editor->create();
        }, $result);
    }

    /** @param array<string, mixed> $view */
    abstract protected function _getPortableViewDefinition(array $view): View;

    /**
     * @param array<array<string, mixed>> $rows
     *
     * @return array<int, ForeignKeyConstraint>
     */
    protected function _getPortableTableForeignKeysList(array $rows): array
    {
        $list = [];

        foreach ($rows as $value) {
            $list[] = $this->_getPortableTableForeignKeyDefinition($value);
        }

        return $list;
    }

    /**
     * This method acts as a temporary adapter between the shape of the elements of the list returned by
     * {@see _getPortableTableForeignKeysList()} and the API of {@see ForeignKeyConstraintEditor}. The intermediate
     * array representation of the foreign key properties is redundant and will be removed in a future release.
     *
     * @param array<string, mixed> $properties
     */
    protected function _getPortableTableForeignKeyDefinition(array $properties): ForeignKeyConstraint
    {
        $editor = ForeignKeyConstraint::editor()
            ->setQuotedReferencedTableName($properties['foreignTable'], $properties['foreignSchema'] ?? null)
            ->setQuotedReferencingColumnNames(
                /** @phpstan-ignore argument.type */
                ...$properties['local'],
            )
            ->setQuotedReferencedColumnNames(
                /** @phpstan-ignore argument.type */
                ...$properties['foreign'],
            );

        if ($properties['name'] !== '') {
            $editor->setQuotedName($properties['name']);
        }

        if (isset($properties['onUpdate'])) {
            $editor->setOnUpdateAction(ReferentialAction::from($properties['onUpdate']));
        }

        if (isset($properties['onDelete'])) {
            $editor->setOnDeleteAction(ReferentialAction::from($properties['onDelete']));
        }

        $deferrability = ! empty($properties['deferred'])
            ? Deferrability::DEFERRED
            : (
            ! empty($properties['deferrable'])
                ? Deferrability::DEFERRABLE
                : Deferrability::NOT_DEFERRABLE
            );

        $editor->setDeferrability($deferrability);

        return $editor->create();
    }

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
        $schemaConfig->setName($this->getCurrentSchemaName());

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

    /**
     * @return non-empty-string
     *
     * @throws Exception
     */
    private function getDatabase(string $methodName): string
    {
        $database = $this->connection->getDatabase();

        if ($database === null) {
            throw DatabaseRequired::new($methodName);
        }

        return $database;
    }

    public function createComparator(/* ComparatorConfig $config = new ComparatorConfig() */): Comparator
    {
        return new Comparator($this->platform, func_num_args() > 0 ? func_get_arg(0) : new ComparatorConfig());
    }

    /**
     * Groups the rows representing database object elements by table they belong to.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array<non-empty-string,array<non-empty-string,list<array<string,mixed>>>>
     */
    private function groupByTable(array $rows): array
    {
        $supportsSchemas = $this->platform->supportsSchemas();

        $data = [];

        foreach ($rows as $row) {
            if ($supportsSchemas) {
                $schemaNameKey = $row[self::SCHEMA_NAME_COLUMN];
            } else {
                $schemaNameKey = self::NULL_SCHEMA_KEY;
            }

            $data[$schemaNameKey][$row[self::TABLE_NAME_COLUMN]][] = $row;
        }

        return $data;
    }
}
