<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Types\Exception\TypeArgumentCountError;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TypeTest extends TestCase
{
    #[DataProvider('defaultTypesProvider')]
    public function testDefaultTypesAreRegistered(string $name): void
    {
        self::assertTrue(Type::hasType($name));
    }

    #[DataProvider('defaultTypesProvider')]
    public function testDefaultTypesReverseLookup(string $name): void
    {
        $type = Type::getType($name);
        self::assertSame($name, Type::lookupName($type));
    }

    /** @return iterable<string[]> */
    public static function defaultTypesProvider(): iterable
    {
        foreach ((new ReflectionClass(Types::class))->getReflectionConstants() as $constant) {
            if (! $constant->isPublic()) {
                continue;
            }

            $constantValue = $constant->getValue();
            self::assertIsString($constantValue);

            yield [$constantValue];
        }
    }

    public function testAddTypeWhenTypeRequiresArguments(): void
    {
        self::expectException(TypeArgumentCountError::class);
        self::expectExceptionMessage('To register "some_type" pass an instance to `Type::addType` instead.');

        Type::addType('some_type', TypeWithConstructor::class);
    }

    public function testAddTypeInstance(): void
    {
        self::assertFalse(Type::hasType('some_type'));
        Type::addType('some_type', new TypeWithConstructor(true));
        self::assertTrue(Type::hasType('some_type'));
    }
}
