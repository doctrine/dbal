<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\TimeImmutableType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TimeImmutableTypeTest extends TestCase
{
    private AbstractPlatform&MockObject $platform;
    private TimeImmutableType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new TimeImmutableType();
    }

    public function testFactoryCreatesCorrectType(): void
    {
        self::assertSame(TimeImmutableType::class, $this->type::class);
    }

    public function testReturnsBindingType(): void
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue(): void
    {
        $date = $this->createMock(DateTimeImmutable::class);

        $this->platform->expects(self::once())
            ->method('getTimeFormatString')
            ->willReturn('H:i:s');
        $date->expects(self::once())
            ->method('format')
            ->with('H:i:s')
            ->willReturn('15:58:59');

        self::assertSame(
            '15:58:59',
            $this->type->convertToDatabaseValue($date, $this->platform),
        );
    }

    public function testConvertsNullToDatabaseValue(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testDoesNotSupportMutableDateTimeToDatabaseValueConversion(): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue(new DateTime(), $this->platform);
    }

    public function testConvertsDateTimeImmutableInstanceToPHPValue(): void
    {
        $date = new DateTimeImmutable();

        self::assertSame($date, $this->type->convertToPHPValue($date, $this->platform));
    }

    public function testConvertsNullToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertsTimeStringToPHPValue(): void
    {
        $this->platform->expects(self::once())
            ->method('getTimeFormatString')
            ->willReturn('H:i:s');

        $date = $this->type->convertToPHPValue('15:58:59', $this->platform);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('15:58:59', $date->format('H:i:s'));
    }

    public function testResetDateFractionsWhenConvertingToPHPValue(): void
    {
        $this->platform->expects(self::any())
            ->method('getTimeFormatString')
            ->willReturn('H:i:s');

        $date = $this->type->convertToPHPValue('15:58:59', $this->platform);

        self::assertNotNull($date);
        self::assertSame('1970-01-01 15:58:59', $date->format('Y-m-d H:i:s'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidTimeString(): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid time string', $this->platform);
    }
}
