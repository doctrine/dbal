<?php

namespace Doctrine\DBAL\Platforms;

/**
 * Database asset interface.
 * Interface that DBAL platforms can implement when all database assets can be retrieved with a single metadata query.
 *
 * @deprecated The methods defined in this interface will be made part of the {@link AbstractPlatform} base class in
 * the next major release.
 */
interface DatabaseIntrospectionSQLBuilder
{
    /**
     * Returns the SQL to list all the columns of all the tables in the database.
     */
    public function getListDatabaseColumnsSQL(string $database): string;

    /**
     * Returns the SQL to list all the indexes in the database.
     */
    public function getListDatabaseIndexesSQL(string $database): string;

    /**
     * Returns the SQL to list all the foreign keys in the database.
     */
    public function getListDatabaseForeignKeysSQL(string $database): string;
}
