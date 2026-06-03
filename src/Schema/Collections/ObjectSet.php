<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Collections;

use Doctrine\DBAL\Schema\Collections\Exception\ObjectAlreadyExists;
use Doctrine\DBAL\Schema\Collections\Exception\ObjectDoesNotExist;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

/**
 * A mutable set of objects where each object is uniquely identified by its {@link UnqualifiedName}.
 *
 * @internal
 *
 * @template E of object
 * @template-extends ReadableObjectSet<E>
 */
interface ObjectSet extends ReadableObjectSet
{
    /**
     * Adds the given element to the set.
     *
     * @phpstan-param E $element
     *
     * @throws ObjectAlreadyExists If an element with the same name already exists.
     */
    public function add(object $element): void;

    /**
     * Removes the element with the given name from the set.
     *
     * @throws ObjectDoesNotExist If no element with the given name exists.
     */
    public function remove(UnqualifiedName $elementName): void;

    /**
     * Modifies the element with the given name using the provided callable.
     *
     * @param callable(E): E $modification
     *
     * @throws ObjectDoesNotExist If no element with the given name exists.
     * @throws ObjectAlreadyExists If an element with the name after modification already exists.
     */
    public function modify(UnqualifiedName $elementName, callable $modification): void;

    /**
     * Clears the set, removing all elements.
     */
    public function clear(): void;
}
