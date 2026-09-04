<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Types\Exception\TypeAlreadyRegistered;
use Doctrine\DBAL\Types\Exception\TypeNotFound;
use Doctrine\DBAL\Types\Exception\TypeNotRegistered;
use Doctrine\DBAL\Types\Exception\TypesAlreadyExists;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Exception\UnknownColumnType;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function array_key_exists;
use function array_search;
use function assert;
use function get_debug_type;
use function sprintf;

/**
 * The type registry is responsible for holding a map of all known DBAL types.
 *
 * @implements IteratorAggregate<string, Type>
 */
final class TypeRegistry implements TypeProvider, IteratorAggregate
{
    /** Map of type names and their corresponding class names. */
    private const BUILTIN_TYPES_MAP = [
        Types::ASCII_STRING         => AsciiStringType::class,
        Types::BIGINT               => BigIntType::class,
        Types::BINARY               => BinaryType::class,
        Types::BLOB                 => BlobType::class,
        Types::BOOLEAN              => BooleanType::class,
        Types::DATE_MUTABLE         => DateType::class,
        Types::DATE_IMMUTABLE       => DateImmutableType::class,
        Types::DATEINTERVAL         => DateIntervalType::class,
        Types::DATETIME_MUTABLE     => DateTimeType::class,
        Types::DATETIME_IMMUTABLE   => DateTimeImmutableType::class,
        Types::DATETIME_UTC_MUTABLE => DateTimeUtcType::class,
        Types::DATETIME_UTC_IMMUTABLE => DateTimeUtcImmutableType::class,
        Types::DATETIMETZ_MUTABLE   => DateTimeTzType::class,
        Types::DATETIMETZ_IMMUTABLE => DateTimeTzImmutableType::class,
        Types::DECIMAL              => DecimalType::class,
        Types::NUMBER               => NumberType::class,
        Types::ENUM                 => EnumType::class,
        Types::FLOAT                => FloatType::class,
        Types::GUID                 => GuidType::class,
        Types::INTEGER              => IntegerType::class,
        Types::JSON                 => JsonType::class,
        Types::JSON_OBJECT          => JsonObjectType::class,
        Types::JSONB                => JsonbType::class,
        Types::JSONB_OBJECT         => JsonbObjectType::class,
        Types::SIMPLE_ARRAY         => SimpleArrayType::class,
        Types::SMALLFLOAT           => SmallFloatType::class,
        Types::SMALLINT             => SmallIntType::class,
        Types::STRING               => StringType::class,
        Types::TEXT                 => TextType::class,
        Types::TIME_MUTABLE         => TimeType::class,
        Types::TIME_IMMUTABLE       => TimeImmutableType::class,
    ];

    /** @var array<string, Type> Resolved types, keyed by type name. Doubles as the resolution cache. */
    private array $instances = [];

    /**
     * Map of type names to the container service IDs providing them.
     *
     * @var array<string, string>
     */
    private array $serviceIds = [];

    private ?ContainerInterface $container = null;

    /**
     * Creates a registry pre-populated with all built-in types. Additional types passed via
     * {@param $types} are registered on top; if a name matches a built-in type it is
     * overridden rather than re-registered.
     *
     * A {@see ContainerInterface} can be passed instead of an array to lazy-load type instances
     * from a service container. In that case, {@param $serviceIds} maps type names to the
     * container service IDs providing them. Types are resolved on first access and cached.
     *
     * Each service must resolve to a distinct {@see Type} instance: mapping two type names to the
     * same service ID makes the second lookup fail with {@see TypeAlreadyRegistered}, because a
     * type instance may only be registered under a single name.
     *
     * @param array<string, Type>|ContainerInterface $types
     * @param array<string, string>|null             $serviceIds Map of type names to container service IDs.
     *                                                           Required when passing a container, in which case an
     *                                                           empty map means no types beyond the built-in ones.
     *
     * @throws TypeAlreadyRegistered
     * @throws TypesException
     */
    public function __construct(array|ContainerInterface $types = [], ?array $serviceIds = null)
    {
        if ($types instanceof ContainerInterface) {
            if ($serviceIds === null) {
                throw new InvalidArgumentException(sprintf(
                    'A map of type names to service IDs is required when passing a "%s".',
                    ContainerInterface::class,
                ));
            }

            $this->container  = $types;
            $this->serviceIds = $serviceIds;
        } else {
            if ($serviceIds !== null) {
                throw new InvalidArgumentException(sprintf(
                    'A map of type names to service IDs can only be used together with a "%s".',
                    ContainerInterface::class,
                ));
            }

            foreach ($types as $name => $type) {
                if (! $type instanceof Type) {
                    throw new InvalidArgumentException(sprintf(
                        'Unexpected value for type "%s", got "%s".',
                        $name,
                        get_debug_type($type),
                    ));
                }

                if ($this->findTypeName($type) !== null) {
                    throw TypeAlreadyRegistered::new($type);
                }

                $this->instances[$name] = $type;
            }
        }
    }

