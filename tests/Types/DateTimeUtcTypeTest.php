<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeUtcType;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DateTimeUtcTypeTest extends TestCase
{
    private AbstractPlatform&Stub $platform;
    private DateTimeUtcType $type;

    protected function setUp(): void
    {
        $this->platform = self::createStub(AbstractPlatform::class);
        $this->platform->method('getDateTimeFormatString')
            ->willReturn('Y-m-d H:i:s');
        $this->type = new DateTimeUtcType();
    }

    public function testReturnsBindingType(): void
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testDelegatesSQLDeclarationToTheDateTimeTypeDeclaration(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects(self::once())
            ->method('getDateTimeTypeDeclarationSQL')
            ->with(['foo' => 'bar'])
            ->willReturn('DATETIME');

        self::assertSame('DATETIME', $this->type->getSQLDeclaration(['foo' => 'bar'], $platform));
    }

    public function testConvertsNonUtcDateTimeToUtcDatabaseValue(): void
    {
        // America/New_York is UTC-5 in January (no DST).
        $date = new DateTime('2016-01-01 15:58:59', new DateTimeZone('America/New_York'));

        self::assertSame(
            '2016-01-01 20:58:59',
            $this->type->convertToDatabaseValue($date, $this->platform),
        );
    }

    public function testDoesNotMutateTheGivenDateTime(): void
    {
        $timezone = new DateTimeZone('America/New_York');
        $date     = new DateTime('2016-01-01 15:58:59', $timezone);

        $this->type->convertToDatabaseValue($date, $this->platform);

        self::assertSame($timezone->getName(), $date->getTimezone()->getName());
        self::assertSame('2016-01-01 15:58:59', $date->format('Y-m-d H:i:s'));
    }

    public function testConvertsNullToDatabaseValue(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testThrowsForInvalidValueOnConversionToDatabaseValue(): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue(new DateTimeImmutable(), $this->platform);
    }

    public function testConvertsDatabaseValueToUtcDateTime(): void
    {
        $date = $this->type->convertToPHPValue('2016-01-01 20:58:59', $this->platform);

        self::assertInstanceOf(DateTime::class, $date);
        self::assertSame('2016-01-01 20:58:59', $date->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $date->getTimezone()->getName());
        self::assertSame(0, $date->getOffset());
    }

    public function testConvertsNullToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertsDateTimeInstanceToPHPValueUnchanged(): void
    {
        $date = new DateTime();

        self::assertSame($date, $this->type->convertToPHPValue($date, $this->platform));
    }

    public function testThrowsExceptionForInvalidDateTimeString(): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid datetime string', $this->platform);
    }
}
