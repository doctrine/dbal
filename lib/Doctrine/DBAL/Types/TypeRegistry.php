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
 * The registry for so-called Doctrine mapping types.
 *
 * A Type object is obtained by calling the static {@link getType()} method.
 *
 * @author Roman Borschel <roman@code-factory.org>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @since  2.0
 */
final class TypeRegistry
{
    /**
     * Map of already instantiated type objects. One instance per type (flyweight).
     *
     * @var array
     */
    private static $_typeObjects = array();

    /**
     * The map of supported doctrine mapping types for the current platform.
     *
     * @var array
     */
    private static $_typesMap = array(
        ArrayType::class,
        SimpleArrayType::class,
        JsonArrayType::class,
        JsonType::class,
        ObjectType::class,
        BooleanType::class,
        IntegerType::class,
        SmallIntType::class,
        BigIntType::class,
        StringType::class,
        TextType::class,
        DateTimeType::class,
        DateTimeImmutableType::class,
        DateTimeTzType::class,
        DateTimeTzImmutableType::class,
        DateType::class,
        DateImmutableType::class,
        TimeType::class,
        TimeImmutableType::class,
        DecimalType::class,
        FloatType::class,
        BinaryType::class,
        BlobType::class,
        GuidType::class,
        DateIntervalType::class,
    );

    /**
     * Factory method to create type instances.
     * Type instances are implemented as flyweights.
     *
     * @param string $class The FQCN of the type
     *
     * @return \Doctrine\DBAL\Types\Type
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function getType(string $class): Doctrine\DBAL\Types\Type
    {
        if (!self::hasType($class)) {
            throw DBALException::unknownColumnType($class);
        }
        if (!isset(self::$_typeObjects[$class])) {
            self::$_typeObjects[$class] = new $class();
        }

        return self::$_typeObjects[$class];
    }

    /**
     * Adds a custom type to the type map.
     *
     * @param string $class The class name of the custom type.
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function addType(string $class)
    {
        if (self::hasType($class)) {
            throw DBALException::typeExists($name);
        }

        self::$_typesMap[] = $class;
    }

    /**
     * Checks if exists support for a type.
     *
     * @param string $class The classname of the type.
     *
     * @return boolean TRUE if type is supported; FALSE otherwise.
     */
    public static function hasType(string $class): bool
    {
        return in_array($class, self::$_typesMap);
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
}
