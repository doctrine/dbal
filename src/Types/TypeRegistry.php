<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Exception\TypeNotFound;
use Doctrine\DBAL\Types\Exception\TypeNotRegistered;
use Doctrine\DBAL\Types\Exception\TypesAlreadyExists;
use Doctrine\DBAL\Types\Exception\UnknownColumnType;

use function array_search;

/**
 * The type registry is responsible for holding a map of all known DBAL types.
 */
final class TypeRegistry
{
    /**
     * Map of type names and their corresponding objects.
     *
     * @var array<string, Type>
     */
    private array $instances;

    /**
     * @param array<string, Type> $instances
     */
    public function __construct(array $instances = [])
    {
        $this->instances = $instances;
    }

    /**
     * Finds a type by the given name.
     *
     * @throws Exception
     */
    public function get(string $name): Type
    {
        if (! isset($this->instances[$name])) {
            throw UnknownColumnType::new($name);
        }

        return $this->instances[$name];
    }

    /**
     * Finds a name for the given type.
     *
     * @throws Exception
     */
    public function lookupName(Type $type): string
    {
        $name = array_search($type, $this->instances, true);

        if ($name === false) {
            throw TypeNotRegistered::new($type);
        }

        return $name;
    }

    /**
     * Checks if there is a type of the given name.
     */
    public function has(string $name): bool
    {
        return isset($this->instances[$name]);
    }

    /**
     * Registers a custom type to the type map.
     *
     * @throws Exception
     */
    public function register(string $name, Type $type): void
    {
        if (isset($this->instances[$name])) {
            throw TypesAlreadyExists::new($name);
        }

        $this->instances[$name] = $type;
    }

    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @throws Exception
     */
    public function override(string $name, Type $type): void
    {
        if (! isset($this->instances[$name])) {
            throw TypeNotFound::new($name);
        }

        $this->instances[$name] = $type;
    }

    /**
     * Gets the map of all registered types and their corresponding type instances.
     *
     * @internal
     *
     * @return array<string, Type>
     */
    public function getMap(): array
    {
        return $this->instances;
    }
}
