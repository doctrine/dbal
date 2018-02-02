<?php

declare(strict_types=1);

namespace Doctrine\Enumeration;

use Doctrine\Enumeration\Exception\NotSupportedException;
use function array_search;
use function constant;
use function in_array;

trait Enumerated
{
    final private function __construct()
    {
    }

    /**
     * @param mixed $value
     */
    final public static function validateValue($value) : void
    {
        if (! in_array($value, self::availableValues(), true)) {
            throw new NotSupportedException('Invalid value.');
        }
    }

    final public static function validateName(string $name) : void
    {
        if (! isset(self::availableValues()[$name])) {
            throw new NotSupportedException('Invalid name.');
        }
    }

    /**
     * @param mixed $value
     */
    final public static function get($value) : self
    {
        self::validateValue($value);

        $name      = array_search($value, self::availableValues(), true);
        $instances = &self::registry(static::class);

        return $instances[$name] ?? $instances[$name] = new static();
    }

    /**
     * @return mixed
     */
    final private static function value(self $instance)
    {
        return self::availableValues()[array_search($instance, self::registry(static::class), true)];
    }

    /**
     * @return self[]
     */
    private static function &registry(string $type) : array
    {
        static $registry = [];

        if (! isset($registry[$type])) {
            $registry[$type] = [];
        }

        $ref = &$registry[$type];
        return $ref;
    }

    /**
     * @return mixed[]
     */
    private static function availableValues() : array
    {
        static $values = [];

        if (isset($values[static::class])) {
            return $values[static::class];
        }

        $values[static::class] = [];

        foreach ((new \ReflectionClass(static::class))->getReflectionConstants() as $name => $constantReflection) {
            if (! $constantReflection->isPublic()) {
                continue;
            }

            $constantValue = $constantReflection->getValue();

            if (in_array($constantValue, $values[static::class], true)) {
                throw new NotSupportedException('Duplicated value.');
            }

            $values[static::class][$name] = $constantValue;
        }

        return $values[static::class];
    }

    /**
     * Get the value this instance represents.
     * @return mixed
     */
    final public function __invoke()
    {
        return self::value($this);
    }

    /**
     * @param mixed[] $args
     */
    final public static function __callStatic(string $name, array $args) : self
    {
        assert(count($args) === 0);

        return static::get(constant(sprintf('%s::%s', static::class, $name)));
    }

    final public function __clone()
    {
        throw new NotSupportedException();
    }

    final public function __set_state() : void
    {
        throw new NotSupportedException();
    }

    final public function __sleep() : void
    {
        throw new NotSupportedException();
    }

    final public function __wakeup() : void
    {
        throw new NotSupportedException();
    }
}
