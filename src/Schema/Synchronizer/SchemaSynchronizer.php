<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Synchronizer;

use Doctrine\DBAL\Schema\Schema;

/**
 * The synchronizer knows how to synchronize a schema with the configured
 * database.
 */
interface SchemaSynchronizer
{
    /**
     * Gets the SQL statements that can be executed to create the schema.
     *
     * @return array<int, string>
     */
    public function getCreateSchema(Schema $createSchema): array;

    /**
     * Gets the SQL Statements to update given schema with the underlying db.
     *
     * @return array<int, string>
     */
    public function getUpdateSchema(Schema $toSchema, bool $noDrops = false): array;

    /**
     * Gets the SQL Statements to drop the given schema from underlying db.
     *
     * @return string[]
     */
    public function getDropSchema(Schema $dropSchema): array;

    /**
     * Gets the SQL statements to drop all schema assets from underlying db.
     *
     * @return array<int, string>
     */
    public function getDropAllSchema(): array;

    /**
     * Creates the Schema.
     */
    public function createSchema(Schema $createSchema): void;

    /**
     * Updates the Schema to new schema version.
     */
    public function updateSchema(Schema $toSchema, bool $noDrops = false): void;

    /**
     * Drops the given database schema from the underlying db.
     */
    public function dropSchema(Schema $dropSchema): void;

    /**
     * Drops all assets from the underlying db.
     */
    public function dropAllSchema(): void;
}
