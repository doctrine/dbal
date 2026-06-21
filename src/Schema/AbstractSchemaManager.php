<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Doctrine\DBAL\Schema\Introspection\IntrospectingSchemaProvider;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

use function array_filter;
use function array_intersect;
use function array_map;
use function array_values;
use function assert;
use function count;
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
     * Returns true if all the given tables exist.
     *
     * @param array<int, string> $names
     *
     * @throws Exception
     */
    public function tablesExist(array $names): bool
    {
        $names = array_map('strtolower', $names);

        $introspectedNames = array_map(
            static function (OptionallyQualifiedName $name): string {
                $qualifier       = $name->getQualifier()?->getValue();
                $unqualifiedName = $name->getUnqualifiedName()->getValue();

                if ($qualifier !== null) {
                    $formattedName = $qualifier . '.' . $unqualifiedName;
                } else {
                    $formattedName = $unqualifiedName;
                }

                return strtolower($formattedName);
            },
            $this->introspectTableNames(),
        );

        return count($names) === count(array_intersect($names, $introspectedNames));
    }

    /** @throws Exception */
    public function tableExists(string $tableName): bool
    {
        return $this->tablesExist([$tableName]);
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
     * @throws Exception
     */
    public function introspectTable(OptionallyQualifiedName $tableName): Table
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
        return $this->introspectTable(
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
        return $this->introspectTable(
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
    public function dropDatabase(string $databaseName): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropDatabaseSQL($databaseName),
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
    public function dropTable(string $tableName): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropTableSQL($tableName),
        );
    }

    /**
     * Drops the index from the given table.
     *
     * @throws Exception
     */
    public function dropIndex(string $indexName, string $tableName): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropIndexSQL($indexName, $tableName),
        );
    }

    /**
     * Drops a foreign key from a table.
     *
     * @throws Exception
     */
    public function dropForeignKey(string $constraintName, string $tableName): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropForeignKeySQL($constraintName, $tableName),
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
    public function dropView(string $viewName): void
    {
        $this->connection->executeStatement(
            $this->platform->getDropViewSQL($viewName),
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
    public function createDatabase(string $databaseName): void
    {
        $this->connection->executeStatement(
            $this->platform->getCreateDatabaseSQL($databaseName),
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
     * @param string $tableName The name of the table on which the index is to be created.
     *
     * @throws Exception
     */
    public function createIndex(Index $index, string $tableName): void
    {
        $this->connection->executeStatement(
            $this->platform->getCreateIndexSQL($index, $tableName),
        );
    }

    /**
     * Creates a new foreign key.
     *
     * @param ForeignKeyConstraint $foreignKey The ForeignKey instance.
     * @param string               $tableName  The name of the table on which the foreign key is to be created.
     *
     * @throws Exception
     */
    public function createForeignKey(ForeignKeyConstraint $foreignKey, string $tableName): void
    {
        $this->connection->executeStatement(
            $this->platform->getCreateForeignKeySQL($foreignKey, $tableName),
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
            $this->platform->getCreateViewSQL($view->getObjectName()->toSQL($this->platform), $view->getSQL()),
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
    public function renameTable(string $oldName, string $newName): void
    {
        $this->connection->executeStatement(
            $this->platform->getRenameTableSQL($oldName, $newName),
        );
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
        $editor = Schema::editor()
            ->setDefaultNamespace($this->getCurrentSchemaName())
            ->setTables(...$this->introspectTables());

        if ($this->platform->supportsSequences()) {
            $editor->setSequences(...$this->introspectSequences());
        }

        return $editor->create();
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

    public function createComparator(ComparatorConfig $config = new ComparatorConfig()): Comparator
    {
        return new Comparator($this->platform, $config);
    }

    protected function parseUnqualifiedName(string $name): UnqualifiedName
    {
        try {
            return Parsers::parseUnqualifiedName($name);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($name, $e);
        }
    }

    protected function parseOptionallyQualifiedName(string $name): OptionallyQualifiedName
    {
        try {
            return Parsers::parseOptionallyQualifiedName($name);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($name, $e);
        }
    }
}
