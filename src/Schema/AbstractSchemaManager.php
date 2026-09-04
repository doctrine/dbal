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
use Doctrine\DBAL\Schema\Introspection\IntrospectingSchemaProvider;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
use Throwable;

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
     * @deprecated Use {@see introspectDatabaseNames()} instead.
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
     * @deprecated Use {@see introspectSchemaNames()} instead.
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
     * @deprecated Use {@see introspectSequences()} instead.
     *
     * @return list<Sequence>
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
     * @deprecated Use {@see introspectTableColumns()}, {@see introspectTableColumnsByUnquotedName()}
     *             or {@see introspectTableColumnsByQuotedName()} instead.
     *
     * @return array<string, Column>
     *
     * @throws Exception
     */
    public function listTableColumns(string $table): array
    {
        $this->validateTableName($table, __METHOD__);

        $database = $this->getDatabase(__METHOD__);

        return $this->_getPortableTableColumnList(
            $table,
            $database,
            $this->fetchTableColumns($database, $this->normalizeName($table)),
        );
    }

    /**
     * Lists the indexes for a given table returning an array of Index instances.
     *
     * Keys of the portable indexes list are all lower-cased.
     *
     * @deprecated Use {@see introspectTableIndexes()}, {@see introspectTableIndexesByUnquotedName()}
     *             or {@see introspectTableIndexesByQuotedName()}
     *
     * @return array<string, Index>
     *
     * @throws Exception
     */
    public function listTableIndexes(string $table): array
    {
        $this->validateTableName($table, __METHOD__);

        $database = $this->getDatabase(__METHOD__);
        $table    = $this->normalizeName($table);

        return $this->_getPortableTableIndexesList(
            $this->fetchIndexColumns($database, $table),
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

    /** @throws Exception */
    public function tableExists(string $tableName): bool
    {
        return $this->tablesExist([$tableName]);
    }

    /**
     * Returns a list of all tables in the current database.
     *
     * @deprecated Use {@see introspectTableNames()} instead.
     *
     * @return list<non-empty-string>
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
     * @param list<N> $assetNames
     *
     * @return list<N>
     *
     * @template N
     */
    private function filterAssetNames(array $assetNames): array
    {
        $filter = $this->connection->getConfiguration()->getSchemaAssetsFilter();

        return array_values(array_filter($assetNames, $filter));
    }

    /**
     * Lists the tables for this connection.
     *
     * @deprecated Use {@see introspectTables()} instead.
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

        $configuration = $this->createSchemaConfig()
            ->toTableConfiguration();

        foreach ($tableColumnsByTable as $tableName => $tableColumns) {
            if (! $filter($tableName)) {
                continue;
            }

            $table = new Table(
                $tableName,
                $this->_getPortableTableColumnList($tableName, $database, $tableColumns),
                $this->_getPortableTableIndexesList($indexColumnsByTable[$tableName] ?? [], $tableName),
                [],
                $this->_getPortableTableForeignKeysList($foreignKeyColumnsByTable[$tableName] ?? []),
                $tableOptionsByTable[$tableName] ?? [],
                $configuration,
                null,
                [],
            );
            $table->setTypeRegistry($this->connection->getConfiguration()->getTypeRegistry());

            $tables[] = $table;
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
     * An extension point for those platforms where case sensitivity of the object name depends on whether it's quoted.
     *
     * Such platforms should convert a possibly quoted name into a value of the corresponding case.
     *
     * @deprecated Use {@see Identifier::toNormalizedValue()} instead.
     */
    protected function normalizeName(string $name): string
    {
        $identifier = new Identifier($name);

        return $identifier->getName();
    }

    private function validateTableName(string $input, string $methodName): void
    {
        try {
            $tableName = Parsers::parseOptionallyQualifiedName($input);
        } catch (Throwable $e) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6768',
                'Unable to parse table name passed to %s(): %s.',
                $methodName,
                $e->getMessage(),
            );

            return;
        }

        if ($tableName->getQualifier() !== null && ! $this->platform->supportsSchemas()) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6768',
                'Relying on %s() not parsing an unquoted table name containing a dot while working with %s is'
                    . ' deprecated. Pass a quoted name instead.',
                $methodName,
                $this->platform::class,
            );
        }
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
     * Fetches definitions of table columns in the specified database. If the table name is specified, narrows down
     * the selection to this table.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    protected function fetchTableColumns(string $databaseName, ?string $tableName = null): array
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
    protected function fetchIndexColumns(string $databaseName, ?string $tableName = null): array
    {
        return $this->selectIndexColumns($databaseName, $tableName)->fetchAllAssociative();
    }

    /**
     * Fetches definitions of foreign key columns in the specified database. If the table name is specified,
     * narrows down the selection to this table.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    protected function fetchForeignKeyColumns(string $databaseName, ?string $tableName = null): array
    {
        return $this->selectForeignKeyColumns($databaseName, $tableName)->fetchAllAssociative();
    }

    /**
     * Fetches definitions of table columns in the specified database and returns them grouped by table name.
     *
     * @return array<non-empty-string,list<array<string,mixed>>>
     *
     * @throws Exception
     */
    protected function fetchTableColumnsByTable(string $databaseName): array
    {
        return $this->groupByTable($this->fetchTableColumns($databaseName));
    }

    /**
     * Fetches definitions of index columns in the specified database and returns them grouped by table name.
     *
     * @return array<non-empty-string,list<array<string,mixed>>>
     *
     * @throws Exception
     */
    protected function fetchIndexColumnsByTable(string $databaseName): array
    {
        return $this->groupByTable($this->fetchIndexColumns($databaseName));
    }

    /**
     * Fetches definitions of foreign key columns in the specified database and returns them grouped by table name.
     *
     * @return array<non-empty-string, list<array<string, mixed>>>
     *
     * @throws Exception
     */
    protected function fetchForeignKeyColumnsByTable(string $databaseName): array
    {
        return $this->groupByTable($this->fetchForeignKeyColumns($databaseName));
    }

    /**
     * Fetches table options for the tables in the specified database and returns them grouped by table name.
     * If the table name is specified, narrows down the selection to this table.
     *
     * @param ?non-empty-string $tableName
     *
     * @return array<non-empty-string, array<string,mixed>>
     *
     * @throws Exception
     */
    abstract protected function fetchTableOptionsByTable(string $databaseName, ?string $tableName = null): array;

    /**
     * Introspects the table with the given name.
     *
     * @deprecated Use {@see introspectTableByUnquotedName()} or {@see introspectTableByQuotedName()} instead.
     *
     * @throws Exception
     */
    public function introspectTable(string $name): Table
    {
        $columns = $this->listTableColumns($name);

        if ($columns === []) {
            throw TableDoesNotExist::new($name);
        }

        $table = new Table(
            $name,
            $columns,
            $this->listTableIndexes($name),
            [],
            $this->listTableForeignKeys($name),
            $this->getTableOptions($name),
            null,
            null,
            [],
        );
        $table->setTypeRegistry($this->connection->getConfiguration()->getTypeRegistry());

        return $table;
    }

    /**
     * Lists the views this connection has.
     *
     * @deprecated Use {@see introspectViews()} instead.
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
     * @deprecated Use {@see introspectTableForeignKeyConstraints()},
     *             {@see introspectTableForeignKeyConstraintsByUnquotedName()}
     *             or {@see introspectTableForeignKeyConstraintsByQuotedName()} instead.
     *
     * @return array<int|string, ForeignKeyConstraint>
     *
     * @throws Exception
     */
    public function listTableForeignKeys(string $table): array
    {
        $this->validateTableName($table, __METHOD__);

        $database = $this->getDatabase(__METHOD__);

        return $this->_getPortableTableForeignKeysList(
            $this->fetchForeignKeyColumns(
                $database,
                $this->normalizeName($table),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function getTableOptions(string $name): array
    {
        $this->validateTableName($name, __METHOD__);

        $normalizedName = $this->normalizeName($name);

        return $this->fetchTableOptionsByTable(
            $this->getDatabase(__METHOD__),
            $normalizedName, // @phpstan-ignore argument.type
        )[$normalizedName] ?? [];
    }

    /**
     * Introspects available databases and returns their names.
     *
     * @return list<UnqualifiedName>
     *
     * @throws Exception
     */
    public function introspectDatabaseNames(): array
    {
        return $this->createSchemaProvider()->getAllDatabaseNames();
    }

    /**
     * Introspects schemas in the current database and returns their names.
     *
     * @return list<UnqualifiedName>
     *
     * @throws Exception
     */
    public function introspectSchemaNames(): array
    {
        return $this->createSchemaProvider()->getAllSchemaNames();
    }

    /**
     * Introspects tables in the current database and returns their names.
     *
     * @return list<OptionallyQualifiedName>
     *
     * @throws Exception
     */
    public function introspectTableNames(): array
    {
        $filter     = $this->connection->getConfiguration()->getSchemaAssetsFilter();
        $tableNames = [];

        foreach ($this->createSchemaProvider()->getAllTableNames() as $tableName) {
            if ($this->testTableName($tableName, $filter)) {
                $tableNames[] = $tableName;
            }
        }

        return $tableNames;
    }

    /**
     * Introspects tables in the current database and returns their definitions.
     *
     * @return list<Table>
     *
     * @throws Exception
     */
    public function introspectTables(): array
    {
        $filter = $this->connection->getConfiguration()->getSchemaAssetsFilter();
        $tables = [];

        foreach ($this->createSchemaProvider()->getAllTables() as $table) {
            if ($this->testTableName($table->getObjectName(), $filter)) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * Tests whether the table name matches the filter.
     */
    private function testTableName(OptionallyQualifiedName $tableName, callable $filter): bool
    {
        $formattedName = $tableName->getUnqualifiedName()->getValue();
        $qualifier     = $tableName->getQualifier();

        if ($qualifier !== null) {
            $formattedName = $qualifier->getValue() . '.' . $formattedName;
        }

        return $filter($formattedName);
    }

    /**
     * Introspects the table with the given name and returns its definition. If the name is unqualified, and the
     * underlying database platform supports schemas, the current schema is used.
     *
     * This method is currently private due to the conflict with {@see introspectTable()}. Use
     * {@see introspectTableByUnquotedName()} or {@see introspectTableByQuotedName()} instead.
     *
     * @throws Exception
     */
    private function introspectTable0(OptionallyQualifiedName $tableName): Table
    {
        $columns = $this->introspectTableColumns($tableName);

        if ($columns === []) {
            throw TableDoesNotExist::new($tableName->toString());
        }

        $options = $this->introspectTableOptions($tableName);
        assert($options !== null);

        return Table::editor()
            ->setName($tableName)
            ->setColumns(...$columns)
            ->setPrimaryKeyConstraint($this->introspectTablePrimaryKeyConstraint($tableName))
            ->setIndexes(...$this->introspectTableIndexes($tableName))
            ->setUniqueConstraints(...$this->introspectTableUniqueConstraints($tableName))
            ->setForeignKeyConstraints(...$this->introspectTableForeignKeyConstraints($tableName))
            ->setOptions($options)
            ->create();
    }

    /**
     * Introspects the table with the given unquoted name and schema name and returns its definition. If the schema name
     * is omitted, and the underlying database platform supports schemas, the current schema is used.
     *
     * @param non-empty-string  $tableName
     * @param ?non-empty-string $schemaName
     *
     * @throws Exception
     */
    public function introspectTableByUnquotedName(string $tableName, ?string $schemaName = null): Table
    {
        return $this->introspectTable0(
            OptionallyQualifiedName::unquoted($tableName, $schemaName),
        );
    }

    /**
     * Introspects the table with the given quoted name and schema name and returns its definition. If the schema name
     * is omitted, and the underlying database platform supports schemas, the current schema is used.
     *
     * @param non-empty-string  $tableName
     * @param ?non-empty-string $schemaName
     *
     * @throws Exception
     */
    public function introspectTableByQuotedName(string $tableName, ?string $schemaName = null): Table
    {
        return $this->introspectTable0(
            OptionallyQualifiedName::quoted($tableName, $schemaName),
        );
    }

    /**
     * Introspects the columns of a given table and returns their definitions. If the name is unqualified, and the
     * underlying database platform supports schemas, the current schema is used.
     *
     * Returns an empty value if the table does not exist.
     *
     * @return list<Column>
     *
     * @throws Exception
     */
    public function introspectTableColumns(OptionallyQualifiedName $tableName): array
    {
        return $this->introspectTableObjects(
            $tableName,
            static function (SchemaProvider $schemaProvider, ?string $schemaName, string $tableName): array {
                return $schemaProvider->getColumnsForTable($schemaName, $tableName);
            },
        );
    }

    /**
     * Introspects the columns of the table with the given unquoted name and schema name and returns their definitions.
     * If the schema name is omitted and the underlying database platform supports schemas, the current schema is used.
     *
     * Returns an empty value if the table does not exist.
     *
     * @param non-empty-string  $tableName
     * @param ?non-empty-string $schemaName
     *
     * @return list<Column>
     *
     * @throws Exception
     */
    public function introspectTableColumnsByUnquotedName(string $tableName, ?string $schemaName = null): array
    {
        return $this->introspectTableColumns(
            OptionallyQualifiedName::unquoted($tableName, $schemaName),
        );
    }

    /**
     * Introspects the columns of the table with the given quoted name and schema name and returns their definitions. If
     * the schema name is omitted and the underlying database platform supports schemas, the current schema is used.
     *
     * Returns an empty value if the table does not exist.
     *
     * @param non-empty-string  $tableName
     * @param ?non-empty-string $schemaName
     *
     * @return list<Column>
     *
     * @throws Exception
     */
    public function introspectTableColumnsByQuotedName(string $tableName, ?string $schemaName = null): array
    {
        return $this->introspectTableColumns(
            OptionallyQualifiedName::quoted($tableName, $schemaName),
        );
    }

    /**
     * Introspects the indexes of a given table and returns their definitions. If the name is unqualified, and the
     * underlying database platform supports schemas, the current schema is used.
     *
     * Returns an empty value if the table does not exist.
     *
     * @return list<Index>
     *
     * @throws Exception
     */
    public function introspectTableIndexes(OptionallyQualifiedName $tableName): array
    {
        return $this->introspectTableObjects(
            $tableName,
            static function (SchemaProvider $schemaProvider, ?string $schemaName, string $tableName): array {
                return $schemaProvider->getIndexesForTable($schemaName, $tableName);
            },
        );
    }

    /**
     * Introspects the indexes of the table with the given unquoted name and schema name and returns their definitions.
     * If the schema name is omitted and the underlying database platform supports schemas, the current schema is used.
     *
     * Returns an empty value if the table does not exist.
     *
     * @param non-empty-string  $tableName
     * @param ?non-empty-string $schemaName
     *
     * @return list<Index>
     *
     * @throws Exception
     */
    public function introspectTableIndexesByUnquotedName(string $tableName, ?string $schemaName = null): array
    {
        return $this->introspectTableIndexes(
            OptionallyQualifiedName::unquoted($tableName, $schemaName),
        );
    }

    /**
     * Introspects the indexes of the table with the given quoted name and schema name and returns their definitions. If
     * the schema name is omitted and the underlying database platform supports schemas, the current schema is used.
     *
     * Returns an empty value if the table does not exist.
     *
     * @param non-empty-string  $tableName
     * @param ?non-empty-string $schemaName
     *
     * @return list<Index>
     *
     * @throws Exception
     */
    public function introspectTableIndexesByQuotedName(string $tableName, ?string $schemaName = null): array
    {
        return $this->introspectTableIndexes(
            OptionallyQualifiedName::quoted($tableName, $schemaName),
        );
    }

    /**
     * Introspects the primary key constraint of a given table and returns its definition. If the name is unqualified,
     * and the underlying database platform supports schemas, the current schema is used.
     *
     * Returns <code>null</code> if the table does not exist or does not have a primary key constraint.
     *
     * @throws Exception
     */
    public function introspectTablePrimaryKeyConstraint(OptionallyQualifiedName $tableName): ?PrimaryKeyConstraint
    {
        return $this->introspectTableObjects(
            $tableName,
            static function (
                SchemaProvider $schemaProvider,
                ?string $schemaName,
                string $tableName,
            ): ?PrimaryKeyConstraint {
                return $schemaProvider->getPrimaryKeyConstraintForTable($schemaName, $tableName);
            },
        );
    }

    /**
     * Introspects the unique constraints of a given table and returns their definitions. If the name is unqualified,
     * and the underlying database platform supports schemas, the current schema is used.
     *
     * Returns an empty value if the table does not exist.
     *
     * @return list<UniqueConstraint>
     *
     * @throws Exception
     */
    public function introspectTableUniqueConstraints(OptionallyQualifiedName $tableName): array
    {
        return $this->introspectTableObjects(
            $tableName,
            static function (SchemaProvider $schemaProvider, ?string $schemaName, string $tableName): array {
                return $schemaProvider->getUniqueConstraintsForTable($schemaName, $tableName);
            },
        );
    }

    /**
     * Introspects the unique constraints of the table with the given unquoted name and schema name and returns their
     * definitions. If the name is unqualified, and the underlying database platform supports schemas, the current
     * schema is used.
     *
     * Returns an empty value if the table does not exist.
     *
     * @param non-empty-string  $tableName
     * @param ?non-empty-string $schemaName
     *
     * @return list<UniqueConstraint>
     *
     * @throws Exception
     */
    public function introspectTableUniqueConstraintsByUnquotedName(
        string $tableName,
        ?string $schemaName = null,
    ): array {
        return $this->introspectTableUniqueConstraints(
            OptionallyQualifiedName::unquoted($tableName, $schemaName),
        );
    }

    /**
     * Introspects the unique constraints of the table with the given quoted name and schema name and returns their
     * definitions. If the name is unqualified, and the underlying database platform supports schemas, the current
     * schema is used.
     *
     * Returns an empty value if the table does not exist.
     *
     * @param non-empty-string  $tableName
     * @param ?non-empty-string $schemaName
     *
     * @return list<UniqueConstraint>
     *
     * @throws Exception
     */
    public function introspectTableUniqueConstraintsByQuotedName(
        string $tableName,
        ?string $schemaName = null,
    ): array {
        return $this->introspectTableUniqueConstraints(
            OptionallyQualifiedName::quoted($tableName, $schemaName),
        );
    }

    /**
     * Introspects the foreign key constraints of a given table and returns their definitions. If the name is
     * unqualified, and the underlying database platform supports schemas, the current schema is used.
     *
     * Returns an empty value if the table does not exist.
     *
     * @return list<ForeignKeyConstraint>
     *
     * @throws Exception
     */
    public function introspectTableForeignKeyConstraints(OptionallyQualifiedName $tableName): array
    {
        return $this->introspectTableObjects(
            $tableName,
            static function (SchemaProvider $schemaProvider, ?string $schemaName, string $tableName): array {
                return $schemaProvider->getForeignKeyConstraintsForTable($schemaName, $tableName);
            },
        );
    }

    /**
     * Introspects the foreign key constraints of the table with the given unquoted name and schema name and returns
     * their definitions. If the name is unqualified, and the underlying database platform supports schemas, the current
     * schema is used.
     *
     * Returns an empty value if the table does not exist.
     *
     * @param non-empty-string  $tableName
     * @param ?non-empty-string $schemaName
     *
     * @return list<ForeignKeyConstraint>
     *
     * @throws Exception
     */
    public function introspectTableForeignKeyConstraintsByUnquotedName(
        string $tableName,
        ?string $schemaName = null,
    ): array {
        return $this->introspectTableForeignKeyConstraints(
            OptionallyQualifiedName::unquoted($tableName, $schemaName),
        );
    }

    /**
     * Introspects the foreign key constraints of the table with the given quoted name and schema name and returns their
     * definitions. If the name is unqualified, and the underlying database platform supports schemas, the current
     * schema is used.
     *
     * Returns an empty value if the table does not exist.
     *
     * @param non-empty-string  $tableName
     * @param ?non-empty-string $schemaName
     *
     * @return list<ForeignKeyConstraint>
     *
     * @throws Exception
     */
    public function introspectTableForeignKeyConstraintsByQuotedName(
        string $tableName,
        ?string $schemaName = null,
    ): array {
        return $this->introspectTableForeignKeyConstraints(
            OptionallyQualifiedName::quoted($tableName, $schemaName),
        );
    }

    /**
     * @param callable(SchemaProvider, ?non-empty-string, non-empty-string): R $function
     *
     * @return R
     *
     * @throws Exception
     *
     * @template R
     */
    private function introspectTableObjects(OptionallyQualifiedName $tableName, callable $function)
    {
        $folding   = $this->platform->getUnquotedIdentifierFolding();
        $qualifier = $tableName->getQualifier();

        if ($qualifier !== null) {
            $schemaName = $qualifier->toNormalizedValue($folding);
        } else {
            $schemaName = $this->getCurrentSchemaName();
        }

        return $function(
            $this->createSchemaProvider(),
            $schemaName,
            $tableName->getUnqualifiedName()->toNormalizedValue($folding),
        );
    }

    /**
     * @return ?array<non-empty-string, mixed>
     *
     * @throws Exception
     */
    private function introspectTableOptions(OptionallyQualifiedName $tableName): ?array
    {
        $folding   = $this->platform->getUnquotedIdentifierFolding();
        $qualifier = $tableName->getQualifier();

        if ($qualifier !== null) {
            $schemaName = $qualifier->toNormalizedValue($folding);
        } else {
            $schemaName = $this->getCurrentSchemaName();
        }

        return $this->createSchemaProvider()->getOptionsForTable(
            $schemaName,
            $tableName->getUnqualifiedName()->toNormalizedValue($folding),
        );
    }

    /**
     * Introspects the views in the current database and returns their definitions.
     *
     * @return list<View>
     *
     * @throws Exception
     */
    public function introspectViews(): array
    {
        return $this->createSchemaProvider()->getAllViews();
    }

    /**
     * Introspects the sequences in the current database and returns their definitions.
     *
     * @return list<Sequence>
     *
     * @throws Exception
     */
    public function introspectSequences(): array
    {
        return $this->filterAssetNames(
            $this->createSchemaProvider()->getAllSequences(),
        );
    }

    /** @throws Exception */
    private function createSchemaProvider(): IntrospectingSchemaProvider
    {
        return new IntrospectingSchemaProvider(
            $this->platform->createMetadataProvider($this->connection),
            $this->getCurrentSchemaName(),
            $this->createSchemaConfig()->toTableConfiguration(),
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
     * @param array<array<string, mixed>> $rows
     *
     * @return array<string, Column>
     *
     * @throws TypesException
     */
    protected function _getPortableTableColumnList(string $table, string $database, array $rows): array
    {
        $list = [];
        foreach ($rows as $row) {
            $column = $this->_getPortableTableColumnDefinition($row);

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
    protected function _getPortableTableIndexesList(array $rows, string $tableName): array
    {
        $result = [];
        foreach ($rows as $row) {
            $indexName = $keyName = $row['key_name'];
            if ($row['primary']) {
                $keyName = 'primary';
            }

            $keyName = strtolower($keyName);

            if (! isset($result[$keyName])) {
                $options = [
                    'lengths' => [],
                ];

                if (isset($row['where'])) {
                    $options['where'] = $row['where'];
                }

                $result[$keyName] = [
                    'name' => $indexName,
                    'columns' => [],
                    'unique' => ! $row['non_unique'],
                    'primary' => $row['primary'],
                    'flags' => $row['flags'] ?? [],
                    'options' => $options,
                ];
            }

            $result[$keyName]['columns'][]            = $row['column_name'];
            $result[$keyName]['options']['lengths'][] = $row['length'] ?? null;
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

    /**
     * @deprecated Use the schema name and the unqualified table name separately instead.
     *
     * @param array<string, mixed> $table
     *
     * @return non-empty-string
     */
    abstract protected function _getPortableTableDefinition(array $table): string;

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
        $schemaConfig->setName($this->getCurrentSchemaName());

        $params = $this->connection->getParams();
        if (! isset($params['defaultTableOptions'])) {
            $params['defaultTableOptions'] = [];
        }

        if (! isset($params['defaultTableOptions']['charset']) && isset($params['charset'])) {
            $params['defaultTableOptions']['charset'] = $params['charset'];
        }

        $schemaConfig->setDefaultTableOptions($params['defaultTableOptions']);
        $schemaConfig->setTypeRegistry($this->connection->getConfiguration()->getTypeRegistry());

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
     * @return array<non-empty-string,list<array<string,mixed>>>
     */
    private function groupByTable(array $rows): array
    {
        $data = [];

        foreach ($rows as $row) {
            $tableName          = $this->_getPortableTableDefinition($row);
            $data[$tableName][] = $row;
        }

        /** @phpstan-ignore return.type */
        return $data;
    }
}
