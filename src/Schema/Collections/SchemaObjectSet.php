<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Collections;

use Doctrine\DBAL\Schema\Collections\Exception\ObjectAlreadyExists;
use Doctrine\DBAL\Schema\Collections\Exception\ObjectDoesNotExist;
use Doctrine\DBAL\Schema\Exception\ImproperlyQualifiedName;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\NamedObject;

use function array_map;
use function array_values;

/**
 * A set of {@link NamedObject}s with names being {@link OptionallyQualifiedName}, all uniformly
 * qualified or uniformly unqualified — see the concrete subclasses.
 *
 * @internal
 *
 * @template E of NamedObject<OptionallyQualifiedName>
 *
 * @phpstan-consistent-constructor
 */
abstract class SchemaObjectSet
{
    /** @var array<string, E> */
    protected array $elements = [];

    public function isEmpty(): bool
    {
        return $this->elements === [];
    }

    public function has(OptionallyQualifiedName $name): bool
    {
        return isset($this->elements[$this->key($name)]);
    }

    /** @phpstan-return ?E */
    public function get(OptionallyQualifiedName $name): ?NamedObject
    {
        return $this->elements[$this->key($name)] ?? null;
    }

    /** @phpstan-return list<E> */
    public function toList(): array
    {
        return array_values($this->elements);
    }

    /**
     * @phpstan-param E $element
     *
     * @throws ObjectAlreadyExists
     */
    public function add(NamedObject $element, OptionallyQualifiedName $name): void
    {
        $key = $this->key($name);

        if (isset($this->elements[$key])) {
            throw ObjectAlreadyExists::new($name);
        }

        $this->elements[$key] = $element;
    }

    /** @throws ObjectDoesNotExist */
    public function remove(OptionallyQualifiedName $name): void
    {
        $key = $this->key($name);

        if (! isset($this->elements[$key])) {
            throw ObjectDoesNotExist::new($name);
        }

        unset($this->elements[$key]);
    }

    /**
     * @phpstan-param E $newElement
     *
     * @throws ObjectDoesNotExist
     * @throws ObjectAlreadyExists If the new key collides with an existing element other than the old one.
     */
    public function replace(
        OptionallyQualifiedName $oldName,
        OptionallyQualifiedName $newName,
        NamedObject $newElement,
    ): void {
        $oldKey = $this->key($oldName);
        if (! isset($this->elements[$oldKey])) {
            throw ObjectDoesNotExist::new($oldName);
        }

        $newKey = $this->key($newName);
        if ($newKey === $oldKey) {
            $this->elements[$oldKey] = $newElement;

            return;
        }

        if (isset($this->elements[$newKey])) {
            throw ObjectAlreadyExists::new($newName);
        }

        unset($this->elements[$oldKey]);
        $this->elements[$newKey] = $newElement;
    }

    public function clear(): void
    {
        $this->elements = [];
    }

    /** Returns a copy with independent storage and shared element references. */
    public function copy(): static
    {
        $copy           = new static();
        $copy->elements = $this->elements;

        /** @phpstan-ignore return.type */
        return $copy;
    }

    /** Deep-clones contained elements while their classes retain mutators. */
    public function __clone()
    {
        $this->elements = array_map(
            static fn (NamedObject $element): NamedObject => clone $element,
            $this->elements,
        );
    }

    /** @throws ImproperlyQualifiedName If the name's qualification form is not the one this set holds. */
    abstract protected function key(OptionallyQualifiedName $name): string;
}
