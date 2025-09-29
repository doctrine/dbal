<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Collections;

use Doctrine\DBAL\Schema\Collections\Exception\SetAlreadyContainsName;
use Doctrine\DBAL\Schema\Collections\Exception\SetDoesNotContainName;
use Doctrine\DBAL\Schema\Name;
use IteratorAggregate;

/**
 * A set of unique {@see Name}s.
 *
 * @internal
 *
 * @template N of Name
 * @template-extends IteratorAggregate<int, N>
 */
interface NameSet extends IteratorAggregate
{
    /**
     * Returns whether the set contains the given name.
     *
     * @phpstan-param N $name
     */
    public function contains(Name $name): bool;

    /**
     * Adds the given name to the set.
     *
     * @phpstan-param N $name
     *
     * @throws SetAlreadyContainsName If the set already contains the name.
     */
    public function add(Name $name): void;

    /**
     * Removes the given name from the set.
     *
     * @phpstan-param N $name
     *
     * @throws SetDoesNotContainName If the set does not contain the name.
     */
    public function remove(Name $name): void;
}
