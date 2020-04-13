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
     * @param int $fetchMode Controls how the next row will be returned to the caller.
     *                       The value must be one of the {@link \Doctrine\DBAL\FetchMode} constants.
     */
    public function setFetchMode(int $fetchMode) : void;

    /**
     * Returns the next row of a result set.
     *
     * @param int|null $fetchMode Controls how the next row will be returned to the caller.
     *                            The value must be one of the {@link \Doctrine\DBAL\FetchMode} constants,
     *                            defaulting to {@link \Doctrine\DBAL\FetchMode::MIXED}.
     *
     * @return mixed The return value of this method on success depends on the fetch mode. In all cases, FALSE is
     *               returned on failure.
     */
    public function fetch(?int $fetchMode = null);

    /**
     * Returns an array containing all of the result set rows.
     *
     * @param int|null $fetchMode Controls how the next row will be returned to the caller.
     *                            The value must be one of the {@link \Doctrine\DBAL\FetchMode} constants,
     *                            defaulting to {@link \Doctrine\DBAL\FetchMode::MIXED}.
     *
     * @return mixed[]
     */
    public function fetchAll(?int $fetchMode = null) : array;

    /**
     * Returns a single column from the next row of a result set or FALSE if there are no more rows.
     *
     * @return mixed|false A single column in the next row of a result set, or FALSE if there are no more rows.
     */
    public function fetchColumn();
}
