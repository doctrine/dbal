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
    public function prepare(string $sql) : Statement;

    /**
     * Executes an SQL statement, returning a result set as a Statement object.
     *
     * @throws DBALException
     */
    public function query(string $sql) : ResultStatement;

    /**
     * Quotes a string for use in a query.
     */
    public function quote(string $input) : string;

    /**
     * Executes an SQL statement and return the number of affected rows.
     *
     * @throws DBALException
     */
    public function exec(string $statement) : int;

    /**
     * Returns the ID of the last inserted row.
     *
     * This method returns a string representing the value of the auto-increment field from the last row inserted into
     * the database, if any, or throws a DriverException if a value cannot be returned, in particular when:
     *
     * - the driver does not support identity columns;
     * - the last statement dit not return an identity (caution: see note below).
     *
     * Note: if the last statement was not an INSERT to an autoincrement column, this method MAY return an ID from a
     * previous statement. DO NOT RELY ON THIS BEHAVIOR which is driver-dependent: always use lastInsertId() right after
     * executing an INSERT statement.
     *
     * @return int|string The last insert ID, as an integer or a numeric string.
     *
     * @throws DriverException If an error occurs.
     */
    public function lastInsertId();

    /**
     * Returns the current sequence value for the given sequence name.
     *
     * This method returns a string representing the current value of the sequence, or throws a DriverException if a
     * value cannot be returned, in particular when:
     *
     * - the driver does not support sequences;
     * - the sequence does not exist.
     *
     * @param string $name The sequence name.
     *
     * @return int|string The sequence number, as an integer or a numeric string.
     *
     * @throws DriverException If an error occurs.
     */
    public function getSequenceNumber(string $name);

    /**
     * Initiates a transaction.
     *
     * @throws DriverException
     */
    public function beginTransaction() : void;

    /**
     * Commits a transaction.
     *
     * @throws DriverException
     */
    public function commit() : void;

    /**
     * Rolls back the current transaction, as initiated by beginTransaction().
     *
     * @throws DriverException
     */
    public function rollBack() : void;
}
