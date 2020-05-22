<?php

declare(strict_types=1);

namespace Doctrine\DBAL\ForwardCompatibility;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\ForwardCompatibility\Driver\ResultStatement as BaseResultStatement;
use Traversable;

/**
 * Forward compatibility extension for the DBAL ResultStatement interface.
 */
interface ResultStatement extends BaseResultStatement
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
