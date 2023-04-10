<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Exception;

use function array_search;
use function in_array;

/**
 * The type registry is responsible for holding a map of all known DBAL types.
 * The types are stored using the flyweight pattern so that one type only exists as exactly one instance.
 */
final class TypeRegistry
{
    /**
     * @var array<string, Type> Map of type names and their corresponding flyweight objects.
     * @psalm-var array<Types::*, Type>
     */
    private array $instances;

    /**
     * @param array<string, Type> $instances
     * @psalm-param array<Types::*, Type> $instances
     */
    public function __construct(array $instances = [])
    {
        $this->instances = $instances;
    }

    /**
     * Finds a type by the given name.
     *
     * @psalm-param Types::* $name
     *
     * @throws Exception
     */
    public function get(string $name): Type
    {
        if (! isset($this->instances[$name])) {
            throw Exception::unknownColumnType($name);
        }

        return $this->instances[$name];
    }

    /**
     * Finds a name for the given type.
     *
     * @psalm-return Types::* $name
     *
     * @throws Exception
     */
    public function lookupName(Type $type): string
    {
        $name = $this->findTypeName($type);

        if ($name === null) {
            throw Exception::typeNotRegistered($type);
        }

        return $name;
    }

    /**
     * Checks if there is a type of the given name.
     *
     * @psalm-param Types::* $name
     */
    public function has(string $name): bool
    {
        return isset($this->instances[$name]);
    }

    /**
     * Registers a custom type to the type map.
     *
     * @psalm-param Types::* $name
     *
     * @throws Exception
     */
    public function register(string $name, Type $type): void
    {
        if (isset($this->instances[$name])) {
            throw Exception::typeExists($name);
        }

        if ($this->findTypeName($type) !== null) {
            throw Exception::typeAlreadyRegistered($type);
        }

        $this->instances[$name] = $type;
    }

    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @psalm-param Types::* $name
     *
     * @throws Exception
     */
    public function override(string $name, Type $type): void
    {
        if (! isset($this->instances[$name])) {
            throw Exception::typeNotFound($name);
        }

        if (! in_array($this->findTypeName($type), [$name, null], true)) {
            throw Exception::typeAlreadyRegistered($type);
        }

        $this->instances[$name] = $type;
    }

    /**
     * Gets the map of all registered types and their corresponding type instances.
     *
     * @internal
     *
     * @return array<string, Type>
     * @psalm-return array<Types::*, Type>
     */
    public function getMap(): array
    {
        return $this->instances;
    }

    /** @psalm-return Types::*|null */
    private function findTypeName(Type $type): ?string
    {
        $name = array_search($type, $this->instances, true);

        if ($name === false) {
            return null;
        }

        return $name;
    }
}
