<?php

namespace Doctrine\DBAL\Driver;

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
     * @param string $sql
     *
     * @return Statement
     */
    public function prepare($sql);

    /**
     * Executes an SQL statement, returning a result set as a Statement object.
     *
     * @return Statement
     */
    public function query();

    /**
     * Quotes a string for use in a query.
     *
     * @param mixed $value
     * @param int   $type
     *
     * @return mixed
     */
    public function quote($value, $type = ParameterType::STRING);

    /**
     * Executes an SQL statement and return the number of affected rows.
     *
     * @param string $sql
     *
     * @return int
     */
    public function exec($sql);

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @param string|null $name
     *
     * @return string|int|false
     */
    public function lastInsertId($name = null);

    /**
     * Initiates a transaction.
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function beginTransaction();

    /**
     * Commits a transaction.
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function commit();

    /**
     * Rolls back the current transaction, as initiated by beginTransaction().
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function rollBack();

    /**
     * Returns the error code associated with the last operation on the database handle.
     *
     * @deprecated The error information is available via exceptions.
     *
     * @return string|null The error code, or null if no operation has been run on the database handle.
     */
    public function errorCode();

    /**
     * Returns extended error information associated with the last operation on the database handle.
     *
     * @deprecated The error information is available via exceptions.
     *
     * @return mixed[]
     */
    public function errorInfo();
}
