<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\ParameterType;

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
     * If the driver does not support quoting, an exception is thrown.
     *
     * @param mixed $input The parameter to quote.
     * @param int   $type  The parameter type, as a ParameterType constant.
     *
     * @return string The quoted string.
     */
    public function quote($input, $type = ParameterType::STRING) : string;

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
     * @param string|null $name The sequence name, or NULL if the database does not use sequences.
     *
     * @return string The last insert ID or sequence value.
     *
     * @throws DriverException If a sequence name is not provided but the database requires one.
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
