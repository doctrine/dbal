<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TypeTest extends TestCase
{
    /** @dataProvider defaultTypesProvider() */
    public function testDefaultTypesAreRegistered(string $name): void
    {
        self::assertTrue(Type::hasType($name));
    }

    /** @return iterable<string[]> */
    public function defaultTypesProvider(): iterable
    {
        foreach ((new ReflectionClass(Type::class))->getReflectionConstants() as $constant) {
            if (! $constant->isPublic()) {
                continue;
            }

            $constantValue = $constant->getValue();
            self::assertIsString($constantValue);

            yield [$constantValue];
        }
    }
}
