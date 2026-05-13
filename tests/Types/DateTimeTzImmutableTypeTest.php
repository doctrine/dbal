<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Override;
use PHPUnit\Framework\TestCase;

class DateTimeTzImmutableTypeTest extends TestCase
{
    private DateTimeTzImmutableType $type;

    #[Override]
    protected function setUp(): void
    {
        $this->type = new DateTimeTzImmutableType();
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
        $date = new DateTimeImmutable('2016-01-01 15:58:59', new DateTimeZone('UTC'));

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects(self::once())
            ->method('getDateTimeTzFormatString')
            ->willReturn('Y-m-d H:i:s T');

        self::assertSame(
            '2016-01-01 15:58:59 UTC',
            $this->type->convertToDatabaseValue($date, $platform),
        );
    }

    public function testConvertsNullToDatabaseValue(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, self::createStub(AbstractPlatform::class)));
    }

    public function testDoesNotSupportMutableDateTimeToDatabaseValueConversion(): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue(new DateTime(), self::createStub(AbstractPlatform::class));
    }

    public function testConvertsDateTimeImmutableInstanceToPHPValue(): void
    {
        $date = new DateTimeImmutable();

        self::assertSame($date, $this->type->convertToPHPValue($date, self::createStub(AbstractPlatform::class)));
    }

    public function testConvertsNullToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, self::createStub(AbstractPlatform::class)));
    }

    public function testConvertsDateTimeWithTimezoneStringToPHPValue(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects(self::once())
            ->method('getDateTimeTzFormatString')
            ->willReturn('Y-m-d H:i:s T');

        $date = $this->type->convertToPHPValue('2016-01-01 15:58:59 UTC', $platform);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2016-01-01 15:58:59 UTC', $date->format('Y-m-d H:i:s T'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidDateTimeWithTimezoneString(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects(self::atLeastOnce())
            ->method('getDateTimeTzFormatString')
            ->willReturn('Y-m-d H:i:s T');

        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid datetime with timezone string', $platform);
    }
}
