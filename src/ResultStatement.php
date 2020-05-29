<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\ResultStatement as DriverResultStatement;
use Traversable;

/**
 * DBAL-level ResultStatement interface.
 */
interface ResultStatement extends DriverResultStatement
{
    /**
     * Returns an iterator over the result set rows represented as numeric arrays.
     *
     * @return Traversable<int,array<int,mixed>>
     *
     * @throws DBALException
     */
    public function iterateNumeric() : Traversable;

    /**
     * Returns an iterator over the result set rows represented as associative arrays.
     *
     * @return Traversable<int,array<string,mixed>>
     *
     * @throws DBALException
     */
    public function iterateAssociative() : Traversable;

    /**
     * Returns an iterator over the values of the first column of the result set.
     *
     * @return Traversable<int,mixed>
     *
     * @throws DBALException
     */
    public function iterateColumn() : Traversable;
}
