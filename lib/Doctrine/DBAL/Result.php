<?php

namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\Exception;
use Traversable;

/**
 * This interfaces contains methods allowing forward compatibility with v3.0 Result
 *
 * @see https://github.com/doctrine/dbal/blob/3.0.x/src/Result.php
 */
interface Result extends Abstraction\Result
{
    /**
     * Returns an array containing the values of the first column of the result.
     *
     * @return array<mixed,mixed>
     *
     * @throws Exception
     */
    public function fetchAllKeyValue(): array;

    /**
     * Returns an associative array with the keys mapped to the first column and the values being
     * an associative array representing the rest of the columns and their values.
     *
     * @return array<mixed,array<string,mixed>>
     *
     * @throws Exception
     */
    public function fetchAllAssociativeIndexed(): array;

    /**
     * Returns an iterator over the result set with the values of the first column of the result
     *
     * @return Traversable<mixed,mixed>
     *
     * @throws Exception
     */
    public function iterateKeyValue(): Traversable;

    /**
     * Returns an iterator over the result set with the keys mapped to the first column and the values being
     * an associative array representing the rest of the columns and their values.
     *
     * @return Traversable<mixed,array<string,mixed>>
     *
     * @throws Exception
     */
    public function iterateAssociativeIndexed(): Traversable;
}
