<?php

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\NonEmptyStringType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NonEmptyStringTest extends TestCase
{
    /** @var AbstractPlatform&MockObject */
    private AbstractPlatform $platform;

    private NonEmptyStringType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new NonEmptyStringType();
    }

    public function testReturnsSqlDeclarationFromPlatformString(): void
    {
        $this->platform->expects(self::once())
            ->method('getStringTypeDeclarationSQL')
            ->willReturn('TEST_STRING');

        self::assertEquals('TEST_STRING', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testConvertToPHPValue(): void
    {
        self::assertSame('foo', $this->type->convertToPHPValue('foo', $this->platform));
        self::assertSame('bar', $this->type->convertToPHPValue('bar', $this->platform));
    }

    public function testConvertToPHPValueThrowsExceptionOnEmptyString(): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToPHPValue('', $this->platform);
    }

    public function testConvertToPHPValueThrowsExceptionOnNull(): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToPHPValue(null, $this->platform);
    }

    public function testConvertToPHPValueThrowsExceptionOnNonString(): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToPHPValue(123, $this->platform);
    }

    public function testConvertToDatabaseValue(): void
    {
        self::assertSame('foo', $this->type->convertToDatabaseValue('foo', $this->platform));
        self::assertSame('bar', $this->type->convertToDatabaseValue('bar', $this->platform));
    }

    public function testConvertToDatabaseValueThrowsExceptionOnEmptyString(): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToDatabaseValue('', $this->platform);
    }

    public function testConvertToDatabaseValueThrowsExceptionOnNull(): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToDatabaseValue(null, $this->platform);
    }

    public function testConvertToDatabaseValueThrowsExceptionOnNonString(): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToDatabaseValue(123, $this->platform);
    }

    public function testSQLConversion(): void
    {
        self::assertFalse($this->type->canRequireSQLConversion());
        self::assertEquals('t.foo', $this->type->convertToDatabaseValueSQL('t.foo', $this->platform));
        self::assertEquals('t.foo', $this->type->convertToPHPValueSQL('t.foo', $this->platform));
    }
}
