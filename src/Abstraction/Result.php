<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Abstraction;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Traversable;

/**
 * Abstraction-level statement execution result. Provides additional methods on top
 * of the driver-level interface.
 */
interface Result extends DriverResult
{
    /**
     * Returns an iterator over the result set rows represented as numeric arrays.
     *
     * @return Traversable<int,array<int,mixed>>
     *
     * @throws DBALException
     */
    public function iterateNumeric(): Traversable;

    /**
     * Returns an iterator over the result set rows represented as associative arrays.
     *
     * @return Traversable<int,array<string,mixed>>
     *
     * @throws DBALException
     */
    public function iterateAssociative(): Traversable;

    /**
     * Returns an iterator over the values of the first column of the result set.
     *
     * @return Traversable<int,mixed>
     *
     * @throws DBALException
     */
    public function iterateColumn(): Traversable;
}
