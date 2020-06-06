<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

/**
 * Driver-level result statement execution result.
 */
interface Result
{
    /**
     * Returns the next row of the result as a numeric array or FALSE if there are no more rows.
     *
     * @return array<int,mixed>|false
     *
     * @throws DriverException
     */
    public function fetchNumeric();

    /**
     * Returns the next row of the result as an associative array or FALSE if there are no more rows.
     *
     * @return array<string,mixed>|false
     *
     * @throws DriverException
     */
    public function fetchAssociative();

    /**
     * Returns the first value of the next row of the result or FALSE if there are no more rows.
     *
     * @return mixed|false
     *
     * @throws DriverException
     */
    public function fetchOne();

    /**
     * Returns an array containing all of the result rows represented as numeric arrays.
     *
     * @return array<int,array<int,mixed>>
     *
     * @throws DriverException
     */
    public function fetchAllNumeric(): array;

    /**
     * Returns an array containing all of the result rows represented as associative arrays.
     *
     * @return array<int,array<string,mixed>>
     *
     * @throws DriverException
     */
    public function fetchAllAssociative(): array;

    /**
     * Returns an array containing the values of the first column of the result.
     *
     * @return array<int,mixed>
     *
     * @throws DriverException
     */
    public function fetchFirstColumn(): array;

    /**
     * Returns the number of columns in the result
     *
     * @return int The number of columns in the result. If the columns cannot be counted,
     *             this method must return 0.
     */
    public function columnCount();

    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function closeCursor();
}
