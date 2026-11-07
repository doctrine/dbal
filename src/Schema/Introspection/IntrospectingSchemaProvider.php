<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Introspection;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\DerivedObjectNotHeld;
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
use Doctrine\DBAL\Schema\Metadata\PrimaryKeyConstraintColumnRow;
use Doctrine\DBAL\Schema\Metadata\UniqueConstraintColumnMetadataRow;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\PrimaryKeyConstraintEditor;
use Doctrine\DBAL\Schema\SchemaProvider;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableConfiguration;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Schema\UniqueConstraintEditor;
use Override;

use function array_map;
use function array_values;
use function in_array;

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
        $indexRowsByTable             = $this->groupRowsByTable($this->metadataProvider->getIndexColumnsForAllTables());
        $primaryKeyRowsByTable        = $this->groupRowsByTable(
            $this->metadataProvider->getPrimaryKeyConstraintColumnsForAllTables(),
        );
        $uniqueConstraintRowsByTable  = $this->groupRowsByTable(
            $this->metadataProvider->getUniqueConstraintColumnsForAllTables(),
        );
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
                $tableName = OptionallyQualifiedName::quoted($unqualifiedName, $schemaName);

                $editor = Table::editor()
                    ->setName($tableName)
                    ->setColumns(...$tableColumns);

                $this->setIndexesAndConstraints(
                    $editor,
                    $tableName,
                    $this->groupIndexEditors($indexRowsByTable[$schemaNameKey][$unqualifiedName] ?? []),
                    $this->groupPrimaryKeyConstraint($primaryKeyRowsByTable[$schemaNameKey][$unqualifiedName] ?? []),
                    $this->groupUniqueConstraints($uniqueConstraintRowsByTable[$schemaNameKey][$unqualifiedName] ?? []),
                );

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
    public function getTable(?string $schemaName, string $tableName): ?Table
    {
        $columns = $this->getColumnsForTable($schemaName, $tableName);

        if ($columns === []) {
            return null;
        }

        $name = OptionallyQualifiedName::quoted($tableName, $schemaName);

        $editor = Table::editor()
            ->setName($name)
            ->setColumns(...$columns);

        $this->setIndexesAndConstraints(
            $editor,
            $name,
            $this->groupIndexEditors($this->metadataProvider->getIndexColumnsForTable($schemaName, $tableName)),
            $this->groupPrimaryKeyConstraint(
                $this->metadataProvider->getPrimaryKeyConstraintColumnsForTable($schemaName, $tableName),
            ),
            $this->groupUniqueConstraints(
                $this->metadataProvider->getUniqueConstraintColumnsForTable($schemaName, $tableName),
            ),
        );

        $editor->setForeignKeyConstraints(...$this->getForeignKeyConstraintsForTable($schemaName, $tableName));

        $options = $this->getOptionsForTable($schemaName, $tableName);

        if ($options !== null) {
            $editor->setOptions($options);
        }

        return $editor->create();
    }

    /**
     * Sets the indexes and constraints of a table. Each derived object is given to the object that holds it: a
     * derived unique constraint to the index its row names, and a derived index to the constraint whose row names it.
     *
     * @param array<string, IndexEditor>                           $indexEditors         By index name.
     * @param ?IntrospectedConstraint<PrimaryKeyConstraintEditor>  $primaryKeyConstraint
     * @param list<IntrospectedConstraint<UniqueConstraintEditor>> $uniqueConstraints
     *
     * @throws DerivedObjectNotHeld If a derived object has no holder.
     */
    private function setIndexesAndConstraints(
        TableEditor $editor,
        OptionallyQualifiedName $tableName,
        array $indexEditors,
        ?IntrospectedConstraint $primaryKeyConstraint,
        array $uniqueConstraints,
    ): void {
        $declaredUniqueConstraints = [];

        foreach ($uniqueConstraints as $uniqueConstraint) {
            if (! $uniqueConstraint->isDerived()) {
                $declaredUniqueConstraints[] = $uniqueConstraint;

                continue;
            }

            $derivedUniqueConstraint = $uniqueConstraint->getEditor()->create();
            $indexName               = $uniqueConstraint->getIndexName();

            if ($indexName === null || ! isset($indexEditors[$indexName])) {
                throw DerivedObjectNotHeld::fromUniqueConstraint($tableName, $derivedUniqueConstraint->getObjectName());
            }

            $indexEditors[$indexName]->setDerivedUniqueConstraint($derivedUniqueConstraint);
        }

        $indexes = array_map(static fn (IndexEditor $editor): Index => $editor->create(), $indexEditors);

        $heldIndexes = [];

        if ($primaryKeyConstraint !== null) {
            $derivedIndex = $this->findDerivedIndex($indexes, $primaryKeyConstraint->getIndexName());

            if ($derivedIndex !== null) {
                $heldIndexes[] = $derivedIndex;
            }

            $editor->setPrimaryKeyConstraint(
                $primaryKeyConstraint->getEditor()
                    ->setDerivedIndex($derivedIndex)
                    ->create(),
            );
        }

        $sources = [];

        foreach ($declaredUniqueConstraints as $uniqueConstraint) {
            $derivedIndex = $this->findDerivedIndex($indexes, $uniqueConstraint->getIndexName());

            if ($derivedIndex !== null) {
                $heldIndexes[] = $derivedIndex;
            }

            $sources[] = $uniqueConstraint->getEditor()
                ->setDerivedIndex($derivedIndex)
                ->create();
        }

        $declaredIndexes = [];

        foreach ($indexes as $index) {
            if (! $index->isDerived()) {
                $declaredIndexes[] = $index;
            } elseif (! in_array($index, $heldIndexes, true)) {
                throw DerivedObjectNotHeld::fromIndex($tableName, $index->getObjectName());
            }
        }

        $editor
            ->setIndexes(...$declaredIndexes)
            ->setUniqueConstraints(...$sources);
    }

    /**
     * Returns the index with the given name if introspection reports it and it is derived. An existing index that a
     * constraint adopted is not derived.
     *
     * @param array<string, Index> $indexes   By index name.
     * @param ?non-empty-string    $indexName
     */
    private function findDerivedIndex(array $indexes, ?string $indexName): ?Index
    {
        if ($indexName === null || ! isset($indexes[$indexName]) || ! $indexes[$indexName]->isDerived()) {
            return null;
        }

        return $indexes[$indexName];
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
        return array_map(
            static fn (IndexEditor $editor): Index => $editor->create(),
            array_values($this->groupIndexEditors(
                $this->metadataProvider->getIndexColumnsForTable($schemaName, $tableName),
            )),
        );
    }

    #[Override]
    public function getPrimaryKeyConstraintForTable(?string $schemaName, string $tableName): ?PrimaryKeyConstraint
    {
        return $this->groupPrimaryKeyConstraint(
            $this->metadataProvider->getPrimaryKeyConstraintColumnsForTable($schemaName, $tableName),
        )?->getEditor()->create();
    }

    #[Override]
    public function getUniqueConstraintsForTable(?string $schemaName, string $tableName): array
    {
        return array_map(
            static fn (IntrospectedConstraint $constraint): UniqueConstraint => $constraint->getEditor()->create(),
            $this->groupUniqueConstraints(
                $this->metadataProvider->getUniqueConstraintColumnsForTable($schemaName, $tableName),
            ),
        );
    }

    /**
     * Builds one index editor per index of a table.
     *
     * @param iterable<IndexColumnMetadataRow> $rows The rows of one table.
     *
     * @return array<string, IndexEditor> By index name.
     */
    private function groupIndexEditors(iterable $rows): array
    {
        $editors   = [];
        $processor = new IndexColumnMetadataProcessor();

        foreach ($rows as $row) {
            $indexName = $row->getIndexName();

            if (! isset($editors[$indexName])) {
                $editors[$indexName] = $processor->initializeEditor($row);
            }

            $processor->applyRow($editors[$indexName], $row);
        }

        return $editors;
    }

    /**
     * Builds the editor of a table's primary key constraint, if the table has one.
     *
     * @param iterable<PrimaryKeyConstraintColumnRow> $rows The rows of one table.
     *
     * @return ?IntrospectedConstraint<PrimaryKeyConstraintEditor>
     */
    private function groupPrimaryKeyConstraint(iterable $rows): ?IntrospectedConstraint
    {
        $constraint = null;
        $processor  = new PrimaryKeyConstraintColumnMetadataProcessor();

        foreach ($rows as $row) {
            $constraint ??= new IntrospectedConstraint($processor->initializeEditor($row), $row->getIndexName(), false);

            $processor->applyRow($constraint->getEditor(), $row);
        }

        return $constraint;
    }

    /**
     * Builds one editor per unique constraint of a table.
     *
     * @param iterable<UniqueConstraintColumnMetadataRow> $rows The rows of one table.
     *
     * @return list<IntrospectedConstraint<UniqueConstraintEditor>>
     */
    private function groupUniqueConstraints(iterable $rows): array
    {
        $constraints = [];
        $processor   = new UniqueConstraintColumnMetadataProcessor();

        foreach ($rows as $row) {
            $id = $row->getId();

            if (! isset($constraints[$id])) {
                $constraints[$id] = new IntrospectedConstraint(
                    $processor->initializeEditor($row),
                    $row->getIndexName(),
                    $row->isDerived(),
                );
            }

            $processor->applyRow($constraints[$id]->getEditor(), $row);
        }

        return array_values($constraints);
    }

    /**
     * Groups rows by schema and table.
     *
     * If the underlying database does not support schemas, the schema key will be {@link NULL_SCHEMA_KEY}.
     *
     * @param iterable<R> $rows
     *
     * @return array<string, array<non-empty-string, list<R>>>
     *
     * @template R of IndexColumnMetadataRow|PrimaryKeyConstraintColumnRow|UniqueConstraintColumnMetadataRow
     */
    private function groupRowsByTable(iterable $rows): array
    {
        $rowsByTable = [];

        foreach ($rows as $row) {
            $rowsByTable[$row->getSchemaName() ?? self::NULL_SCHEMA_KEY][$row->getTableName()][] = $row;
        }

        return $rowsByTable;
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
     * @template R of ForeignKeyConstraintColumnMetadataRow
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
