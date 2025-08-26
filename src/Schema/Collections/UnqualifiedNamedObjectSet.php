<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Collections;

use Doctrine\DBAL\Schema\Collections\Exception\ObjectAlreadyExists;
use Doctrine\DBAL\Schema\Collections\Exception\ObjectDoesNotExist;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\NamedObject;
use Traversable;

use function array_combine;
use function array_keys;
use function array_search;
use function array_values;
use function assert;
use function count;
use function strtolower;

/**
 * An ordered set of {@link NamedObject}s with names being {@link UnqualifiedName}.
 *
 * New objects are added to the end of the set. The order of elements is preserved during modification.
 *
 * @internal
 *
 * @template E of NamedObject<UnqualifiedName>
 * @template-implements ObjectSet<E>
 */
final class UnqualifiedNamedObjectSet implements ObjectSet
{
    /** @var array<string, E> */
    private array $elements = [];

    /** @phpstan-param E ...$elements */
    public function __construct(NamedObject ...$elements)
    {
        foreach ($elements as $element) {
            $this->add($element);
        }
    }

    public function isEmpty(): bool
    {
        return count($this->elements) === 0;
    }

    public function get(UnqualifiedName $elementName): ?NamedObject
    {
        $key = $this->getKey($elementName);

        return $this->elements[$key] ?? null;
    }

    public function add(object $element): void
    {
        $elementName = $element->getObjectName();
        $key         = $this->getKey($elementName);

        if (isset($this->elements[$key])) {
            throw ObjectAlreadyExists::new($elementName);
        }

        $this->elements[$key] = $element;
    }

    public function remove(UnqualifiedName $elementName): void
    {
        $key = $this->getKey($elementName);

        if (! isset($this->elements[$key])) {
            throw ObjectDoesNotExist::new($elementName);
        }

        unset($this->elements[$key]);
    }

    public function modify(UnqualifiedName $elementName, callable $modification): void
    {
        $key = $this->getKey($elementName);

        if (! isset($this->elements[$key])) {
            throw ObjectDoesNotExist::new($elementName);
        }

        $this->replace($key, $modification($this->elements[$key]));
    }

    public function clear(): void
    {
        $this->elements = [];
    }

    /** {@inheritDoc} */
    public function toList(): array
    {
        return array_values($this->elements);
    }

    /** {@inheritDoc} */
    public function getIterator(): Traversable
    {
        foreach ($this->elements as $element) {
            yield $element;
        }
    }

    /**
     * Replaces the element corresponding to the old key with the provided element. The position of the element in the
     * set is preserved.
     *
     * @phpstan-param E $element
     *
     * @throws ObjectAlreadyExists If an element with the same name as the element name already exists.
     */
    private function replace(string $oldKey, NamedObject $element): void
    {
        $elementName = $element->getObjectName();
        $newKey      = $this->getKey($elementName);

        if ($newKey === $oldKey) {
            $this->elements[$oldKey] = $element;

            return;
        }

        if (isset($this->elements[$newKey])) {
            throw ObjectAlreadyExists::new($elementName);
        }

        $keys   = array_keys($this->elements);
        $values = array_values($this->elements);

        $position = array_search($oldKey, $keys, true);
        assert($position !== false);

        $keys[$position]   = $newKey;
        $values[$position] = $element;

        $this->elements = array_combine($keys, $values);
    }

    private function getKey(UnqualifiedName $name): string
    {
        return strtolower($name->getIdentifier()->getValue());
    }
}
