<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Collections;

use Doctrine\DBAL\Schema\Collections\Exception\ObjectAlreadyExists;
use Doctrine\DBAL\Schema\Collections\Exception\ObjectDoesNotExist;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\OptionallyNamedObject;
use Traversable;

use function array_splice;
use function count;
use function strtolower;

/**
 * An ordered set of {@link OptionallyNamedObject}s with names being {@link UnqualifiedName}.
 *
 * New objects are added to the end of the set. The order of elements is preserved during modification.
 *
 * If an object is unnamed, it can be added to the set but cannot be referenced and, therefore, modified or removed
 * from the set. Two unnamed objects are considered as having different names.
 *
 * @internal
 *
 * @template E of OptionallyNamedObject<UnqualifiedName>
 * @template-implements ObjectSet<E>
 */
final class OptionallyUnqualifiedNamedObjectSet implements ObjectSet
{
    /** @var list<E> */
    private array $elements = [];

    /** @var array<string, int> */
    private array $elementPositionsByKey = [];

    /** @phpstan-param E ...$elements */
    public function __construct(OptionallyNamedObject ...$elements)
    {
        foreach ($elements as $element) {
            $this->add($element);
        }
    }

    public function isEmpty(): bool
    {
        return count($this->elements) === 0;
    }

    public function get(UnqualifiedName $elementName): ?OptionallyNamedObject
    {
        $key = $this->getKey($elementName);

        if (isset($this->elementPositionsByKey[$key])) {
            return $this->elements[$this->elementPositionsByKey[$key]];
        }

        return null;
    }

    public function add(object $element): void
    {
        $elementName = $element->getObjectName();

        if ($elementName !== null) {
            $key = $this->getKey($elementName);

            if (isset($this->elementPositionsByKey[$key])) {
                throw ObjectAlreadyExists::new($elementName);
            }

            $this->elementPositionsByKey[$key] = count($this->elements);
        }

        $this->elements[] = $element;
    }

    public function remove(UnqualifiedName $elementName): void
    {
        $key = $this->getKey($elementName);

        if (! isset($this->elementPositionsByKey[$key])) {
            throw ObjectDoesNotExist::new($elementName);
        }

        $this->removeByKey($key);
    }

    public function modify(UnqualifiedName $elementName, callable $modification): void
    {
        $key = $this->getKey($elementName);

        if (! isset($this->elementPositionsByKey[$key])) {
            throw ObjectDoesNotExist::new($elementName);
        }

        $position = $this->elementPositionsByKey[$key];

        $this->replace($key, $position, $modification($this->elements[$position]));
    }

    public function clear(): void
    {
        $this->elements = $this->elementPositionsByKey = [];
    }

    /** {@inheritDoc} */
    public function toList(): array
    {
        return $this->elements;
    }

    /** {@inheritDoc} */
    public function getIterator(): Traversable
    {
        yield from $this->elements;
    }

    /**
     * Replaces the element corresponding to the old key with the provided element.
     *
     * @phpstan-param E $element
     *
     * @throws ObjectAlreadyExists If an element with the same name as the element name already exists.
     */
    private function replace(string $oldKey, int $position, OptionallyNamedObject $element): void
    {
        $elementName = $element->getObjectName();

        if ($elementName !== null) {
            $newKey = $this->getKey($elementName);

            if ($newKey !== $oldKey) {
                if (isset($this->elementPositionsByKey[$newKey])) {
                    throw ObjectAlreadyExists::new($elementName);
                }

                unset($this->elementPositionsByKey[$oldKey]);

                $this->elementPositionsByKey[$newKey] = $position;
            }
        } else {
            unset($this->elementPositionsByKey[$oldKey]);
        }

        // @phpstan-ignore assign.propertyType
        $this->elements[$position] = $element;
    }

    private function removeByKey(string $key): void
    {
        $position = $this->elementPositionsByKey[$key];

        array_splice($this->elements, $position, 1);
        unset($this->elementPositionsByKey[$key]);

        foreach ($this->elementPositionsByKey as $elementKey => $elementPosition) {
            if ($elementPosition <= $position) {
                continue;
            }

            $this->elementPositionsByKey[$elementKey]--;
        }
    }

    private function getKey(UnqualifiedName $name): string
    {
        return strtolower($name->getIdentifier()->getValue());
    }
}
