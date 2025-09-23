<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

/**
 * Provides access to the database schema.
 *
 * Each method, except for {@link getAllDatabaseNames()}, will return the schema objects in the current database. The
 * definition of a "database" may vary depending on the underlying database platform.
 *
 * @internal Should be used only by {@link AbstractSchemaManager}.
 */
interface SchemaProvider
{
    /**
     * Returns names of all available databases.
     *
     * @return list<UnqualifiedName>
     *
     * @throws Exception
     */
    public function getAllDatabaseNames(): array;

    /**
     * Returns names of all available schemas.
     *
     * @return list<UnqualifiedName>
     *
     * @throws Exception
     */
    public function getAllSchemaNames(): array;

    /**
     * Returns names of all tables.
     *
     * @return list<OptionallyQualifiedName>
     *
     * @throws Exception
     */
    public function getAllTableNames(): array;

    /**
     * Returns all tables.
     *
     * @return list<Table>
     *
     * @throws Exception
     */
    public function getAllTables(): array;

    /**
     * Returns columns for the given table.
     *
     * If the current database platform supports schemas, the schema name must be specified. Otherwise, null must be
     * passed as the schema name.
     *
     * Returns an empty value if the table doesn't exist or is not accessible to the connection.
     *
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     *
     * @return list<Column>
     *
     * @throws Exception
     */
    public function getColumnsForTable(?string $schemaName, string $tableName): array;

    /**
     * Returns indexes for the given table.
     *
     * If the current database platform supports schemas, the schema name must be specified. Otherwise, null must be
     * passed as the schema name.
     *
     * Returns an empty value if the table doesn't exist or is not accessible to the connection.
     *
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     *
     * @return list<Index>
     *
     * @throws Exception
     */
    public function getIndexesForTable(?string $schemaName, string $tableName): array;

    /**
     * Returns the primary key constraint for the given table.
     *
     * If the current database platform supports schemas, the schema name must be specified. Otherwise, null must be
     * passed as the schema name.
     *
     * Returns <code>null</code> if the table doesn't exist, doesn't have a primary key constraint or is not accessible
     * to the connection.
     *
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     *
     * @throws Exception
     */
    public function getPrimaryKeyConstraintForTable(?string $schemaName, string $tableName): ?PrimaryKeyConstraint;

    /**
     * Returns the foreign key constraints in the given table.
     *
     * If the current database platform supports schemas, the schema name must be specified. Otherwise, null must be
     * passed as the schema name.
     *
     * Returns an empty value if the table doesn't exist or is not accessible to the connection.
     *
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     *
     * @return list<ForeignKeyConstraint>
     *
     * @throws Exception
     */
    public function getForeignKeyConstraintsForTable(?string $schemaName, string $tableName): array;

    /**
     * Returns options of the given table.
     *
     * If the current database platform supports schemas, the schema name must be specified. Otherwise, null must be
     * passed as the schema name.
     *
     * Returns <code>null</code> if the table doesn't exist or is not accessible to the connection.
     *
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     *
     * @return array<non-empty-string, mixed>|null
     *
     * @throws Exception
     */
    public function getOptionsForTable(?string $schemaName, string $tableName): ?array;

    /**
     * @return list<View>
     *
     * @throws Exception
     */
    public function getAllViews(): array;

    /**
     * @return list<Sequence>
     *
     * @throws Exception
     */
    public function getAllSequences(): array;
}
