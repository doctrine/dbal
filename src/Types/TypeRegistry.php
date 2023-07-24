<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Exception\TypeAlreadyRegistered;
use Doctrine\DBAL\Types\Exception\TypeNotFound;
use Doctrine\DBAL\Types\Exception\TypeNotRegistered;
use Doctrine\DBAL\Types\Exception\TypesAlreadyExists;
use Doctrine\DBAL\Types\Exception\UnknownColumnType;

use function spl_object_id;

/**
 * The type registry is responsible for holding a map of all known DBAL types.
 */
final class TypeRegistry
{
    /** @var array<string, Type> Map of type names and their corresponding flyweight objects. */
    private array $instances;
    /** @var array<int, string> */
    private array $instancesReverseIndex;

    /** @param array<string, Type> $instances */
    public function __construct(array $instances = [])
    {
        $this->instances             = [];
        $this->instancesReverseIndex = [];
        foreach ($instances as $name => $type) {
            $this->register($name, $type);
        }
    }

    /**
     * Finds a type by the given name.
     *
     * @throws Exception
     */
    public function get(string $name): Type
    {
        $type = $this->instances[$name] ?? null;
        if ($type === null) {
            throw UnknownColumnType::new($name);
        }

        return $type;
    }

    /**
     * Finds a name for the given type.
     *
     * @throws Exception
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
     * @throws Exception
     */
    public function register(string $name, Type $type): void
    {
        if (isset($this->instances[$name])) {
            throw TypesAlreadyExists::new($name);
        }

        if ($this->findTypeName($type) !== null) {
            throw TypeAlreadyRegistered::new($type);
        }

        $this->instances[$name]                            = $type;
        $this->instancesReverseIndex[spl_object_id($type)] = $name;
    }

    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @throws Exception
     */
    public function override(string $name, Type $type): void
    {
        $origType = $this->instances[$name] ?? null;
        if ($origType === null) {
            throw TypeNotFound::new($name);
        }

        if (($this->findTypeName($type) ?? $name) !== $name) {
            throw TypeAlreadyRegistered::new($type);
        }

        unset($this->instancesReverseIndex[spl_object_id($origType)]);
        $this->instances[$name]                            = $type;
        $this->instancesReverseIndex[spl_object_id($type)] = $name;
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
        return $this->instancesReverseIndex[spl_object_id($type)] ?? null;
    }
}
