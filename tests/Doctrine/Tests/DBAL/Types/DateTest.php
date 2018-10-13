<?php

namespace Doctrine\Tests\DBAL\Types;

use DateTime;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use function date_default_timezone_set;

class DateTest extends BaseDateTypeTestCase
{
    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->type = Type::getType('date');

        parent::setUp();
    }

    public function testDateConvertsToPHPValue()
    {
        // Birthday of jwage and also birthday of Doctrine. Send him a present ;)
        self::assertInstanceOf(
            DateTime::class,
            $this->type->convertToPHPValue('1985-09-01', $this->platform)
        );
    }

    public function testDateResetsNonDatePartsToZeroUnixTimeValues()
    {
        $date = $this->type->convertToPHPValue('1985-09-01', $this->platform);

        self::assertEquals('00:00:00', $date->format('H:i:s'));
    }

    public function testDateRestsSummerTimeAffection()
    {
        date_default_timezone_set('Europe/Berlin');

        $date = $this->type->convertToPHPValue('2009-08-01', $this->platform);
        self::assertEquals('00:00:00', $date->format('H:i:s'));
        self::assertEquals('2009-08-01', $date->format('Y-m-d'));

        $date = $this->type->convertToPHPValue('2009-11-01', $this->platform);
        self::assertEquals('00:00:00', $date->format('H:i:s'));
        self::assertEquals('2009-11-01', $date->format('Y-m-d'));
    }

    public function testInvalidDateFormatConversion()
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }
}
