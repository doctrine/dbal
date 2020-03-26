<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Traversable;

/**
 * Interface for the reading part of a prepare statement only.
 */
interface ResultStatement extends Traversable
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
     * Sets the fetch mode to use while iterating this statement.
     *
     * @param int   $fetchMode Controls how the next row will be returned to the caller.
     *                         The value must be one of the {@link \Doctrine\DBAL\FetchMode} constants.
     * @param mixed ...$args   Optional mode-specific arguments (see {@link self::fetchAll()}).
     */
    public function setFetchMode(int $fetchMode, ...$args) : void;

    /**
     * Returns the next row of a result set.
     *
     * @param int|null $fetchMode Controls how the next row will be returned to the caller.
     *                            The value must be one of the {@link \Doctrine\DBAL\FetchMode} constants,
     *                            defaulting to {@link \Doctrine\DBAL\FetchMode::MIXED}.
     * @param mixed    ...$args   Optional mode-specific arguments (see {@link self::fetchAll()}).
     *
     * @return mixed The return value of this method on success depends on the fetch mode. In all cases, FALSE is
     *               returned on failure.
     */
    public function fetch(?int $fetchMode = null, ...$args);

    /**
     * Returns an array containing all of the result set rows.
     *
     * @param int|null $fetchMode Controls how the next row will be returned to the caller.
     *                            The value must be one of the {@link \Doctrine\DBAL\FetchMode} constants,
     *                            defaulting to {@link \Doctrine\DBAL\FetchMode::MIXED}.
     * @param mixed    ...$args   Optional mode-specific arguments. Supported modes:
     *                            * {@link \Doctrine\DBAL\FetchMode::COLUMN}
     *                              1. The 0-indexed column to be returned.
     *                            * {@link \Doctrine\DBAL\FetchMode::CUSTOM_OBJECT}
     *                              1. The classname of the object to be created,
     *                              2. Array of constructor arguments
     *
     * @return mixed[]
     */
    public function fetchAll(?int $fetchMode = null, ...$args) : array;

    /**
     * Returns a single column from the next row of a result set or FALSE if there are no more rows.
     *
     * @param int $columnIndex 0-indexed number of the column you wish to retrieve from the row.
     *                         If no value is supplied, fetches the first column.
     *
     * @return mixed|false A single column in the next row of a result set, or FALSE if there are no more rows.
     */
    public function fetchColumn(int $columnIndex = 0);
}
