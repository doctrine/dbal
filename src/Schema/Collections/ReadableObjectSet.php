<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Collections;

use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use IteratorAggregate;

/**
 * A read-only view of a set of objects, each uniquely identified by its {@link UnqualifiedName}.
 *
 * @internal
 *
 * @template E of object
 * @template-extends IteratorAggregate<int, E>
 */
interface ReadableObjectSet extends IteratorAggregate
{
    /**
     * Checks if the set is empty.
     */
    public function isEmpty(): bool;

    /**
     * Returns the element with the given name. If no such element exists, null is returned.
     *
     * @phpstan-return E|null
     */
    public function get(UnqualifiedName $elementName): ?object;

    /**
     * Returns the elements of the set represented as a list.
     *
     * @phpstan-return list<E>
     */
    public function toList(): array;
}
