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
use Doctrine\DBAL\Schema\Introspection\MetadataProcessor\ViewMetadataProcessor;
use Doctrine\DBAL\Schema\Metadata\MetadataProvider;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\PrimaryKeyConstraintEditor;
use Doctrine\DBAL\Schema\SchemaProvider;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableConfiguration;
use TypeError;

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
    private const NULL_SCHEMA_KEY = '';

    /** @param ?non-empty-string $currentSchemaName */
    public function __construct(
        private MetadataProvider $metadataProvider,
        private ?string $currentSchemaName,
        private TableConfiguration $tableConfiguration,
    ) {
    }

    /** {@inheritDoc} */
    public function getAllDatabaseNames(): array
    {
        $databaseNames = [];

        foreach ($this->metadataProvider->getAllDatabaseNames() as $row) {
            $databaseNames[] = UnqualifiedName::quoted($row->getDatabaseName());
        }

        return $databaseNames;
    }

    /** {@inheritDoc} */
    public function getAllSchemaNames(): array
    {
        $schemaNames = [];

        foreach ($this->metadataProvider->getAllSchemaNames() as $row) {
            $schemaNames[] = UnqualifiedName::quoted($row->getSchemaName());
        }

        return $schemaNames;
    }

    /** {@inheritDoc} */
    public function getAllTables(): array
    {
        $tableColumnsByTable          = $this->getColumnsForAllTables();
        $indexesByTable               = $this->getIndexesForAllTables();
        $primaryKeyConstraintsByTable = $this->getPrimaryKeyConstraintsForAllTables();
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

    /** {@inheritDoc} */
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

    /** {@inheritDoc} */
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

    /** {@inheritDoc} */
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
        $editors   = [];
        $processor = new IndexColumnMetadataProcessor();

        foreach ($this->metadataProvider->getIndexColumnsForAllTables() as $row) {
            $schemaName = $row->getSchemaName() ?? self::NULL_SCHEMA_KEY;
            $tableName  = $row->getTableName();
            $indexName  = $row->getIndexName();

            if (! isset($editors[$schemaName][$tableName][$indexName])) {
                $editors[$schemaName][$tableName][$indexName] = $processor->initializeEditor($row);
            }

            $processor->applyRow($editors[$schemaName][$tableName][$indexName], $row);
        }

        return array_map(
            static fn (array $editors): array => array_map(
                static fn (array $editors): array => array_map(
                    static fn (IndexEditor $editor): Index => $editor->create(),
                    array_values($editors),
                ),
                $editors,
            ),
            $editors,
        );
    }

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

    /** {@inheritDoc} */
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
        $editors   = [];
        $processor = new ForeignKeyConstraintColumnMetadataProcessor($this->currentSchemaName);

        foreach ($this->metadataProvider->getForeignKeyConstraintColumnsForAllTables() as $row) {
            $schemaName = $row->getSchemaName() ?? self::NULL_SCHEMA_KEY;
            $tableName  = $row->getTableName();
            $id         = $row->getId();

            if (! isset($editors[$schemaName][$tableName][$id])) {
                $editors[$schemaName][$tableName][$id] = $processor->initializeEditor($row);
            }

            $processor->applyRow($editors[$schemaName][$tableName][$id], $row);
        }

        return array_map(
            static fn (array $editors): array => array_map(
                static fn (array $editors): array => array_map(
                    static fn (ForeignKeyConstraintEditor $editor): ForeignKeyConstraint => $editor->create(),
                    array_values($editors),
                ),
                $editors,
            ),
            $editors,
        );
    }

    /** {@inheritDoc} */
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

    /** {@inheritDoc} */
    public function getAllViews(): array
    {
        $processor = new ViewMetadataProcessor();
        $views     = [];

        foreach ($this->metadataProvider->getAllViews() as $row) {
            $views[] = $processor->createObject($row);
        }

        return $views;
    }

    /** {@inheritDoc} */
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
