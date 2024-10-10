<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use function array_map;

/**
 * The base class for so-called Doctrine mapping types.
 *
 * A Type object is obtained by calling the static {@see getType()} method.
 */
abstract class Type
{
    /**
     * The map of supported doctrine mapping types.
     */
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
        Types::DATETIMETZ_MUTABLE   => DateTimeTzType::class,
        Types::DATETIMETZ_IMMUTABLE => DateTimeTzImmutableType::class,
        Types::DECIMAL              => DecimalType::class,
        Types::ENUM                 => EnumType::class,
        Types::FLOAT                => FloatType::class,
        Types::GUID                 => GuidType::class,
        Types::INTEGER              => IntegerType::class,
        Types::JSON                 => JsonType::class,
        Types::SIMPLE_ARRAY         => SimpleArrayType::class,
        Types::SMALLFLOAT           => SmallFloatType::class,
        Types::SMALLINT             => SmallIntType::class,
        Types::STRING               => StringType::class,
        Types::TEXT                 => TextType::class,
        Types::TIME_MUTABLE         => TimeType::class,
        Types::TIME_IMMUTABLE       => TimeImmutableType::class,
    ];

    private static ?TypeRegistry $typeRegistry = null;

    /** @internal Do not instantiate directly - use {@see Type::addType()} method instead. */
    final public function __construct()
    {
    }

    /**
     * Converts a value from its PHP representation to its database representation
     * of this type.
     *
     * @param mixed            $value    The value to convert.
     * @param AbstractPlatform $platform The currently used database platform.
     *
     * @return mixed The database representation of the value.
     *
     * @throws ConversionException
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return $value;
    }

    /**
     * Converts a value from its database representation to its PHP representation
     * of this type.
     *
     * @param mixed            $value    The value to convert.
     * @param AbstractPlatform $platform The currently used database platform.
     *
     * @return mixed The PHP representation of the value.
     *
     * @throws ConversionException
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return $value;
    }

    /**
     * Gets the SQL declaration snippet for a column of this type.
     *
     * @param array<string, mixed> $column   The column definition
     * @param AbstractPlatform     $platform The currently used database platform.
     */
    abstract public function getSQLDeclaration(array $column, AbstractPlatform $platform): string;

    final public static function getTypeRegistry(): TypeRegistry
    {
        return self::$typeRegistry ??= self::createTypeRegistry();
    }

    private static function createTypeRegistry(): TypeRegistry
    {
        $instances = [];

        foreach (self::BUILTIN_TYPES_MAP as $name => $class) {
            $instances[$name] = new $class();
        }

        return new TypeRegistry($instances);
    }

    /**
     * Factory method to create type instances.
     *
     * @param string $name The name of the type.
     *
     * @throws Exception
     */
    public static function getType(string $name): self
    {
        return self::getTypeRegistry()->get($name);
    }

    /**
     * Finds a name for the given type.
     *
     * @throws Exception
     */
    public static function lookupName(self $type): string
    {
        return self::getTypeRegistry()->lookupName($type);
    }

    /**
     * Adds a custom type to the type map.
     *
     * @param string             $name      The name of the type.
     * @param class-string<Type> $className The class name of the custom type.
     *
     * @throws Exception
     */
    public static function addType(string $name, string $className): void
    {
        self::getTypeRegistry()->register($name, new $className());
    }

    /**
     * Checks if exists support for a type.
     *
     * @param string $name The name of the type.
     *
     * @return bool TRUE if type is supported; FALSE otherwise.
     */
    public static function hasType(string $name): bool
    {
        return self::getTypeRegistry()->has($name);
    }

    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @param class-string<Type> $className
     *
     * @throws Exception
     */
    public static function overrideType(string $name, string $className): void
    {
        self::getTypeRegistry()->override($name, new $className());
    }

    /**
     * Gets the (preferred) binding type for values of this type that
     * can be used when binding parameters to prepared statements.
     */
    public function getBindingType(): ParameterType
    {
        return ParameterType::STRING;
    }

    /**
     * Gets the types array map which holds all registered types and the corresponding
     * type class
     *
     * @return array<string, string>
     */
    public static function getTypesMap(): array
    {
        return array_map(
            static function (Type $type): string {
                return $type::class;
            },
            self::getTypeRegistry()->getMap(),
        );
    }

    /**
     * Modifies the SQL expression (identifier, parameter) to convert to a database value.
     */
    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $sqlExpr;
    }

    /**
     * Modifies the SQL expression (identifier, parameter) to convert to a PHP value.
     */
    public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $sqlExpr;
    }

    /**
     * Gets an array of database types that map to this Doctrine type.
     *
     * @return array<int, string>
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return [];
    }
}
