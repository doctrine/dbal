<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class DateIntervalTest  extends \Doctrine\Tests\DbalTestCase
{
    protected
        $_platform,
        $_type;

    protected function setUp()
    {
        $this->_platform = new MockPlatform();
        $this->_type = Type::getType('dateinterval');
    }

    public function testDateIntervalConvertsToDatabaseValue()
    {
        $interval = new \DateInterval('P2Y1DT1H2M3S');

        $expected = 'P0002-00-01T01:02:03';
        $actual = $this->_type->convertToDatabaseValue($interval, $this->_platform);

        $this->assertEquals($expected, $actual);
    }

    public function testDateIntervalConvertsToPHPValue()
    {
        $date = $this->_type->convertToPHPValue('P0002-00-01T01:02:03', $this->_platform);
        $this->assertInstanceOf('DateInterval', $date);
        $this->assertEquals('P2Y0M1DT1H2M3S', $date->format('P%yY%mM%dDT%hH%iM%sS'));
    }

    public function testInvalidDateIntervalFormatConversion()
    {
        $this->setExpectedException('Doctrine\DBAL\Types\ConversionException');
        $this->_type->convertToPHPValue('abcdefg', $this->_platform);
    }

    public function testDateIntervalNullConversion()
    {
        $this->assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }
}
