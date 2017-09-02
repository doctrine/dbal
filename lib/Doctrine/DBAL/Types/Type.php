<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\DBALException;

/**
 * The base class for so-called Doctrine mapping types.
 *
 * A Type object is obtained by calling the static {@link getType()} method.
 *
 * @author Roman Borschel <roman@code-factory.org>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @since  2.0
 */
abstract class Type implements TypeInterface
{
    const TARRAY = 'array';
    const SIMPLE_ARRAY = 'simple_array';
    const JSON_ARRAY = 'json_array';
    const JSON = 'json';
    const BIGINT = 'bigint';
    const BOOLEAN = 'boolean';
    const DATETIME = 'datetime';
    const DATETIME_IMMUTABLE = 'datetime_immutable';
    const DATETIMETZ = 'datetimetz';
    const DATETIMETZ_IMMUTABLE = 'datetimetz_immutable';
    const DATE = 'date';
    const DATE_IMMUTABLE = 'date_immutable';
    const TIME = 'time';
    const TIME_IMMUTABLE = 'time_immutable';
    const DECIMAL = 'decimal';
    const INTEGER = 'integer';
    const OBJECT = 'object';
    const SMALLINT = 'smallint';
    const STRING = 'string';
    const TEXT = 'text';
    const BINARY = 'binary';
    const BLOB = 'blob';
    const FLOAT = 'float';
    const GUID = 'guid';
    const DATEINTERVAL = 'dateinterval';

    /**
     * Map of already instantiated type objects. One instance per type (flyweight).
     *
     * @var array
     */
    private static $_typeObjects = [];

    /**
     * The map of supported doctrine mapping types.
     *
     * @var array
     */
    private static $_typesMap = [
        self::TARRAY => ArrayType::class,
        self::SIMPLE_ARRAY => SimpleArrayType::class,
        self::JSON_ARRAY => JsonArrayType::class,
        self::JSON => JsonType::class,
        self::OBJECT => ObjectType::class,
        self::BOOLEAN => BooleanType::class,
        self::INTEGER => IntegerType::class,
        self::SMALLINT => SmallIntType::class,
        self::BIGINT => BigIntType::class,
        self::STRING => StringType::class,
        self::TEXT => TextType::class,
        self::DATETIME => DateTimeType::class,
        self::DATETIME_IMMUTABLE => DateTimeImmutableType::class,
        self::DATETIMETZ => DateTimeTzType::class,
        self::DATETIMETZ_IMMUTABLE => DateTimeTzImmutableType::class,
        self::DATE => DateType::class,
        self::DATE_IMMUTABLE => DateImmutableType::class,
        self::TIME => TimeType::class,
        self::TIME_IMMUTABLE => TimeImmutableType::class,
        self::DECIMAL => DecimalType::class,
        self::FLOAT => FloatType::class,
        self::BINARY => BinaryType::class,
        self::BLOB => BlobType::class,
        self::GUID => GuidType::class,
        self::DATEINTERVAL => DateIntervalType::class,
    ];


    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    /**
     * Gets the default length of this type.
     *
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return integer|null
     *
     * @todo Needed?
     */
    public function getDefaultLength(AbstractPlatform $platform)
    {
        return null;
    }

    /**
     * {@inheritdoc}
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

    private static function warnAgainstUsingTheOldAPI()
    {
        @trigger_error(
            'Using '.__CLASS__.' as a type registry is deprecated, please use '.
            TypeRegistry::class.' instead',
            E_USER_DEPRECATED
        );
    }

    /**
     * Factory method to create type instances.
     * Type instances are implemented as flyweights.
     *
     * @param string $name The name of the type (as returned by getName()).
     *
     * @return \Doctrine\DBAL\Types\Type
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function getType($name)
    {
        self::warnAgainstUsingTheOldAPI();
        try {
            return TypeRegistry::getType($name);
        } catch (DBALException $e) {
            if ( ! isset(self::$_typeObjects[$name])) {
                if ( ! isset(self::$_typesMap[$name])) {
                    throw DBALException::unknownColumnType($name, true);
                }
                self::$_typeObjects[$name] = new self::$_typesMap[$name]();
            }

            return self::$_typeObjects[$name];
        }
    }

    /**
     * Adds a custom type to the type map.
     *
     * @param string $name      The name of the type. This should correspond to what getName() returns.
     * @param string $className The class name of the custom type.
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function addType($name, $className)
    {
        self::warnAgainstUsingTheOldAPI();
        if ($name === $className) {
            TypeRegistry::addType($className);
            return;
        }
        if (isset(self::$_typesMap[$name])) {
            throw DBALException::typeExists($name);
        }

        self::$_typesMap[$name] = $className;
    }

    /**
     * Checks if exists support for a type.
     *
     * @param string $name The name of the type.
     *
     * @return boolean TRUE if type is supported; FALSE otherwise.
     */
    public static function hasType($name)
    {
        self::warnAgainstUsingTheOldAPI();
        return isset(self::$_typesMap[$name]);
    }

    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @param string $name
     * @param string $className
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function overrideType($name, $className)
    {
        if ( ! isset(self::$_typesMap[$name])) {
            throw DBALException::typeNotFound($name);
        }

        if (isset(self::$_typeObjects[$name])) {
            unset(self::$_typeObjects[$name]);
        }

        self::$_typesMap[$name] = $className;
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingType()
    {
        return \PDO::PARAM_STR;
    }

    /**
     * Gets the types array map which holds all registered types and the corresponding
     * type class
     *
     * @return array
     */
    public static function getTypesMap()
    {
        return self::$_typesMap;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $e = explode('\\', get_class($this));

        return str_replace('Type', '', end($e));
    }

    /**
     * {@inheritdoc}
     */
    public function canRequireSQLConversion()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
    {
        return $sqlExpr;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValueSQL($sqlExpr, $platform)
    {
        return $sqlExpr;
    }

    /**
     * {@inheritdoc}
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return false;
    }
}
