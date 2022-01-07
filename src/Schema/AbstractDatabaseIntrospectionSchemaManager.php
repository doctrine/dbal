<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;

use function assert;

/**
 * Base class for schema managers that allow database introspection.
 *
 * @template T of AbstractPlatform
 */
abstract class AbstractDatabaseIntrospectionSchemaManager extends AbstractSchemaManager
{
    /**
     * {@inheritdoc}
     */
    public function listTableDetails($name)
    {
        $currentDatabase = $this->_conn->getDatabase();

        assert($currentDatabase !== null);

        $tableOptions = $this->getDatabaseTableOptions($currentDatabase, $name);

        return new Table(
            $name,
            $this->_getPortableTableColumnList(
                $name,
                $currentDatabase,
                $this->selectDatabaseColumns($currentDatabase, $name)
                    ->fetchAllAssociative()
            ),
            $this->_getPortableTableIndexesList(
                $this->selectDatabaseIndexes($currentDatabase, $name)
                    ->fetchAllAssociative(),
                $name
            ),
            [],
            $this->_getPortableTableForeignKeysList(
                $this->selectDatabaseForeignKeys($currentDatabase, $name)
                    ->fetchAllAssociative()
            ),
            $tableOptions[$name] ?? []
        );
    }

    /**
     * {@inheritdoc}
     */
    public function listTables()
    {
        $currentDatabase = $this->_conn->getDatabase();

        assert($currentDatabase !== null);

        /** @var array<string,list<array<string,mixed>>> $columns */
        $columns = $this->selectDatabaseColumns($currentDatabase)
            ->fetchAllAssociativeGrouped();

        $indexes = $this->selectDatabaseIndexes($currentDatabase)
            ->fetchAllAssociativeGrouped();

        $foreignKeys = $this->selectDatabaseForeignKeys($currentDatabase)
            ->fetchAllAssociativeGrouped();

        $tableOptions = $this->getDatabaseTableOptions($currentDatabase);

        $tables = [];

        foreach ($columns as $tableName => $tableColumns) {
            $tables[] = new Table(
                $tableName,
                $this->_getPortableTableColumnList($tableName, $currentDatabase, $tableColumns),
                $this->_getPortableTableIndexesList($indexes[$tableName] ?? [], $tableName),
                [],
                $this->_getPortableTableForeignKeysList($foreignKeys[$tableName] ?? []),
                $tableOptions[$tableName] ?? []
            );
        }

        return $tables;
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
     */
    abstract protected function getDatabaseTableOptions(string $databaseName, ?string $tableName = null): array;
}
