<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeType;

class DateTimeTest extends BaseDateTypeTestCase
{
    protected function setUp(): void
    {
        $this->type = new DateTimeType();

        parent::setUp();

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
    }

    public function testInvalidDateTimeFormatConversion(): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testConvertsNonMatchingFormatToPhpValueWithParser(): void
    {
        $date = '1985/09/01 10:10:10.12345';

        $actual = $this->type->convertToPHPValue($date, $this->platform);

        self::assertEquals('1985-09-01 10:10:10', $actual->format('Y-m-d H:i:s'));
    }
}
