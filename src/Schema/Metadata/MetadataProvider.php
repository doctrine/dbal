<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Metadata;

use Doctrine\DBAL\Exception;

/**
 * Provides low-level metadata that describes the underlying database schema.
 *
 * Recommendations for implementations:
 * 1. Filter out internal or system schemas, tables, and other objects that are not relevant to the user.
 * 2. Remain stateless: if the underlying connection changes the current database and/or schema, the subsequent calls
 *    to the methods of this interface should reflect that change.
 */
interface MetadataProvider
{
    /**
     * Returns names of all available databases.
     *
     * The resulting list is ordered by database name.
     *
     * @return iterable<DatabaseMetadataRow>
     *
     * @throws Exception
     */
    public function getAllDatabaseNames(): iterable;

    /**
     * Returns names of all schemas available within the current database.
     *
     * The resulting list is ordered by schema name.
     *
     * @return iterable<SchemaMetadataRow>
     *
     * @throws Exception
     */
    public function getAllSchemaNames(): iterable;

    /**
     * Returns names of all tables within the current database.
     *
     * The results are ordered by schema name (if the underlying database platform supports schemas) and table name.
     *
     * @return iterable<TableMetadataRow>
     *
     * @throws Exception
     */
    public function getAllTableNames(): iterable;

    /**
     * Returns the columns of all tables within the current database.
     *
     * The results are ordered by schema name (if the underlying database platform supports schemas), table name, and
     * column position within the table.
     *
     * @return iterable<TableColumnMetadataRow>
     *
     * @throws Exception
     */
    public function getTableColumnsForAllTables(): iterable;

    /**
     * Returns the columns of the given table.
     *
     * If the underlying database platform supports schemas, the schema name must be specified. Otherwise, null must be
     * passed as the schema name.
     *
     * The results are ordered by column position within the table. If the table doesn't exist, or is not accessible to
     * the connection, an empty value is returned.
     *
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     *
     * @return iterable<TableColumnMetadataRow>
     *
     * @throws Exception
     */
    public function getTableColumnsForTable(?string $schemaName, string $tableName): iterable;

    /**
     * Returns the index columns of all tables within the current database.
     *
     * The results are ordered by schema name (if the underlying database platform supports schemas), table name, index
     * name, and column position within the index.
     *
     * @return iterable<IndexColumnMetadataRow>
     *
     * @throws Exception
     */
    public function getIndexColumnsForAllTables(): iterable;

    /**
     * Returns the index columns of the given table.
     *
     * If the underlying database platform supports schemas, the schema name must be specified. Otherwise, null must be
     * passed as the schema name.
     *
     * The results are ordered by index name and column position within the index. If the table doesn't exist, or is not
     * accessible to the connection, an empty value is returned.
     *
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     *
     * @return iterable<IndexColumnMetadataRow>
     *
     * @throws Exception
     */
    public function getIndexColumnsForTable(?string $schemaName, string $tableName): iterable;

    /**
     * Returns the primary key constraint columns of all tables within the current database.
     *
     * The results are ordered by schema name (if the underlying database platform supports schemas), table name, and
     * column position within the primary key constraint. If a table does not have a primary key constraint, it will not
     * be represented in the results.
     *
     * @return iterable<PrimaryKeyConstraintColumnRow>
     *
     * @throws Exception
     */
    public function getPrimaryKeyConstraintColumnsForAllTables(): iterable;

    /**
     * Returns the primary key constraint columns of the given table.
     *
     * If the underlying database platform supports schemas, the schema name must be specified. Otherwise, null must be
     * passed as the schema name.
     *
     * The results are ordered by column position within the primary key constraint. If the table doesn't exist,
     * is not accessible to the connection or doesn't have a primary key constraint, an empty value is returned.
     *
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     *
     * @return iterable<PrimaryKeyConstraintColumnRow>
     *
     * @throws Exception
     */
    public function getPrimaryKeyConstraintColumnsForTable(?string $schemaName, string $tableName): iterable;

    /**
     * Returns the foreign key constraint columns of all tables within the current database.
     *
     * The results are ordered by schema name (if the underlying database platform supports schemas), table name,
     * foreign key constraint name, and column position within the foreign key constraint. If the underlying database
     * platform supports unnamed foreign key constraints, instead of ordering by name, it may provide another stable
     * order of the results.
     *
     * @return iterable<ForeignKeyConstraintColumnMetadataRow>
     *
     * @throws Exception
     */
    public function getForeignKeyConstraintColumnsForAllTables(): iterable;

    /**
     * Returns the foreign key constraint columns of the given table.
     *
     * If the underlying database platform supports schemas, the schema name must be specified. Otherwise, null must be
     * passed as the schema name.
     *
     * The results are ordered by foreign key constraint name and column position within the foreign key constraint. If
     * the underlying database platform supports unnamed foreign key constraints, instead of ordering by name, it may
     * provide another stable order of the results. If the table doesn't exist, or is not accessible to the connection,
     * an empty value is returned.
     *
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     *
     * @return iterable<ForeignKeyConstraintColumnMetadataRow>
     *
     * @throws Exception
     */
    public function getForeignKeyConstraintColumnsForTable(
        ?string $schemaName,
        string $tableName,
    ): iterable;

    /**
     * Returns the options of all tables within the current database.
     *
     * The results are ordered by schema name (if the underlying database platform supports schemas) and table name. The
     * order of the options within each array is not significant.
     *
     * Implementations must return an element for each table, even if their options are not explicitly represented in
     * the underlying database.
     *
     * @return iterable<TableMetadataRow>
     *
     * @throws Exception
     */
    public function getTableOptionsForAllTables(): iterable;

    /**
     * Returns the options of the given table.
     *
     * If the underlying database platform supports schemas, the schema name must be specified. Otherwise, null must be
     * passed as the schema name.
     *
     * If the table doesn't exist or is not accessible to the connection, an empty value is returned. Implementations
     * must return a non-empty array as long as the table exists, even if its options are not explicitly represented
     * in the underlying database.
     *
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     *
     * @return iterable<TableMetadataRow>
     *
     * @throws Exception
     */
    public function getTableOptionsForTable(?string $schemaName, string $tableName): iterable;

    /**
     * Returns the definitions of all views within the current database.
     *
     * If the underlying database platform supports schemas, the schema name must be specified. Otherwise, null must be
     * passed as the schema name.
     *
     * The results are ordered by schema name (if the underlying database platform supports schemas) and view name.
     *
     * @return iterable<ViewMetadataRow>
     *
     * @throws Exception
     */
    public function getAllViews(): iterable;

    /**
     * Returns the definitions of all sequences within the current database.
     *
     * If the underlying database platform supports schemas, the schema name must be specified. Otherwise, null must be
     * passed as the schema name.
     *
     * The results are ordered by schema name (if the underlying database platform supports schemas) and sequence name.
     *
     * @return iterable<SequenceMetadataRow>
     *
     * @throws Exception
     */
    public function getAllSequences(): iterable;
}
