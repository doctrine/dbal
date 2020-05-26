<?php

declare(strict_types=1);

namespace Doctrine\DBAL\ForwardCompatibility\Driver;

use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\ResultStatement as BaseResultStatement;

/**
 * Forward compatibility extension for the ResultStatement interface.
 */
interface ResultStatement extends BaseResultStatement
{
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
}