    /**
     * Finds a type by the given name.
     *
     * @throws TypesException
     */
    public function get(string $name): Type
    {
        $type = $this->instances[$name] ?? null;
        if ($type !== null) {
            return $type;
        }

        if (array_key_exists($name, $this->serviceIds)) {
            $container = $this->container;
            assert($container !== null);

            $serviceId = $this->serviceIds[$name];
            try {
                $type = $container->get($serviceId);

                if (! $type instanceof Type) {
                    throw new InvalidArgumentException(sprintf(
                        'Service "%s" registered for type "%s" must be an instance of "%s", got "%s".',
                        $serviceId,
                        $name,
                        Type::class,
                        get_debug_type($type),
                    ));
                }
            } catch (ContainerExceptionInterface $exception) {
                if (! $container->has($serviceId)) {
                    throw UnknownColumnType::new($name, $exception);
                }

                // @phpstan-ignore missingType.checkedException
                throw $exception;
            }
        } elseif (array_key_exists($name, self::BUILTIN_TYPES_MAP)) {
            $class = self::BUILTIN_TYPES_MAP[$name];
            $type  = new $class();
        } else {
            throw UnknownColumnType::new($name);
        }

        if ($this->findTypeName($type) !== null) {
            throw TypeAlreadyRegistered::new($type);
        }

        $this->instances[$name] = $type;

        return $type;
    }

    /**
     * Finds a name for the given type.
     *
     * @deprecated since doctrine/dbal 4.5. Track the type name instead of the instance. This method
     *             cannot be implemented once a type instance may be registered under several names.
     *
     * @throws TypesException
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
        return array_key_exists($name, $this->instances)
            || array_key_exists($name, $this->serviceIds)
            || array_key_exists($name, self::BUILTIN_TYPES_MAP);
    }

    /**
     * Registers a custom type to the type map.
     *
     * @throws TypesException
     */
    public function register(string $name, Type $type): void
    {
        if ($this->has($name)) {
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
     * @throws TypeNotFound
     * @throws TypeAlreadyRegistered
     */
    public function override(string $name, Type $type): void
    {
        if (! $this->has($name)) {
            throw TypeNotFound::new($name);
        }

        if (($this->findTypeName($type) ?? $name) !== $name) {
            throw TypeAlreadyRegistered::new($type);
        }

        // Dropping the service ID keeps an unresolved container-backed type from being
        // instantiated only to be discarded.
        unset($this->serviceIds[$name]);
        $this->instances[$name] = $type;
    }

    /**
     * Yields every known type, keyed by type name.
     *
     * Types that have not been resolved yet are instantiated as they are reached, so stopping the
     * iteration early leaves the remaining ones untouched.
     *
     * @return Generator<string, Type>
     *
     * @throws TypesException
     */
    public function getIterator(): Generator
    {
        foreach ($this->instances as $name => $type) {
            yield $name => $type;
        }

        // Resolving adds to $this->instances, which is what keeps each name yielded only once.
        foreach ($this->serviceIds as $name => $serviceId) {
            if (array_key_exists($name, $this->instances)) {
                continue;
            }

            yield $name => $this->get($name);
        }

        foreach (self::BUILTIN_TYPES_MAP as $name => $class) {
            if (array_key_exists($name, $this->instances) || array_key_exists($name, $this->serviceIds)) {
                continue;
            }

            yield $name => $this->get($name);
        }
    }

    private function findTypeName(Type $type): ?string
    {
        $name = array_search($type, $this->instances, true);

        return $name === false ? null : $name;
    }
}
