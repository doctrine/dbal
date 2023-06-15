<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateType;

use function date_default_timezone_set;

class DateTest extends BaseDateTypeTestCase
{
    protected function setUp(): void
    {
        $this->type = new DateType();

        parent::setUp();

        $this->platform->method('getDateFormatString')
            ->willReturn('Y-m-d');
    }

    public function testDateConvertsToPHPValue(): void
    {
        // Birthday of jwage and also birthday of Doctrine. Send him a present ;)
        self::assertInstanceOf(
            DateTime::class,
            $this->type->convertToPHPValue('1985-09-01', $this->platform),
        );
    }

    public function testDateResetsNonDatePartsToZeroUnixTimeValues(): void
    {
        $date = $this->type->convertToPHPValue('1985-09-01', $this->platform);

        self::assertEquals('00:00:00', $date->format('H:i:s'));
    }

    public function testDateRestsSummerTimeAffection(): void
    {
        date_default_timezone_set('Europe/Berlin');

        $date = $this->type->convertToPHPValue('2009-08-01', $this->platform);
        self::assertEquals('00:00:00', $date->format('H:i:s'));
        self::assertEquals('2009-08-01', $date->format('Y-m-d'));

        $date = $this->type->convertToPHPValue('2009-11-01', $this->platform);
        self::assertEquals('00:00:00', $date->format('H:i:s'));
        self::assertEquals('2009-11-01', $date->format('Y-m-d'));
    }

    public function testInvalidDateFormatConversion(): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }
}
