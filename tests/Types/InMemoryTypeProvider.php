<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Exception\UnknownColumnType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeProvider;
use IteratorAggregate;
use Traversable;

use function array_key_exists;

/** @implements IteratorAggregate<string, Type> */
final class InMemoryTypeProvider implements TypeProvider, IteratorAggregate
{
    /** @param array<string, Type> $types */
    public function __construct(private readonly array $types)
    {
    }

    public function get(string $name): Type
    {
        if (! array_key_exists($name, $this->types)) {
            throw UnknownColumnType::new($name);
        }

        return $this->types[$name];
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->types);
    }

    /**
     * @return Traversable<string, Type>
     *
     * @throws TypesException
     */
    public function getIterator(): Traversable
    {
        yield from $this->types;
    }
}
