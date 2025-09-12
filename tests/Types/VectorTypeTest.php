<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function hex2bin;

final class VectorTypeTest extends TestCase
{
    public function testConvertToDatabaseValue(): void
    {
        $type = Type::getType(Types::VECTOR);

        $value = $type->convertToDatabaseValue(
            [0.418708, 0.809902, 0.823193, 0.598179, 0.0332549],
            self::createStub(AbstractPlatform::class),
        );

        self::assertSame('e560d63ebd554f3fc7bc523f4222193f4a36083d', bin2hex($value));
    }

    public function testConvertToPHPValue(): void
    {
        $type = Type::getType(Types::VECTOR);

        $value = $type->convertToPHPValue(
            hex2bin('e560d63ebd554f3fc7bc523f4222193f4a36083d'),
            self::createStub(AbstractPlatform::class),
        );

        self::assertEqualsWithDelta([0.418708, 0.809902, 0.823193, 0.598179, 0.0332549], $value, .0001);
    }
}
