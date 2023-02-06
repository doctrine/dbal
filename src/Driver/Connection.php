<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\ServerVersionProvider;

/**
 * Connection interface.
 * Driver connections must implement this interface.
 */
interface Connection extends ServerVersionProvider
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
     *
     * The usage of this method is discouraged. Use prepared statements
     * or {@see AbstractPlatform::quoteStringLiteral()} instead.
     */
    public function quote(string $value): string;

    /**
     * Executes an SQL statement and return the number of affected rows.
     * If the number of affected rows is greater than the maximum int value (PHP_INT_MAX),
     * the number of affected rows may be returned as a string.
     *
     * @return int|numeric-string
     *
     * @throws Exception
     */
    public function exec(string $sql): int|string;

    /**
     * Returns the ID of the last inserted row.
     *
     * This method returns an integer or a string representing the value of the auto-increment column
     * from the last row inserted into the database, if any, or throws an exception if a value cannot be returned,
     * in particular when:
     *
     * - the driver does not support identity columns;
     * - the last statement dit not return an identity (caution: see note below).
     *
     * Note: if the last statement was not an INSERT to an autoincrement column, this method MAY return an ID from a
     * previous statement. DO NOT RELY ON THIS BEHAVIOR which is driver-dependent: always call this method right after
     * executing an INSERT statement.
     *
     * @throws Exception
     */
    public function lastInsertId(): int|string;

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

    /**
     * Provides access to the native database connection.
     *
     * @return resource|object
     */
    public function getNativeConnection();
}
