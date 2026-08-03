<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeUtcImmutableType;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DateTimeUtcImmutableTypeTest extends TestCase
{
    private AbstractPlatform&Stub $platform;
    private DateTimeUtcImmutableType $type;

    protected function setUp(): void
    {
        $this->platform = self::createStub(AbstractPlatform::class);
        $this->platform->method('getDateTimeFormatString')
            ->willReturn('Y-m-d H:i:s');
        $this->type = new DateTimeUtcImmutableType();
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
        $date = new DateTimeImmutable('2016-01-01 15:58:59', new DateTimeZone('America/New_York'));

        self::assertSame(
            '2016-01-01 20:58:59',
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

    public function testConvertsDatabaseValueToUtcDateTimeImmutable(): void
    {
        $date = $this->type->convertToPHPValue('2016-01-01 20:58:59', $this->platform);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2016-01-01 20:58:59', $date->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $date->getTimezone()->getName());
        self::assertSame(0, $date->getOffset());
    }

    public function testConvertsNullToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertsDateTimeImmutableInstanceToPHPValueUnchanged(): void
    {
        $date = new DateTimeImmutable();

        self::assertSame($date, $this->type->convertToPHPValue($date, $this->platform));
    }

    public function testThrowsExceptionForInvalidDateTimeString(): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid datetime string', $this->platform);
    }
}
