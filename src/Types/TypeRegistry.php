<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Types\Exception\TypeAlreadyRegistered;
use Doctrine\DBAL\Types\Exception\TypeNotFound;
use Doctrine\DBAL\Types\Exception\TypeNotRegistered;
use Doctrine\DBAL\Types\Exception\TypesAlreadyExists;
use Doctrine\DBAL\Types\Exception\UnknownColumnType;

use function array_search;
use function in_array;

/**
 * The type registry is responsible for holding a map of all known DBAL types.
 * The types are stored using the flyweight pattern so that one type only exists as exactly one instance.
 *
 * @internal TypeRegistry exists for forward compatibility, its API should not be considered stable.
 */
final class TypeRegistry
{
    /**
     * Map of type names and their corresponding flyweight objects.
     *
     * @var array<string, Type>
     */
    private $instances = [];

    /**
     * Finds a type by the given name.
     *
     * @throws DBALException
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
     * @throws DBALException
     */
    public function lookupName(Type $type): string
    {
        $name = $this->findTypeName($type);

        if ($name === null) {
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
     * @throws DBALException
     */
    public function register(string $name, Type $type): void
    {
        if (isset($this->instances[$name])) {
            throw TypesAlreadyExists::new($name);
        }

        if ($this->findTypeName($type) !== null) {
            throw TypeAlreadyRegistered::new($type);
        }

        $this->instances[$name] = $type;
    }

    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @throws DBALException
     */
    public function override(string $name, Type $type): void
    {
        if (! isset($this->instances[$name])) {
            throw TypeNotFound::new($name);
        }

        if (! in_array($this->findTypeName($type), [$name, null], true)) {
            throw TypeAlreadyRegistered::new($type);
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

    private function findTypeName(Type $type): ?string
    {
        $name = array_search($type, $this->instances, true);

        if ($name === false) {
            return null;
        }

        return $name;
    }
}
