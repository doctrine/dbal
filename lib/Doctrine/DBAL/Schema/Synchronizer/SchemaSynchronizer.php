<?php

namespace Doctrine\DBAL\Schema\Synchronizer;

use Doctrine\DBAL\Schema\Schema;

/**
 * The synchronizer knows how to synchronize a schema with the configured
 * database.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
interface SchemaSynchronizer
{
    /**
     * Gets the SQL statements that can be executed to create the schema.
     *
     * @param \Doctrine\DBAL\Schema\Schema $createSchema
     *
     * @return array
     */
    function getCreateSchema(Schema $createSchema);

    /**
     * Gets the SQL Statements to update given schema with the underlying db.
     *
     * @param \Doctrine\DBAL\Schema\Schema $toSchema
     * @param bool                         $noDrops
     *
     * @return array
     */
    function getUpdateSchema(Schema $toSchema, $noDrops = false);

    /**
     * Gets the SQL Statements to drop the given schema from underlying db.
     *
     * @param \Doctrine\DBAL\Schema\Schema $dropSchema
     *
     * @return array
     */
    function getDropSchema(Schema $dropSchema);

    /**
     * Gets the SQL statements to drop all schema assets from underlying db.
     *
     * @return array
     */
    function getDropAllSchema();

    /**
     * Creates the Schema.
     *
     * @param \Doctrine\DBAL\Schema\Schema $createSchema
     *
     * @return void
     */
    function createSchema(Schema $createSchema);

    /**
     * Updates the Schema to new schema version.
     *
     * @param \Doctrine\DBAL\Schema\Schema $toSchema
     * @param bool                         $noDrops
     *
     * @return void
     */
    function updateSchema(Schema $toSchema, $noDrops = false);

    /**
     * Drops the given database schema from the underlying db.
     *
     * @param \Doctrine\DBAL\Schema\Schema $dropSchema
     *
     * @return void
     */
    function dropSchema(Schema $dropSchema);

    /**
     * Drops all assets from the underlying db.
     *
     * @return void
     */
    function dropAllSchema();
}
