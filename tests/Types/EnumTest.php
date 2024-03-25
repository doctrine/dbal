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
        $this->type->enumClassname = EnumPhp::class;

        self::assertInstanceOf($this->type->enumClassname, $this->type->convertToPHPValue('A', $this->platform));
        self::assertSame(EnumPhp::A, $this->type->convertToPHPValue('A', $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertToPHPEnumBacked(): void
    {
        $this->type->enumClassname = EnumPhpBacked::class;

        self::assertInstanceOf($this->type->enumClassname, $this->type->convertToPHPValue('a', $this->platform));
        self::assertSame(EnumPhpBacked::A, $this->type->convertToPHPValue('a', $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertToPHPObject(): void
    {
        $this->type->enumClassname = EnumClass::class;

        self::assertInstanceOf($this->type->enumClassname, $this->type->convertToPHPValue('a', $this->platform));
        self::assertEquals(new EnumClass('a'), $this->type->convertToPHPValue('a', $this->platform));
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
        $this->type->enumClassname = EnumPhp::class;

        self::assertSame('A', $this->type->convertToDatabaseValue(EnumPhp::A, $this->platform));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testConvertEnumBackedToDatabaseValue(): void
    {
        $this->type->enumClassname = EnumPhpBacked::class;

        self::assertSame('a', $this->type->convertToDatabaseValue(EnumPhpBacked::A, $this->platform));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testConvertObjectToDatabaseValue(): void
    {
        $this->type->enumClassname = EnumClass::class;

        self::assertSame('a', $this->type->convertToDatabaseValue(new EnumClass('a'), $this->platform));
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
            'enum' => [EnumPhp::A],
            'enum backed' => [EnumPhpBacked::A],
            'object' => [new \stdClass()],
            'stringable' => [new class() { function __toString() { return 'a'; }}],
        ];
    }

    #[DataProvider('provideInvalidDataForEnumPhpToDatabaseValueConversion')]
    public function testEnumPhpDoesNotSupportInvalidValuesToDatabaseValueConversion($value): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue($value, $this->platform);
    }

    public static function provideInvalidDataForEnumPhpToDatabaseValueConversion()
    {
        return [
            'boolean true' => [true],
            'boolean false' => [false],
            'integer' => [17],
            'string' => ['a'],
            'array' => [['']],
            'enum' => [WrongEnumPhp::WRONG],
            'enum backed' => [EnumPhpBacked::A],
            'object' => [new \stdClass()],
            'stringable' => [new class() { function __toString() { return 'a'; }}],
        ];
    }

    #[DataProvider('provideInvalidDataForEnumPhpBackedToDatabaseValueConversion')]
    public function testEnumPhpBackedDoesNotSupportInvalidValuesToDatabaseValueConversion($value): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue($value, $this->platform);
    }

    public static function provideInvalidDataForEnumPhpBackedToDatabaseValueConversion()
    {
        return [
            'boolean true' => [true],
            'boolean false' => [false],
            'integer' => [17],
            'string' => ['a'],
            'array' => [['']],
            'enum' => [EnumPhp::A],
            'enum backed' => [WrongEnumPhpBacked::WRONG],
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
            'enum' => [EnumPhp::A],
            'enum backed' => [EnumPhpBacked::A],
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

enum EnumPhp
{
    case A;
    case B;
}

enum WrongEnumPhp
{
    case WRONG;
}

enum EnumPhpBacked: string
{
    case A = 'a';
    case B = 'b';
}

enum WrongEnumPhpBacked: string
{
    case WRONG = 'wrong';
}

final class EnumClass implements Stringable
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
