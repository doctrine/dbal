<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

/**
 * Connection interface.
 * Driver connections must implement this interface.
 */
interface Connection
{
    /**
     * Prepares a statement for execution and returns a Statement object.
     *
     * @throws Exception
     */
    public function prepare(string $sql): Statement;

    /**
     * Executes an SQL statement, returning a result set as a Statement object.
     *
     * @throws Exception
     */
    public function query(string $sql): Result;

    /**
     * Quotes a string for use in a query.
     */
    public function quote(string $value): string;

    /**
     * Executes an SQL statement and return the number of affected rows.
     *
     * @throws Exception
     */
    public function exec(string $sql): int;

    /**
     * Returns the ID of the last inserted row.
     *
     * @throws Exception
     */
    public function lastInsertId(): string;

    /**
     * Initiates a transaction.
     *
     * @throws Exception
     */
    public function beginTransaction(): void;

    /**
     * Commits a transaction.
     *
     * @throws Exception
     */
    public function commit(): void;

    /**
     * Rolls back the current transaction, as initiated by beginTransaction().
     *
     * @throws Exception
     */
    public function rollBack(): void;
}
