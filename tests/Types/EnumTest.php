<?php

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Tests\Tools\TestAsset\SimpleEnum;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\EnumType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function is_a;
use function serialize;

/**
 * @requires PHP >= 8.1
 */
class EnumTest extends TestCase
{
    /** @var AbstractPlatform&MockObject */
    private $platform;

    /** @var EnumType */
    private $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new EnumType();
    }

    public function testObjectConvertsToDatabaseValue(): void
    {
        self::assertIsString($this->type->convertToDatabaseValue(SimpleEnum::DRAFT, $this->platform));
    }

    public function testObjectConvertsToPHPValue(): void
    {
        $value = $this->type->convertToPHPValue(serialize(SimpleEnum::DRAFT), $this->platform);
        self::assertTrue(is_a($value, SimpleEnum::class));
    }

    public function testConversionToDatabaseValueFailure(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage(
            "Could not convert PHP value 'abcdefg' to type enum. Expected one of the following types: null, UnitEnum"
        );
        $this->type->convertToDatabaseValue('abcdefg', $this->platform);
    }

    public function testConversionToPHPValueFailure(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage(
            "Could not convert database value to 'enum' as an error was triggered by the unserialization:"
            . " 'unserialize(): Error at offset 0 of 7 bytes'"
        );
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testNullConversion(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}
