<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Deprecations\Deprecation;

use function array_map;
use function get_class;

/**
 * The base class for so-called Doctrine mapping types.
 *
 * A Type object is obtained by calling the static {@see getType()} method.
 */
abstract class Type
{
    private static ?TypeRegistry $typeRegistry = null;

    /**
     * @internal Do not instantiate directly - use {@see TypeRegistry::getInstance()->register()} method instead.
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
     *
     * @throws ConversionException
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
     *
     * @throws ConversionException
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    /**
     * Gets the SQL declaration snippet for a column of this type.
     *
     * @param mixed[]          $column   The column definition
     * @param AbstractPlatform $platform The currently used database platform.
     *
     * @return string
     */
    abstract public function getSQLDeclaration(array $column, AbstractPlatform $platform);

    /**
     * Gets the name of this type.
     *
     * @deprecated this method will be removed in Doctrine DBAL 4.0.
     *
     * @return string
     */
    abstract public function getName();

    /**
     * @deprecated Use TypeRegistry::getInstance() instead.
     */
    final public static function getTypeRegistry(): TypeRegistry
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5530',
            '%s is deprecated in favor of TypeRegistry::getInstance()',
            __METHOD__
        );

        return self::$typeRegistry ??= self::createTypeRegistry();
    }

    private static function createTypeRegistry(): TypeRegistry
    {
        return TypeRegistry::getInstance();
    }

    /**
     * Factory method to create type instances.
     * Type instances are implemented as flyweights.
     *
     * @deprecated Use TypeRegistry::getType() instead.
     *
     * @param string $name The name of the type (as returned by getName()).
     *
     * @return Type
     *
     * @throws Exception
     */
    public static function getType($name)
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5530',
            '%s is deprecated In favor of TypeRegistry::get()',
            __METHOD__
        );

        return self::getTypeRegistry()->get($name);
    }

    /**
     * Adds a custom type to the type map.
     *
     * @deprecated Use TypeRegistry::register() instead.
     *
     * @param string             $name      The name of the type. This should correspond to what getName() returns.
     * @param class-string<Type> $className The class name of the custom type.
     *
     * @return void
     *
     * @throws Exception
     */
    public static function addType($name, $className)
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5530',
            '%s is deprecated in favor of TypeRegistry::register()',
            __METHOD__
        );
        self::getTypeRegistry()->register($name, new $className());
    }

    /**
     * Checks if exists support for a type.
     *
     * @deprecated Use TypeRegistry::hasType() instead.
     *
     * @param string $name The name of the type.
     *
     * @return bool TRUE if type is supported; FALSE otherwise.
     */
    public static function hasType($name)
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5530',
            '%s is deprecated in favor of TypeRegistry::has()',
            __METHOD__
        );

        return self::getTypeRegistry()->has($name);
    }

    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @deprecated Use TypeRegistry::override() instead.
     *
     * @param string             $name
     * @param class-string<Type> $className
     *
     * @return void
     *
     * @throws Exception
     */
    public static function overrideType($name, $className)
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5530',
            '%s is deprecated in favor of TypeRegistry::override()',
            __METHOD__
        );
        self::getTypeRegistry()->override($name, new $className());
    }

    /**
     * Gets the (preferred) binding type for values of this type that
     * can be used when binding parameters to prepared statements.
     *
     * This method should return one of the {@see ParameterType} constants.
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
     * @deprecated Use TypeRegistry::getMap() instead.
     *
     * @return array<string, string>
     */
    public static function getTypesMap()
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5530',
            '%s is deprecated.',
            __METHOD__
        );

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
     * Usage of {@see convertToDatabaseValueSQL} and
     * {@see convertToPHPValueSQL} works for any type and mostly
     * does nothing. This method can additionally be used for optimization purposes.
     *
     * @deprecated Consumers should call {@see convertToDatabaseValueSQL} and {@see convertToPHPValueSQL}
     * regardless of the type.
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
     * @deprecated
     *
     * @return bool
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5509',
            '%s is deprecated.',
            __METHOD__
        );

        return false;
    }
}
