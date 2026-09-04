<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use ArgumentCountError;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\TypeArgumentCountError;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\Deprecations\Deprecation;

use function array_map;
use function is_string;
use function iterator_to_array;

/**
 * The base class for so-called Doctrine mapping types.
 *
 * A Type object is obtained by calling the static {@see getType()} method.
 */
abstract class Type
{
    private static ?TypeRegistry $typeRegistry = null;

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

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see Configuration::getTypeRegistry()}, or inject
     *             your own type provider. There will be no static type provider in DBAL 5.
     */
    final public static function getTypeRegistry(): TypeRegistry
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7342',
            '%s is deprecated. Use Configuration::getTypeRegistry(), or inject your own type provider.',
            __METHOD__,
        );

        // @phpstan-ignore missingType.checkedException
        return self::$typeRegistry ??= new TypeRegistry();
    }

    /**
     * Factory method to create type instances.
     *
     * @deprecated since doctrine/dbal 4.5. Resolve the type through the type provider of the
     *             connection instead.
     *
     * @param string $name The name of the type.
     *
     * @throws TypesException
     */
    public static function getType(string $name): self
    {
        // Called from the deprecated Column::getType(), which triggers its own deprecation, so
        // triggering unconditionally here would report the same call twice.
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7342',
            '%s is deprecated. Resolve the type through the type provider of the connection instead.',
            __METHOD__,
        );

        return self::getTypeRegistry()->get($name);
    }

    /**
     * Finds a name for the given type.
     *
     * @deprecated since doctrine/dbal 4.5. Track the type name instead of the instance.
     *
     * @throws TypesException
     */
    public static function lookupName(self $type): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7342',
            '%s is deprecated. Track the type name instead of the instance.',
            __METHOD__,
        );

        return self::getTypeRegistry()->lookupName($type);
    }

    /**
     * Adds a custom type to the type map.
     *
     * @deprecated since doctrine/dbal 4.5. Register the type in the type provider of the
     *             connection instead.
     *
     * @param string                  $name The name of the type.
     * @param class-string<Type>|Type $type The custom type or the class name of the custom type.
     *
     * @throws Exception
     */
    public static function addType(string $name, string|Type $type): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7342',
            '%s is deprecated. Register the type in the type provider of the connection instead.',
            __METHOD__,
        );

        if (is_string($type)) {
            try {
                $type = new $type();
            } catch (ArgumentCountError $e) { // @phpstan-ignore catch.neverThrown (it can be thrown)
                throw TypeArgumentCountError::new($name, $e);
            }
        }

        self::getTypeRegistry()->register($name, $type);
    }

    /**
     * Checks if exists support for a type.
     *
     * @deprecated since doctrine/dbal 4.5. Ask the type provider of the connection instead.
     *
     * @param string $name The name of the type.
     *
     * @return bool TRUE if type is supported; FALSE otherwise.
     *
     * @throws TypesException
     */
    public static function hasType(string $name): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7342',
            '%s is deprecated. Ask the type provider of the connection instead.',
            __METHOD__,
        );

        return self::getTypeRegistry()->has($name);
    }

    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @deprecated since doctrine/dbal 4.5. Build a type provider with the type already overridden
     *             instead.
     *
     * @param class-string<Type>|Type $type The custom type or the class name of the custom type.
     *
     * @throws Exception
     */
    public static function overrideType(string $name, string|Type $type): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7342',
            '%s is deprecated. Build a type provider with the type already overridden instead.',
            __METHOD__,
        );

        if (is_string($type)) {
            try {
                $type = new $type();
            } catch (ArgumentCountError $e) { // @phpstan-ignore catch.neverThrown (it can be thrown)
                throw TypeArgumentCountError::new($name, $e);
            }
        }

        self::getTypeRegistry()->override($name, $type);
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
     * @deprecated since doctrine/dbal 4.5. Iterate the type provider of the connection instead.
     *
     * @return array<string, string>
     *
     * @throws TypesException
     */
    public static function getTypesMap(): array
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7342',
            '%s is deprecated. Iterate the type provider of the connection instead.',
            __METHOD__,
        );

        return array_map(
            static fn (Type $type): string => $type::class,
            iterator_to_array(self::getTypeRegistry()),
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
