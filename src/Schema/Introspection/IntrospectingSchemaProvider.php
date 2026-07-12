<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Introspection;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraintEditor;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\IndexEditor;
use Doctrine\DBAL\Schema\Introspection\MetadataProcessor\ForeignKeyConstraintColumnMetadataProcessor;
use Doctrine\DBAL\Schema\Introspection\MetadataProcessor\IndexColumnMetadataProcessor;
use Doctrine\DBAL\Schema\Introspection\MetadataProcessor\PrimaryKeyConstraintColumnMetadataProcessor;
use Doctrine\DBAL\Schema\Introspection\MetadataProcessor\SequenceMetadataProcessor;
use Doctrine\DBAL\Schema\Introspection\MetadataProcessor\UniqueConstraintColumnMetadataProcessor;
use Doctrine\DBAL\Schema\Introspection\MetadataProcessor\ViewMetadataProcessor;
use Doctrine\DBAL\Schema\Metadata\ForeignKeyConstraintColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\IndexColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\MetadataProvider;
use Doctrine\DBAL\Schema\Metadata\UniqueConstraintColumnMetadataRow;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\PrimaryKeyConstraintEditor;
use Doctrine\DBAL\Schema\SchemaProvider;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableConfiguration;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Schema\UniqueConstraintEditor;
use Override;

use function array_map;
use function array_values;

/**
 * Provides access to the database schema obtained by introspection.
 *
 * If the underlying database platform supports schemas, and the object is located in the current schema, the schema
 * name will be omitted to match the behavior of the platforms that do not support schemas.
 *
 * @internal Should be used only by {@link AbstractSchemaManager}.
 */
