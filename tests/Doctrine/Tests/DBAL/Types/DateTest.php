<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;

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
        $this->assertTrue(
            $this->type->convertToPHPValue('1985-09-01', $this->platform)
            instanceof \DateTime
        );
    }

    public function testDateResetsNonDatePartsToZeroUnixTimeValues()
    {
        $date = $this->type->convertToPHPValue('1985-09-01', $this->platform);

        $this->assertEquals('00:00:00', $date->format('H:i:s'));
    }

    public function testDateRests_SummerTimeAffection()
    {
        date_default_timezone_set('Europe/Berlin');

        $date = $this->type->convertToPHPValue('2009-08-01', $this->platform);
        $this->assertEquals('00:00:00', $date->format('H:i:s'));
        $this->assertEquals('2009-08-01', $date->format('Y-m-d'));

        $date = $this->type->convertToPHPValue('2009-11-01', $this->platform);
        $this->assertEquals('00:00:00', $date->format('H:i:s'));
        $this->assertEquals('2009-11-01', $date->format('Y-m-d'));
    }

    public function testInvalidDateFormatConversion()
    {
        $this->setExpectedException('Doctrine\DBAL\Types\ConversionException');
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }
}
