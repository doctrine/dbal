<?php

namespace Doctrine\DBAL\Driver;

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
     *
     * @param string $sql The SQL query to prepare.
     *
     * @return Statement The prepared statement.
     *
     * @throws DriverException If an error occurs.
     */
    public function prepare(string $sql) : Statement;

    /**
     * Executes an SQL statement, returning a result set as a Statement object.
     *
     * @param string $sql The SQL query to execute.
     *
     * @return ResultStatement The result statement.
     *
     * @throws DriverException If an error occurs.
     */
    public function query(string $sql) : ResultStatement;

    /**
     * Quotes a string for use in a query.
     *
     * @param string $input The parameter to quote.
     *
     * @return string The quoted string.
     *
     * @throws DriverException If an error occurs.
     */
    public function quote(string $input) : string;

    /**
     * Executes an SQL statement and return the number of affected rows.
     *
     * @param string $statement The SQL query to execute.
     *
     * @return int The number of affected rows.
     *
     * @throws DriverException If an error occurs.
     */
    public function exec(string $statement) : int;

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * If a sequence name was not specified, lastInsertId() returns a string representing the value of the
     * auto-increment field from the last row inserted into the database, if any.
     *
     * If a sequence name was specified, lastInsertId() returns a string representing the current value of the sequence.
     *
     * This method throws a DriverException if a value cannot be returned, in particular when:
     *
     * - this operation is not supported by the driver;
     * - no sequence name was provided, but the driver requires one;
     * - no sequence name was provided, but the last statement dit not return an identity (caution: see note below);
     * - a sequence name was provided, but the driver does not support sequences;
     * - a sequence name was provided, but the sequence does not exist.
     *
     * Note: if the last statement was not an INSERT to an autoincrement column, this method MAY return an ID from a
     * previous statement. DO NOT RELY ON THIS BEHAVIOR which is driver-dependent: always use lastInsertId() right after
     * executing an INSERT statement.
     *
     * @param string|null $name The sequence name, or NULL to return the ID of the last row inserted.
     *
     * @return string The last insert ID or sequence value.
     *
     * @throws DriverException If an error occurs.
     */
    public function lastInsertId(?string $name = null) : string;

    /**
     * Initiates a transaction.
     *
     * @throws DriverException If an error occurs.
     */
    public function beginTransaction() : void;

    /**
     * Commits a transaction.
     *
     * @throws DriverException If an error occurs.
     */
    public function commit() : void;

    /**
     * Rolls back the current transaction, as initiated by beginTransaction().
     *
     * @throws DriverException If an error occurs.
     */
    public function rollBack() : void;

    /**
     * Returns the error code associated with the last operation on the database handle.
     *
     * @return string|null The error code, or null if no operation has been run on the database handle.
     */
    public function errorCode();

    /**
     * Returns extended error information associated with the last operation on the database handle.
     *
     * @return mixed[]
     */
    public function errorInfo();
}
