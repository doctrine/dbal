<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateImmutableType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DateImmutableTypeTest extends TestCase
{
    private AbstractPlatform&MockObject $platform;
    private DateImmutableType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new DateImmutableType();
    }

    public function testFactoryCreatesCorrectType(): void
    {
        self::assertSame(DateImmutableType::class, $this->type::class);
    }

    public function testReturnsBindingType(): void
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue(): void
    {
        $date = $this->createMock(DateTimeImmutable::class);

        $this->platform->expects(self::once())
            ->method('getDateFormatString')
            ->willReturn('Y-m-d');
        $date->expects(self::once())
            ->method('format')
            ->with('Y-m-d')
            ->willReturn('2016-01-01');

        self::assertSame(
            '2016-01-01',
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

    public function testConvertsDateStringToPHPValue(): void
    {
        $this->platform->expects(self::once())
            ->method('getDateFormatString')
            ->willReturn('Y-m-d');

        $date = $this->type->convertToPHPValue('2016-01-01', $this->platform);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2016-01-01', $date->format('Y-m-d'));
    }

    public function testResetTimeFractionsWhenConvertingToPHPValue(): void
    {
        $this->platform->expects(self::any())
            ->method('getDateFormatString')
            ->willReturn('Y-m-d');

        $date = $this->type->convertToPHPValue('2016-01-01', $this->platform);

        self::assertNotNull($date);
        self::assertSame('2016-01-01 00:00:00.000000', $date->format('Y-m-d H:i:s.u'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidDateString(): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid date string', $this->platform);
    }
}
