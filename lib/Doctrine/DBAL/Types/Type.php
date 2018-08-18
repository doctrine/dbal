<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use function array_map;
use function get_class;
use function str_replace;
use function strrpos;
use function substr;

/**
 * The base class for so-called Doctrine mapping types.
 *
 * A Type object is obtained by calling the static {@link getType()} method.
 */
abstract class Type
{
    public const BIGINT               = 'bigint';
    public const BINARY               = 'binary';
    public const BLOB                 = 'blob';
    public const BOOLEAN              = 'boolean';
    public const DATE                 = 'date';
    public const DATE_IMMUTABLE       = 'date_immutable';
    public const DATEINTERVAL         = 'dateinterval';
    public const DATETIME             = 'datetime';
    public const DATETIME_IMMUTABLE   = 'datetime_immutable';
    public const DATETIMETZ           = 'datetimetz';
    public const DATETIMETZ_IMMUTABLE = 'datetimetz_immutable';
    public const DECIMAL              = 'decimal';
    public const FLOAT                = 'float';
    public const GUID                 = 'guid';
    public const INTEGER              = 'integer';
    public const JSON                 = 'json';
    public const JSON_ARRAY           = 'json_array';
    public const OBJECT               = 'object';
    public const SIMPLE_ARRAY         = 'simple_array';
    public const SMALLINT             = 'smallint';
    public const STRING               = 'string';
    public const TARRAY               = 'array';
    public const TEXT                 = 'text';
    public const TIME                 = 'time';
    public const TIME_IMMUTABLE       = 'time_immutable';

    /**
     * The map of supported doctrine mapping types.
     */
    private const BUILTIN_TYPES_MAP = [
        self::BIGINT               => BigIntType::class,
        self::BINARY               => BinaryType::class,
        self::BLOB                 => BlobType::class,
        self::BOOLEAN              => BooleanType::class,
        self::DATE                 => DateType::class,
        self::DATE_IMMUTABLE       => DateImmutableType::class,
        self::DATEINTERVAL         => DateIntervalType::class,
        self::DATETIME             => DateTimeType::class,
        self::DATETIME_IMMUTABLE   => DateTimeImmutableType::class,
        self::DATETIMETZ           => DateTimeTzType::class,
        self::DATETIMETZ_IMMUTABLE => DateTimeTzImmutableType::class,
        self::DECIMAL              => DecimalType::class,
        self::FLOAT                => FloatType::class,
        self::GUID                 => GuidType::class,
        self::INTEGER              => IntegerType::class,
        self::JSON                 => JsonType::class,
        self::JSON_ARRAY           => JsonArrayType::class,
        self::OBJECT               => ObjectType::class,
        self::SIMPLE_ARRAY         => SimpleArrayType::class,
        self::SMALLINT             => SmallIntType::class,
        self::STRING               => StringType::class,
        self::TARRAY               => ArrayType::class,
        self::TEXT                 => TextType::class,
        self::TIME                 => TimeType::class,
        self::TIME_IMMUTABLE       => TimeImmutableType::class,
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
     * Gets the default length of this type.
     *
     * @deprecated Rely on information provided by the platform instead.
     *
     * @return int|null
     */
    public function getDefaultLength(AbstractPlatform $platform)
    {
        return null;
    }

    /**
     * Gets the SQL declaration snippet for a field of this type.
     *
     * @param mixed[]          $fieldDeclaration The field declaration.
     * @param AbstractPlatform $platform         The currently used database platform.
     *
     * @return string
     */
    abstract public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform);

    /**
     * Gets the name of this type.
     *
     * @return string
     *
     * @todo Needed?
     */
    abstract public function getName();

    /**
     * @internal This method is only to be used within DBAL for forward compatibility purposes. Do not use directly.
     */
    final public static function getTypeRegistry() : TypeRegistry
    {
        if (self::$typeRegistry === null) {
            self::$typeRegistry = self::createTypeRegistry();
        }

        return self::$typeRegistry;
    }

    private static function createTypeRegistry() : TypeRegistry
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
     * @return \Doctrine\DBAL\Types\Type
     *
     * @throws DBALException
     */
    public static function getType($name)
    {
        return self::getTypeRegistry()->get($name);
    }

    /**
     * Adds a custom type to the type map.
     *
     * @param string $name      The name of the type. This should correspond to what getName() returns.
     * @param string $className The class name of the custom type.
     *
     * @return void
     *
     * @throws DBALException
     */
    public static function addType($name, $className)
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
    public static function hasType($name)
    {
        return self::getTypeRegistry()->has($name);
    }

    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @param string $name
     * @param string $className
     *
     * @return void
     *
     * @throws DBALException
     */
    public static function overrideType($name, $className)
    {
        self::getTypeRegistry()->override($name, new $className());
    }

    /**
     * Gets the (preferred) binding type for values of this type that
     * can be used when binding parameters to prepared statements.
     *
     * This method should return one of the {@link \Doctrine\DBAL\ParameterType} constants.
     *
     * @return int
     */
    public function getBindingType()
    {
        return ParameterType::STRING;
    }

    /**
     * Gets the types array map which holds all registered types and the corresponding
     * type class
     *
     * @return string[]
     */
    public static function getTypesMap()
    {
        return array_map(
            static function (Type $type) : string {
                return get_class($type);
            },
            self::getTypeRegistry()->getMap()
        );
    }

    /**
     * @deprecated Relying on string representation is discouraged and will be removed in DBAL 3.0.
     *
     * @return string
     */
    public function __toString()
    {
        $type     = static::class;
        $position = strrpos($type, '\\');

        if ($position !== false) {
            $type = substr($type, $position);
        }

        return str_replace('Type', '', $type);
    }

    /**
     * Does working with this column require SQL conversion functions?
     *
     * This is a metadata function that is required for example in the ORM.
     * Usage of {@link convertToDatabaseValueSQL} and
     * {@link convertToPHPValueSQL} works for any type and mostly
     * does nothing. This method can additionally be used for optimization purposes.
     *
     * @return bool
     */
    public function canRequireSQLConversion()
    {
        return false;
    }

    /**
     * Modifies the SQL expression (identifier, parameter) to convert to a database value.
     *
     * @param string $sqlExpr
     *
     * @return string
     */
    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
    {
        return $sqlExpr;
    }

    /**
     * Modifies the SQL expression (identifier, parameter) to convert to a PHP value.
     *
     * @param string           $sqlExpr
     * @param AbstractPlatform $platform
     *
     * @return string
     */
    public function convertToPHPValueSQL($sqlExpr, $platform)
    {
        return $sqlExpr;
    }

    /**
     * Gets an array of database types that map to this Doctrine type.
     *
     * @return string[]
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform)
    {
        return [];
    }

    /**
     * If this Doctrine Type maps to an already mapped database type,
     * reverse schema engineering can't tell them apart. You need to mark
     * one of those types as commented, which will have Doctrine use an SQL
     * comment to typehint the actual Doctrine Type.
     *
     * @return bool
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return false;
    }
}
