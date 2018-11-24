<?php

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
     *
     * @param string $sql The SQL query to prepare.
     *
     * @return Statement The prepared statement.
     */
    public function prepare(string $sql) : Statement;

    /**
     * Executes an SQL statement, returning a result set as a Statement object.
     *
     * @param string $sql The SQL query to execute.
     *
     * @return ResultStatement The result statement.
     *
     * @throws DBALException
     */
    public function query(string $sql) : ResultStatement;

    /**
     * Quotes a string for use in a query.
     *
     * @param string $input The parameter to quote.
     *
     * @return string The quoted string.
     */
    public function quote(string $input) : string;

    /**
     * Executes an SQL statement and return the number of affected rows.
     *
     * @param string $statement The SQL query to execute.
     *
     * @return int The number of affected rows.
     *
     * @throws DBALException
     */
    public function exec(string $statement) : int;

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * If a sequence name was not specified, lastInsertId() returns a string representing the row ID of the last row
     * that was inserted into the database.
     *
     * If a sequence name was specified, lastInsertId() returns a string representing the last value retrieved from the
     * specified sequence object.
     *
     * This method throws a DriverException if a value cannot be returned, in particular when:
     *
     * - this operation is not supported by the driver;
     * - no sequence name was provided, but the driver requires one;
     * - no sequence name was provided, but the last statement dit not return an identity;
     * - a sequence name was provided, but the driver does not support sequences;
     * - a sequence name was provided, but the sequence does not exist.
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
     */
    public function beginTransaction() : void;

    /**
     * Commits a transaction.
     */
    public function commit() : void;

    /**
     * Rolls back the current transaction, as initiated by beginTransaction().
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
