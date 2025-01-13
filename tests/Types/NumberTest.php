<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use BcMath\Number;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\NumberType;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

#[RequiresPhp('8.4')]
#[RequiresPhpExtension('bcmath')]
final class NumberTest extends TestCase
{
    private AbstractPlatform&MockObject $platform;
    private NumberType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new NumberType();
    }

    #[TestWith(['5.5'])]
    #[TestWith(['5.5000'])]
    #[TestWith([5.5])]
    public function testDecimalConvertsToPHPValue(mixed $dbValue): void
    {
        $phpValue = $this->type->convertToPHPValue($dbValue, $this->platform);

        self::assertInstanceOf(Number::class, $phpValue);
        self::assertSame(0, $phpValue <=> new Number('5.5'));
    }

    public function testDecimalNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testNumberConvertsToDecimalString(): void
    {
        self::assertSame('5.5', $this->type->convertToDatabaseValue(new Number('5.5'), $this->platform));
    }

    public function testNumberNullConvertsToNull(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    #[TestWith(['5.5'])]
    #[TestWith([new stdClass()])]
    public function testInvalidPhpValuesTriggerException(mixed $value): void
    {
        self::expectException(InvalidType::class);

        $this->type->convertToDatabaseValue($value, $this->platform);
    }

    #[TestWith(['foo'])]
    #[TestWith([true])]
    public function testUnexpectedValuesReturnedByTheDatabaseTriggerException(mixed $value): void
    {
        self::expectException(ValueNotConvertible::class);

        $this->type->convertToPHPValue($value, $this->platform);
    }
}
