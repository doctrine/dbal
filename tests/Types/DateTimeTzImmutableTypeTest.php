<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DateTimeTzImmutableTypeTest extends TestCase
{
    private AbstractPlatform&MockObject $platform;
    private DateTimeTzImmutableType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new DateTimeTzImmutableType();
    }

    public function testFactoryCreatesCorrectType(): void
    {
        self::assertSame(DateTimeTzImmutableType::class, $this->type::class);
    }

    public function testReturnsBindingType(): void
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue(): void
    {
        $date = $this->createMock(DateTimeImmutable::class);

        $this->platform->expects(self::once())
            ->method('getDateTimeTzFormatString')
            ->willReturn('Y-m-d H:i:s T');
        $date->expects(self::once())
            ->method('format')
            ->with('Y-m-d H:i:s T')
            ->willReturn('2016-01-01 15:58:59 UTC');

        self::assertSame(
            '2016-01-01 15:58:59 UTC',
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

    public function testConvertsDateTimeWithTimezoneStringToPHPValue(): void
    {
        $this->platform->expects(self::once())
            ->method('getDateTimeTzFormatString')
            ->willReturn('Y-m-d H:i:s T');

        $date = $this->type->convertToPHPValue('2016-01-01 15:58:59 UTC', $this->platform);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2016-01-01 15:58:59 UTC', $date->format('Y-m-d H:i:s T'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidDateTimeWithTimezoneString(): void
    {
        $this->platform->expects(self::atLeastOnce())
            ->method('getDateTimeTzFormatString')
            ->willReturn('Y-m-d H:i:s T');

        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid datetime with timezone string', $this->platform);
    }
}
