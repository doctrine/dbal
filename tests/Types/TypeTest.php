<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TypeTest extends TestCase
{
    /**
     * @dataProvider defaultTypesProvider()
     */
    public function testDefaultTypesAreRegistered(string $name) : void
    {
        self::assertTrue(Type::hasType($name));
    }

    /**
     * @return string[][]
     */
    public function defaultTypesProvider() : iterable
    {
        foreach ((new ReflectionClass(Type::class))->getReflectionConstants() as $constant) {
            if (! $constant->isPublic()) {
                continue;
            }

            yield [$constant->getValue()];
        }
    }
}
