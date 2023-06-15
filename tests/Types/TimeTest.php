<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\TimeType;

class TimeTest extends BaseDateTypeTestCase
{
    protected function setUp(): void
    {
        $this->type = new TimeType();

        parent::setUp();

        $this->platform->method('getTimeFormatString')
            ->willReturn('H:i:s');
    }

    public function testTimeConvertsToPHPValue(): void
    {
        self::assertInstanceOf(DateTime::class, $this->type->convertToPHPValue('5:30:55', $this->platform));
    }

    public function testDateFieldResetInPHPValue(): void
    {
        $time = $this->type->convertToPHPValue('01:23:34', $this->platform);

        self::assertEquals('01:23:34', $time->format('H:i:s'));
        self::assertEquals('1970-01-01', $time->format('Y-m-d'));
    }

    public function testInvalidTimeFormatConversion(): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }
}
