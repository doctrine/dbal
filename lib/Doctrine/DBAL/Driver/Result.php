<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver\ResultStatement as BaseResultStatement;

/**
 * Driver-level result statement execution result.
 */
interface Result extends BaseResultStatement
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
}
