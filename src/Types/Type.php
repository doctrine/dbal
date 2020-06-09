<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use function array_map;
use function get_class;

/**
 * The base class for so-called Doctrine mapping types.
 *
 * A Type object is obtained by calling the static {@link getType()} method.
 */
abstract class Type
{
    /**
     * The map of supported doctrine mapping types.
     */
    private const BUILTIN_TYPES_MAP = [
        Types::ARRAY                => ArrayType::class,
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
        Types::FLOAT                => FloatType::class,
        Types::GUID                 => GuidType::class,
        Types::INTEGER              => IntegerType::class,
        Types::JSON                 => JsonType::class,
        Types::OBJECT               => ObjectType::class,
        Types::SIMPLE_ARRAY         => SimpleArrayType::class,
        Types::SMALLINT             => SmallIntType::class,
        Types::STRING               => StringType::class,
        Types::TEXT                 => TextType::class,
        Types::TIME_MUTABLE         => TimeType::class,
        Types::TIME_IMMUTABLE       => TimeImmutableType::class,
    ];

    /** @var TypeRegistry|null */
    private static $typeRegistry;

    /**
     * @internal Do not instantiate directly - use {@see Type::addType()} method instead.
     */
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
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
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
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    /**
     * Gets the SQL declaration snippet for a field of this type.
     *
     * @param array<string, mixed> $fieldDeclaration The field declaration.
     * @param AbstractPlatform     $platform         The currently used database platform.
     *
     * @throws DBALException
     */
    abstract public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string;

    /**
     * Gets the name of this type.
     *
     * @todo Needed?
     */
    abstract public function getName(): string;

    /**
     * @internal This method is only to be used within DBAL for forward compatibility purposes. Do not use directly.
     */
    final public static function getTypeRegistry(): TypeRegistry
    {
        if (self::$typeRegistry === null) {
            self::$typeRegistry = self::createTypeRegistry();
        }

        return self::$typeRegistry;
    }

    private static function createTypeRegistry(): TypeRegistry
    {
        $registry = new TypeRegistry();

        foreach (self::BUILTIN_TYPES_MAP as $name => $class) {
            $registry->register($name, new $class());
        }

        return $registry;
    }

    /**
     * Factory method to create type instances.
     * Type instances are implemented as flyweights.
     *
     * @param string $name The name of the type (as returned by getName()).
     *
     * @throws DBALException
     */
    public static function getType(string $name): self
    {
        return self::getTypeRegistry()->get($name);
    }

    /**
     * Adds a custom type to the type map.
     *
     * @param string $name      The name of the type. This should correspond to what getName() returns.
     * @param string $className The class name of the custom type.
     *
     * @throws DBALException
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
     * @throws DBALException
     */
    public static function overrideType(string $name, string $className): void
    {
        self::getTypeRegistry()->override($name, new $className());
    }

    /**
     * Gets the (preferred) binding type for values of this type that
     * can be used when binding parameters to prepared statements.
     *
     * This method should return one of the {@link \Doctrine\DBAL\ParameterType} constants.
     */
    public function getBindingType(): int
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
                return get_class($type);
            },
            self::getTypeRegistry()->getMap()
        );
    }

    /**
     * Does working with this column require SQL conversion functions?
     *
     * This is a metadata function that is required for example in the ORM.
     * Usage of {@link convertToDatabaseValueSQL} and
     * {@link convertToPHPValueSQL} works for any type and mostly
     * does nothing. This method can additionally be used for optimization purposes.
     */
    public function canRequireSQLConversion(): bool
    {
        return false;
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

    /**
     * If this Doctrine Type maps to an already mapped database type,
     * reverse schema engineering can't tell them apart. You need to mark
     * one of those types as commented, which will have Doctrine use an SQL
     * comment to typehint the actual Doctrine Type.
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return false;
    }
}
