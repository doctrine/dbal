<?php

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Tests\Types\Fixtures\UnserializeWithDeprecationObject;
use Doctrine\DBAL\Types\ArrayType;
use Doctrine\DBAL\Types\ConversionException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function serialize;

class ArrayTest extends TestCase
{
    /** @var AbstractPlatform&MockObject */
    private AbstractPlatform $platform;

    private ArrayType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new ArrayType();
    }

    public function testArrayConvertsToDatabaseValue(): void
    {
        self::assertIsString($this->type->convertToDatabaseValue([], $this->platform));
    }

    public function testArrayConvertsToPHPValue(): void
    {
        self::assertIsArray($this->type->convertToPHPValue(serialize([]), $this->platform));
    }

    public function testConversionFailure(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage(
            "Could not convert database value to 'array' as an error was triggered by the unserialization:"
                . " 'unserialize(): Error at offset 0 of 7 bytes'",
        );

        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testDeprecationDuringConversion(): void
    {
        @self::assertInstanceOf(UnserializeWithDeprecationObject::class, $this->type->convertToPHPValue(
            serialize(new UnserializeWithDeprecationObject()),
            $this->platform,
        ));
    }

    public function testNullConversion(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testFalseConversion(): void
    {
        self::assertFalse($this->type->convertToPHPValue(serialize(false), $this->platform));
    }
}
