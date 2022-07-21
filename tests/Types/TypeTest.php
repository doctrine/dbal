<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function strtr;

class TypeTest extends TestCase
{
    /**
     * @dataProvider defaultTypesProvider()
     */
    public function testDefaultTypesAreRegistered(string $name): void
    {
        self::assertTrue(Type::hasType($name));
    }

    /**
     * @return iterable<string[]>
     */
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

    /**
     * @doesNotPerformAssertions
     */
    public function testOverridingTheConstructorIsAllowed(): void
    {
        new class (['a' => 'b']) extends StringType
        {
            /**
             * @param array<string, string> $replacements
             */
            public function __construct(
                private array $replacements,
            ) {
            }

            public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
            {
                return strtr($value, $this->replacements);
            }
        };
    }
}
