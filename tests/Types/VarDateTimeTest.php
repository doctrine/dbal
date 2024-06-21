<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\VarDateTimeType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VarDateTimeTest extends TestCase
{
    private AbstractPlatform&MockObject $platform;
    private VarDateTimeType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new VarDateTimeType();

        $this->platform->method('getDateTimeFormatString')
            ->willReturn('Y-m-d H:i:s');
    }

    public function testDateTimeConvertsToDatabaseValue(): void
    {
        $date = new DateTime('1985-09-01 10:10:10');

        $expected = $date->format($this->platform->getDateTimeFormatString());
        $actual   = $this->type->convertToDatabaseValue($date, $this->platform);

        self::assertEquals($expected, $actual);
    }

    public function testDateTimeConvertsToPHPValue(): void
    {
        // Birthday of jwage and also birthday of Doctrine. Send him a present ;)
        $date = $this->type->convertToPHPValue('1985-09-01 00:00:00', $this->platform);
        self::assertInstanceOf(DateTime::class, $date);
        self::assertEquals('1985-09-01 00:00:00', $date->format('Y-m-d H:i:s'));
        self::assertEquals('000000', $date->format('u'));
    }

    public function testInvalidDateTimeFormatConversion(): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testConversionWithMicroseconds(): void
    {
        $date = $this->type->convertToPHPValue('1985-09-01 00:00:00.123456', $this->platform);
        self::assertInstanceOf(DateTime::class, $date);
        self::assertEquals('1985-09-01 00:00:00', $date->format('Y-m-d H:i:s'));
        self::assertEquals('123456', $date->format('u'));
    }

    public function testNullConversion(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertDateTimeToPHPValue(): void
    {
        $date = new DateTime('now');
        self::assertSame($date, $this->type->convertToPHPValue($date, $this->platform));
    }
}
