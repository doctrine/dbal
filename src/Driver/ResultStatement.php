<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

/**
 * Interface for the reading part of a prepare statement only.
 */
interface ResultStatement
{
    /**
     * Closes the cursor, enabling the statement to be executed again.
     */
    public function closeCursor() : void;

    /**
     * Returns the number of columns in the result set
     *
     * @return int The number of columns in the result set represented
     *             by the statement. If there is no result set,
     *             this method should return 0.
     */
    public function columnCount() : int;

    /**
     * Returns the number of rows affected by the last DELETE, INSERT, or UPDATE statement
     * executed by the corresponding object.
     *
     * If the last SQL statement executed by the associated Statement object was a SELECT statement,
     * some databases may return the number of rows returned by that statement. However,
     * this behaviour is not guaranteed for all databases and should not be
     * relied on for portable applications.
     */
    public function rowCount() : int;

    /**
     * Returns the next row of a result set as a numeric array or FALSE if there are no more rows.
     *
     * @return array<int,mixed>|false
     *
     * @throws DriverException
     */
    public function fetchNumeric();

    /**
     * Returns the next row of a result set as an associative array or FALSE if there are no more rows.
     *
     * @return array<string,mixed>|false
     *
     * @throws DriverException
     */
    public function fetchAssociative();

    /**
     * Returns the first value of the next row of a result set or FALSE if there are no more rows.
     *
     * @return mixed|false
     *
     * @throws DriverException
     */
    public function fetchOne();

    /**
     * Returns an array containing all of the result set rows represented as numeric arrays.
     *
     * @return array<int,array<int,mixed>>
     *
     * @throws DriverException
     */
    public function fetchAllNumeric() : array;

    /**
     * Returns an array containing all of the result set rows represented as associative arrays.
     *
     * @return array<int,array<string,mixed>>
     *
     * @throws DriverException
     */
    public function fetchAllAssociative() : array;

    /**
     * Returns an array containing the values of the first column of the result set.
     *
     * @return array<int,mixed>
     *
     * @throws DriverException
     */
    public function fetchColumn() : array;
}
