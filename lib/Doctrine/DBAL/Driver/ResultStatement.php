<?php

namespace Doctrine\DBAL\Driver;

/**
 * Interface for the reading part of a prepare statement only.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
interface ResultStatement extends \Traversable
{
    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function closeCursor();

    /**
     * Returns the number of columns in the result set
     *
     * @return integer The number of columns in the result set represented
     *                 by the PDOStatement object. If there is no result set,
     *                 this method should return 0.
     */
    public function columnCount();

    /**
     * Sets the fetch mode to use while iterating this statement.
     *
     * @param int   $fetchMode    Controls how the next row will be returned to the caller.
     *                            The value must be one of the {@link \Doctrine\DBAL\FetchMode} constants.
     * @param array $args         Optional mode-specific arguments (see {@link self::fetchAll()}).
     *
     * @return boolean
     */
    public function setFetchMode($fetchMode, ...$args);

    /**
     * Returns the next row of a result set.
     *
     * @param int|null $fetchMode    Controls how the next row will be returned to the caller.
     *                               The value must be one of the {@link \Doctrine\DBAL\FetchMode} constants,
     *                               defaulting to {@link \Doctrine\DBAL\FetchMode::MIXED}.
     * @param array    $args         Optional mode-specific arguments (see {@link self::fetchAll()}).
     *
     * @return mixed The return value of this method on success depends on the fetch mode. In all cases, FALSE is
     *               returned on failure.
     */
    public function fetch($fetchMode = null, ...$args);

    /**
     * Returns an array containing all of the result set rows.
     *
     * @param int|null $fetchMode     Controls how the next row will be returned to the caller.
     *                                The value must be one of the {@link \Doctrine\DBAL\FetchMode} constants,
     *                                defaulting to {@link \Doctrine\DBAL\FetchMode::MIXED}.
     * @param array    $args          Optional mode-specific arguments. Supported modes:
     *                                * {@link \Doctrine\DBAL\FetchMode::COLUMN}
     *                                  1. The 0-indexed column to be returned.
     *                                * {@link \Doctrine\DBAL\FetchMode::CUSTOM_OBJECT}
     *                                  1. The class name of the object to be created,
     *                                  2. Array of constructor arguments
     *
     * @return array
     */
    public function fetchAll($fetchMode = null, ...$args);

    /**
     * Returns a single column from the next row of a result set or FALSE if there are no more rows.
     *
     * @param integer $columnIndex 0-indexed number of the column you wish to retrieve from the row.
     *                             If no value is supplied, PDOStatement->fetchColumn()
     *                             fetches the first column.
     *
     * @return string|boolean A single column in the next row of a result set, or FALSE if there are no more rows.
     */
    public function fetchColumn($columnIndex = 0);
}