final readonly class IntrospectingSchemaProvider implements SchemaProvider
{
    /**
     * The value representing the <code>NULL</code> schema key in results grouped by schema name.
     *
     * The value should be a valid array key but, ideally, not a valid schema name, so an empty string looks like a
     * perfect fit.
     */
    private const string NULL_SCHEMA_KEY = '';

    /** @param ?non-empty-string $currentSchemaName */
    public function __construct(
        private MetadataProvider $metadataProvider,
        private ?string $currentSchemaName,
        private TableConfiguration $tableConfiguration,
    ) {
    }

    #[Override]
    public function getAllDatabaseNames(): array
    {
        $databaseNames = [];

        foreach ($this->metadataProvider->getAllDatabaseNames() as $row) {
            $databaseNames[] = UnqualifiedName::quoted($row->getDatabaseName());
        }

        return $databaseNames;
    }

    #[Override]
    public function getAllSchemaNames(): array
    {
        $schemaNames = [];

        foreach ($this->metadataProvider->getAllSchemaNames() as $row) {
            $schemaNames[] = UnqualifiedName::quoted($row->getSchemaName());
        }

        return $schemaNames;
    }

    #[Override]
    public function getAllTables(): array
    {
        $tableColumnsByTable          = $this->getColumnsForAllTables();
        $indexesByTable               = $this->getIndexesForAllTables();
        $primaryKeyConstraintsByTable = $this->getPrimaryKeyConstraintsForAllTables();
        $uniqueConstraintsByTable     = $this->getUniqueConstraintsForAllTables();
        $foreignKeyConstraintsByTable = $this->getForeignKeyConstraintsForAllTables();
        $tableOptionsByTable          = $this->getOptionsForAllTables();

        $tables = [];

        foreach ($tableColumnsByTable as $schemaNameKey => $schemaTables) {
            if ($schemaNameKey !== self::NULL_SCHEMA_KEY && $schemaNameKey !== $this->currentSchemaName) {
                $schemaName = $schemaNameKey;
            } else {
                $schemaName = null;
            }

            foreach ($schemaTables as $unqualifiedName => $tableColumns) {
                $editor = Table::editor()
                    ->setName(
                        OptionallyQualifiedName::quoted($unqualifiedName, $schemaName),
                    )
                    ->setColumns(...$tableColumns)
                    ->setIndexes(
                        ...$indexesByTable[$schemaNameKey][$unqualifiedName] ?? [],
                    );

                if (isset($primaryKeyConstraintsByTable[$schemaNameKey][$unqualifiedName])) {
                    $editor->setPrimaryKeyConstraint(
                        $primaryKeyConstraintsByTable[$schemaNameKey][$unqualifiedName],
                    );
                }

                if (isset($uniqueConstraintsByTable[$schemaNameKey][$unqualifiedName])) {
                    $editor->setUniqueConstraints(
                        ...$uniqueConstraintsByTable[$schemaNameKey][$unqualifiedName],
                    );
                }

                if (isset($foreignKeyConstraintsByTable[$schemaNameKey][$unqualifiedName])) {
                    $editor->setForeignKeyConstraints(
                        ...$foreignKeyConstraintsByTable[$schemaNameKey][$unqualifiedName],
                    );
                }

                if (isset($tableOptionsByTable[$schemaNameKey][$unqualifiedName])) {
                    $editor->setOptions($tableOptionsByTable[$schemaNameKey][$unqualifiedName]);
                }

                $tables[] = $editor
                    ->setConfiguration($this->tableConfiguration)
                    ->create();
            }
        }

        return $tables;
    }

    #[Override]
    public function getAllTableNames(): array
    {
        $tableNames = [];

        foreach ($this->metadataProvider->getAllTableNames() as $row) {
            $schemaName = $row->getSchemaName();
            $tableName  = $row->getTableName();

            if ($schemaName === $this->currentSchemaName) {
                $schemaName = null;
            }

            $tableNames[] = OptionallyQualifiedName::quoted($tableName, $schemaName);
        }

        return $tableNames;
    }

    #[Override]
    public function getColumnsForTable(?string $schemaName, string $tableName): array
    {
        $columns = [];

        foreach ($this->metadataProvider->getTableColumnsForTable($schemaName, $tableName) as $row) {
            $columns[] = $row->getColumn();
        }

        return $columns;
    }

    /**
     * Returns columns of all tables, grouped by schema and table.
     *
     * If the underlying database does not support schemas, the schema key will be {@link NULL_SCHEMA_KEY}.
     *
     * @return array<string, array<non-empty-string, list<Column>>>
     *
     * @throws Exception
     */
    private function getColumnsForAllTables(): array
    {
        $columns = [];

        foreach ($this->metadataProvider->getTableColumnsForAllTables() as $row) {
            $schemaName = $row->getSchemaName() ?? self::NULL_SCHEMA_KEY;
            $tableName  = $row->getTableName();

            $columns[$schemaName][$tableName][] = $row->getColumn();
        }

        return $columns;
    }

    #[Override]
    public function getIndexesForTable(?string $schemaName, string $tableName): array
    {
        $editors   = [];
        $processor = new IndexColumnMetadataProcessor();

        foreach ($this->metadataProvider->getIndexColumnsForTable($schemaName, $tableName) as $row) {
            $indexName = $row->getIndexName();

            if (! isset($editors[$indexName])) {
                $editors[$indexName] = $processor->initializeEditor($row);
            }

            $processor->applyRow($editors[$indexName], $row);
        }

        return array_map(
            static fn (IndexEditor $e): Index => $e->create(),
            array_values($editors),
        );
    }

    /**
     * Returns indexes for all tables, grouped by schema and table.
     *
     * If the underlying database does not support schemas, the schema key will be {@link NULL_SCHEMA_KEY}.
     *
     * @return array<string, array<non-empty-string, list<Index>>>
     *
     * @throws Exception
     */
    private function getIndexesForAllTables(): array
    {
        $processor = new IndexColumnMetadataProcessor();

        return $this->groupByTable(
            static fn (IndexColumnMetadataRow $row): string => $row->getIndexName(),
            $processor->initializeEditor(...),
            $processor->applyRow(...),
            static fn (IndexEditor $editor): Index => $editor->create(),
            $this->metadataProvider->getIndexColumnsForAllTables(),
        );
    }

    #[Override]
    public function getPrimaryKeyConstraintForTable(?string $schemaName, string $tableName): ?PrimaryKeyConstraint
    {
        $editor    = null;
        $processor = new PrimaryKeyConstraintColumnMetadataProcessor();

        foreach ($this->metadataProvider->getPrimaryKeyConstraintColumnsForTable($schemaName, $tableName) as $row) {
            $editor ??= $processor->initializeEditor($row);

            $processor->applyRow($editor, $row);
        }

        return $editor?->create();
    }

    /**
     * Returns the primary key constraints for all tables, grouped by schema and table.
     *
     * If the underlying database does not support schemas, the schema key will be {@link NULL_SCHEMA_KEY}.
     *
     * @return array<string, array<non-empty-string, PrimaryKeyConstraint>>
     *
     * @throws Exception
     */
    private function getPrimaryKeyConstraintsForAllTables(): array
    {
        $editors   = [];
        $processor = new PrimaryKeyConstraintColumnMetadataProcessor();

        foreach ($this->metadataProvider->getPrimaryKeyConstraintColumnsForAllTables() as $row) {
            $schemaName = $row->getSchemaName() ?? self::NULL_SCHEMA_KEY;
            $tableName  = $row->getTableName();

            if (! isset($editors[$schemaName][$tableName])) {
                $editors[$schemaName][$tableName] = $processor->initializeEditor($row);
            }

            $processor->applyRow($editors[$schemaName][$tableName], $row);
        }

        return array_map(
            static fn (array $editors): array => array_map(
                static fn (PrimaryKeyConstraintEditor $editor): PrimaryKeyConstraint => $editor->create(),
                $editors,
            ),
            $editors,
        );
    }

    #[Override]
    public function getUniqueConstraintsForTable(?string $schemaName, string $tableName): array
    {
        $editors   = [];
        $processor = new UniqueConstraintColumnMetadataProcessor();

        foreach ($this->metadataProvider->getUniqueConstraintColumnsForTable($schemaName, $tableName) as $row) {
            $id = $row->getId();

            if (! isset($editors[$id])) {
                $editors[$id] = $processor->initializeEditor($row);
            }

            $processor->applyRow($editors[$id], $row);
        }

        return array_map(
            static fn (UniqueConstraintEditor $e): UniqueConstraint => $e->create(),
            array_values($editors),
        );
    }

    /**
     * Returns the unique constraints, grouped by schema and table.
     *
     * If the underlying database does not support schemas, the schema key will be {@link NULL_SCHEMA_KEY}.
     *
     * @return array<string, array<non-empty-string, list<UniqueConstraint>>>
     *
     * @throws Exception
     */
    private function getUniqueConstraintsForAllTables(): array
    {
        $processor = new UniqueConstraintColumnMetadataProcessor();

        return $this->groupByTable(
            static fn (UniqueConstraintColumnMetadataRow $row): int|string => $row->getId(),
            $processor->initializeEditor(...),
            $processor->applyRow(...),
            static fn (UniqueConstraintEditor $editor): UniqueConstraint => $editor->create(),
            $this->metadataProvider->getUniqueConstraintColumnsForAllTables(),
        );
    }

    #[Override]
    public function getForeignKeyConstraintsForTable(?string $schemaName, string $tableName): array
    {
        $editors   = [];
        $processor = new ForeignKeyConstraintColumnMetadataProcessor($this->currentSchemaName);

        foreach ($this->metadataProvider->getForeignKeyConstraintColumnsForTable($schemaName, $tableName) as $row) {
            $id = $row->getId();

            if (! isset($editors[$id])) {
                $editors[$id] = $processor->initializeEditor($row);
            }

            $processor->applyRow($editors[$id], $row);
        }

        return array_map(
            static fn (ForeignKeyConstraintEditor $e): ForeignKeyConstraint => $e->create(),
            array_values($editors),
        );
    }

    /**
     * Returns the foreign key constraints, grouped by schema and table.
     *
     * If the underlying database does not support schemas, the schema key will be {@link NULL_SCHEMA_KEY}.
     *
     * @return array<string, array<non-empty-string, list<ForeignKeyConstraint>>>
     *
     * @throws Exception
     */
    private function getForeignKeyConstraintsForAllTables(): array
    {
        $processor = new ForeignKeyConstraintColumnMetadataProcessor($this->currentSchemaName);

        return $this->groupByTable(
            static fn (ForeignKeyConstraintColumnMetadataRow $row): int|string => $row->getId(),
            $processor->initializeEditor(...),
            $processor->applyRow(...),
            static fn (ForeignKeyConstraintEditor $editor): ForeignKeyConstraint => $editor->create(),
            $this->metadataProvider->getForeignKeyConstraintColumnsForAllTables(),
        );
    }

    /**
     * Groups rows by schema and table, builds one editor per grouping key, and creates the objects.
     *
     * If the underlying database does not support schemas, the schema key will be {@link NULL_SCHEMA_KEY}.
     *
     * @param callable(R): (int|string) $getKey
     * @param callable(R): E            $initializeEditor
     * @param callable(E, R): void      $applyRow
     * @param callable(E): T            $create
     * @param iterable<R>               $rows
     *
     * @return array<string, array<non-empty-string, list<T>>>
     *
     * @template R of ForeignKeyConstraintColumnMetadataRow|IndexColumnMetadataRow|UniqueConstraintColumnMetadataRow
     * @template E of object
     * @template T of object
     */
    private function groupByTable(
        callable $getKey,
        callable $initializeEditor,
        callable $applyRow,
        callable $create,
        iterable $rows,
    ): array {
        $editors = [];

        foreach ($rows as $row) {
            $schemaName = $row->getSchemaName() ?? self::NULL_SCHEMA_KEY;
            $tableName  = $row->getTableName();
            $key        = $getKey($row);

            if (! isset($editors[$schemaName][$tableName][$key])) {
                $editors[$schemaName][$tableName][$key] = $initializeEditor($row);
            }

            $applyRow($editors[$schemaName][$tableName][$key], $row);
        }

        return array_map(
            static fn (array $tables): array => array_map(
                static fn (array $editors): array => array_map($create, array_values($editors)),
                $tables,
            ),
            $editors,
        );
    }

    #[Override]
    public function getOptionsForTable(?string $schemaName, string $tableName): ?array
    {
        foreach ($this->metadataProvider->getTableOptionsForTable($schemaName, $tableName) as $row) {
            return $row->getOptions();
        }

        return null;
    }

    /**
     * Returns options for all tables, grouped by schema and table.
     *
     * If the underlying database does not support schemas, the schema key will be {@link NULL_SCHEMA_KEY}.
     *
     * @return array<string, array<non-empty-string, array<non-empty-string, mixed>>>
     *
     * @throws Exception
     */
    private function getOptionsForAllTables(): array
    {
        $options = [];

        foreach ($this->metadataProvider->getTableOptionsForAllTables() as $row) {
            $schemaName = $row->getSchemaName() ?? self::NULL_SCHEMA_KEY;
            $tableName  = $row->getTableName();

            $options[$schemaName][$tableName] = $row->getOptions();
        }

        return $options;
    }

    #[Override]
    public function getAllViews(): array
    {
        $processor = new ViewMetadataProcessor();
        $views     = [];

        foreach ($this->metadataProvider->getAllViews() as $row) {
            $views[] = $processor->createObject($row);
        }

        return $views;
    }

    #[Override]
    public function getAllSequences(): array
    {
        $processor = new SequenceMetadataProcessor();
        $sequences = [];

        foreach ($this->metadataProvider->getAllSequences() as $row) {
            $sequences[] = $processor->createObject($row);
        }

        return $sequences;
    }
}
