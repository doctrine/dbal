<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\EnumType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stringable;

class EnumTest extends TestCase
{
    private AbstractPlatform&MockObject $platform;
    private EnumType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new EnumType();
    }

    public function testReturnsSQLDeclaration(): void
    {
        self::assertSame('ENUM(\'a\', \'b\')', $this->type->getSQLDeclaration(['members' => ['a', 'b']], $this->platform));
    }

    public function testConvertToPHPValue(): void
    {
        $this->type->members = ['a', 'b'];

        self::assertIsString($this->type->convertToPHPValue('b', $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertToPHPEnum(): void
    {
        $this->type->enumClassname = EnumNative::class;

        self::assertInstanceOf($this->type->enumClassname, $this->type->convertToPHPValue('A', $this->platform));
        self::assertSame(EnumNative::A, $this->type->convertToPHPValue('A', $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertToPHPEnumBacked(): void
    {
        $this->type->enumClassname = EnumNativeBacked::class;

        self::assertInstanceOf($this->type->enumClassname, $this->type->convertToPHPValue('a', $this->platform));
        self::assertSame(EnumNativeBacked::A, $this->type->convertToPHPValue('a', $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertToPHPObject(): void
    {
        $this->type->enumClassname = EnumObject::class;

        self::assertInstanceOf($this->type->enumClassname, $this->type->convertToPHPValue('a', $this->platform));
        self::assertEquals(new EnumObject('a'), $this->type->convertToPHPValue('a', $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertStringToDatabaseValue(): void
    {
        $this->type->members = ['a', 'b'];

        self::assertSame('a', $this->type->convertToDatabaseValue('a', $this->platform));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testConvertEnumToDatabaseValue(): void
    {
        $this->type->enumClassname = EnumNative::class;

        self::assertSame('A', $this->type->convertToDatabaseValue(EnumNative::A, $this->platform));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testConvertEnumBackedToDatabaseValue(): void
    {
        $this->type->enumClassname = EnumNativeBacked::class;

        self::assertSame('a', $this->type->convertToDatabaseValue(EnumNativeBacked::A, $this->platform));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testConvertObjectToDatabaseValue(): void
    {
        $this->type->enumClassname = EnumObject::class;

        self::assertSame('a', $this->type->convertToDatabaseValue(new EnumObject('a'), $this->platform));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    #[DataProvider('provideInvalidDataForEnumStringToDatabaseValueConversion')]
    public function testEnumStringDoesNotSupportInvalidValuesToDatabaseValueConversion($value): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue($value, $this->platform);
    }

    public static function provideInvalidDataForEnumStringToDatabaseValueConversion()
    {
        return [
            'boolean true' => [true],
            'boolean false' => [false],
            'integer' => [17],
            'string' => ['not_in_members'],
            'array' => [['']],
            'enum' => [EnumNative::A],
            'enum backed' => [EnumNativeBacked::A],
            'object' => [new \stdClass()],
            'stringable' => [new class() { function __toString() { return 'a'; }}],
        ];
    }

    #[DataProvider('provideInvalidDataForEnumNativeToDatabaseValueConversion')]
    public function testEnumNativeDoesNotSupportInvalidValuesToDatabaseValueConversion($value): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue($value, $this->platform);
    }

    public static function provideInvalidDataForEnumNativeToDatabaseValueConversion()
    {
        return [
            'boolean true' => [true],
            'boolean false' => [false],
            'integer' => [17],
            'string' => ['a'],
            'array' => [['']],
            'enum' => [WrongEnumNative::WRONG],
            'enum backed' => [EnumNativeBacked::A],
            'object' => [new \stdClass()],
            'stringable' => [new class() { function __toString() { return 'a'; }}],
        ];
    }

    #[DataProvider('provideInvalidDataForEnumNativeBackedToDatabaseValueConversion')]
    public function testEnumNativeBackedDoesNotSupportInvalidValuesToDatabaseValueConversion($value): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue($value, $this->platform);
    }

    public static function provideInvalidDataForEnumNativeBackedToDatabaseValueConversion()
    {
        return [
            'boolean true' => [true],
            'boolean false' => [false],
            'integer' => [17],
            'string' => ['a'],
            'array' => [['']],
            'enum' => [EnumNative::A],
            'enum backed' => [WrongEnumNativeBacked::WRONG],
            'object' => [new \stdClass()],
            'stringable' => [new class() { function __toString() { return 'a'; }}],
        ];
    }

    #[DataProvider('provideInvalidDataForEnumObjectToDatabaseValueConversion')]
    public function testEnumObjectDoesNotSupportInvalidValuesToDatabaseValueConversion($value): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue($value, $this->platform);
    }

    public static function provideInvalidDataForEnumObjectToDatabaseValueConversion()
    {
        return [
            'boolean true' => [true],
            'boolean false' => [false],
            'integer' => [17],
            'string' => ['a'],
            'array' => [['']],
            'enum' => [EnumNative::A],
            'enum backed' => [EnumNativeBacked::A],
            'object' => [new \stdClass()],
            'stringable' => [new class() { function __toString() { return 'a'; }}],
        ];
    }

    public function testInvalidValueForDatabaseValueToEnumStringConversion(): void
    {
        $this->type->members = ['a', 'b'];

        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('not_in_members', $this->platform);
    }
}

enum EnumNative
{
    case A;
    case B;
}

enum WrongEnumNative
{
    case WRONG;
}

enum EnumNativeBacked: string
{
    case A = 'a';
    case B = 'b';
}

enum WrongEnumNativeBacked: string
{
    case WRONG = 'wrong';
}

final class EnumObject implements Stringable
{
    public function __construct(
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
