<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Types\Exception\TypeAlreadyRegistered;
use Doctrine\DBAL\Types\Exception\TypeArgumentCountError;
use Doctrine\DBAL\Types\Exception\TypeNotFound;
use Doctrine\DBAL\Types\Exception\TypeNotRegistered;
use Doctrine\DBAL\Types\Exception\TypesAlreadyExists;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Exception\UnknownColumnType;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use ReflectionClass;
use Symfony\Contracts\Service\ServiceProviderInterface;

use function array_key_exists;
use function array_keys;
use function array_search;
use function get_debug_type;
use function is_subclass_of;
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
        Types::ASCII_STRING           => AsciiStringType::class,
        Types::BIGINT                 => BigIntType::class,
        Types::BINARY                 => BinaryType::class,
        Types::BLOB                   => BlobType::class,
        Types::BOOLEAN                => BooleanType::class,
        Types::DATE_MUTABLE           => DateType::class,
        Types::DATE_IMMUTABLE         => DateImmutableType::class,
        Types::DATEINTERVAL           => DateIntervalType::class,
        Types::DATETIME_MUTABLE       => DateTimeType::class,
        Types::DATETIME_IMMUTABLE     => DateTimeImmutableType::class,
        Types::DATETIME_UTC_MUTABLE   => DateTimeUtcType::class,
        Types::DATETIME_UTC_IMMUTABLE => DateTimeUtcImmutableType::class,
        Types::DATETIMETZ_MUTABLE     => DateTimeTzType::class,
        Types::DATETIMETZ_IMMUTABLE   => DateTimeTzImmutableType::class,
        Types::DECIMAL                => DecimalType::class,
        Types::NUMBER                 => NumberType::class,
        Types::ENUM                   => EnumType::class,
        Types::FLOAT                  => FloatType::class,
        Types::GUID                   => GuidType::class,
        Types::INTEGER                => IntegerType::class,
        Types::JSON                   => JsonType::class,
        Types::JSON_OBJECT            => JsonObjectType::class,
        Types::JSONB                  => JsonbType::class,
        Types::JSONB_OBJECT           => JsonbObjectType::class,
        Types::SIMPLE_ARRAY           => SimpleArrayType::class,
        Types::SMALLFLOAT             => SmallFloatType::class,
        Types::SMALLINT               => SmallIntType::class,
        Types::STRING                 => StringType::class,
        Types::TEXT                   => TextType::class,
        Types::TIME_MUTABLE           => TimeType::class,
        Types::TIME_IMMUTABLE         => TimeImmutableType::class,
    ];

    /** @var array<string, Type> Resolved type objects, keyed by type name. Doubles as the resolution cache. */
    private array $typeObjects = [];

    /** @var array<string, class-string<Type>> Type classes registered by name, not instantiated yet. */
    private array $typesMap = [];

    /** @var ServiceProviderInterface<Type>|null */
    private ?ServiceProviderInterface $services = null;

    /**
     * Creates a registry pre-populated with all built-in types. Additional types passed via
     * {@param $types} are registered on top; if a name matches a built-in type it is
     * overridden rather than re-registered.
     *
     * Each entry may be a {@see Type} instance or a class string. Class strings are validated up
     * front but instantiated lazily on first access, so registering a type by class name does not
     * pay the construction cost until it is actually needed.
     *
     * A {@see ServiceProviderInterface} can be passed instead of an array to lazy-load type
     * instances from a service container. Each service ID acts as the type name, so a service
     * providing type "foo" is exposed as type "foo". Services are resolved on every access and not
     * memoized, so a provider may return a fresh instance between requests.
     *
     * @param array<string, Type|class-string<Type>>|ServiceProviderInterface<Type> $types
     *
     * @throws TypeAlreadyRegistered
     * @throws TypesException
     */
    public function __construct(array|ServiceProviderInterface $types = [])
    {
        if ($types instanceof ServiceProviderInterface) {
            $this->services = $types;

            return;
        }

        foreach ($types as $name => $type) {
            $this->set($name, $type);
        }
    }

    /**
     * Finds a type by the given name.
     *
     * @throws TypesException
     */
    public function get(string $name): Type
    {
        $type = $this->typeObjects[$name] ?? null;
        if ($type !== null) {
            return $type;
        }

        if (array_key_exists($name, $this->typesMap)) {
            return $this->typeObjects[$name] = new ($this->typesMap[$name])();
        }

        if ($this->services?->has($name) ?? false) {
            // Resolve the service on every call. The provider may return a fresh instance between
            // requests, so the result is not memoized. Service-backed types are therefore not
            // visible to the deprecated lookupName(), which is removed in DBAL 5.0.
            // @phpstan-ignore missingType.checkedException
            $type = $this->services->get($name);

            if (! $type instanceof Type) {
                throw new InvalidArgumentException(sprintf(
                    'Service registered for type "%s" must be an instance of "%s", got "%s".',
                    $name,
                    Type::class,
                    get_debug_type($type),
                ));
            }

            return $type;
        }

        if (array_key_exists($name, self::BUILTIN_TYPES_MAP)) {
            return $this->typeObjects[$name] = new (self::BUILTIN_TYPES_MAP[$name])();
        }

        throw UnknownColumnType::new($name);
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
        return array_key_exists($name, $this->typeObjects)
            || array_key_exists($name, $this->typesMap)
            || array_key_exists($name, self::BUILTIN_TYPES_MAP)
            || ($this->services?->has($name) ?? false);
    }

    /**
     * Registers a custom type to the type map.
     *
     * @param class-string<Type>|Type $type The custom type or the class name of the custom type.
     *     Class names are validated up front and instantiated lazily on first access.
     *
     * @throws TypesException
     */
    public function register(string $name, string|Type $type): void
    {
        if ($this->has($name)) {
            throw TypesAlreadyExists::new($name);
        }

        $this->set($name, $type);
    }

    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @param class-string<Type>|Type $type The custom type or the class name of the custom type.
     *     Class names are validated up front and instantiated lazily on first access.
     *
     * @throws TypeNotFound
     * @throws TypeAlreadyRegistered
     * @throws TypesException
     */
    public function override(string $name, string|Type $type): void
    {
        if (! $this->has($name)) {
            throw TypeNotFound::new($name);
        }

        $this->set($name, $type);
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
        $names = array_keys(
            self::BUILTIN_TYPES_MAP
                + $this->typeObjects
                + $this->typesMap
                + ($this->services?->getProvidedServices() ?? []),
        );

        foreach ($names as $name) {
            yield $name => $this->get($name);
        }
    }

    /**
     * Stores a type under the given name, replacing any previous entry, without the "already
     * exists" guard. Used by the constructor, which is allowed to override built-in names.
     *
     * @param class-string<Type>|Type $type The type or its class name, instantiated lazily.
     *
     * @throws TypeAlreadyRegistered
     */
    private function set(string $name, string|Type $type): void
    {
        if ($type instanceof Type) {
            // Reject an instance already registered under another name, but allow re-registering it
            // under the same name (used by override()).
            if (($this->findTypeName($type) ?? $name) !== $name) {
                throw TypeAlreadyRegistered::new($type);
            }

            $this->typeObjects[$name] = $type;
            unset($this->typesMap[$name]);

            return;
        }

        // @phpstan-ignore missingType.checkedException
        $this->typesMap[$name] = $this->validateClassString($name, $type);
        unset($this->typeObjects[$name]);
    }

    /**
     * Validates that a class string can be used to lazy-load a {@see Type} instance.
     *
     * @param class-string $class
     *
     * @return class-string<Type>
     *
     * @throws TypesException
     */
    private function validateClassString(string $name, string $class): string
    {
        if (! is_subclass_of($class, Type::class)) {
            throw new InvalidArgumentException(sprintf(
                'Type class "%s" registered for type "%s" must be a subclass of "%s".',
                $class,
                $name,
                Type::class,
            ));
        }

        $reflectionClass = new ReflectionClass($class);
        if (! $reflectionClass->isInstantiable()) {
            throw new InvalidArgumentException(sprintf(
                'Type class "%s" registered for type "%s" is not instantiable. Register an instance instead.',
                $class,
                $name,
            ));
        }

        $constructor = $reflectionClass->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw TypeArgumentCountError::fromClass($name, $class);
        }

        return $class;
    }

    private function findTypeName(Type $type): ?string
    {
        $name = array_search($type, $this->typeObjects, true);

        return $name === false ? null : $name;
    }
}
