<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\DBALException;

/**
 * Connection interface.
 * Driver connections must implement this interface.
 *
 * This resembles (a subset of) the PDO interface.
 */
interface Connection
{
    /**
     * Prepares a statement for execution and returns a Statement object.
     */
    public function prepare(string $sql): Statement;

    /**
     * Executes an SQL statement, returning a result set as a Statement object.
     *
     * @throws DBALException
     */
    public function query(string $sql): Result;

    /**
     * Quotes a string for use in a query.
     */
    public function quote(string $input): string;

    /**
     * Executes an SQL statement and return the number of affected rows.
     *
     * @throws DBALException
     */
    public function exec(string $statement): int;

    /**
     * Returns the ID of the last inserted row or sequence value.
     */
    public function lastInsertId(?string $name = null): string;

    /**
     * Initiates a transaction.
     *
     * @throws DriverException
     */
    public function beginTransaction(): void;

    /**
     * Commits a transaction.
     *
     * @throws DriverException
     */
    public function commit(): void;

    /**
     * Rolls back the current transaction, as initiated by beginTransaction().
     *
     * @throws DriverException
     */
    public function rollBack(): void;
}
