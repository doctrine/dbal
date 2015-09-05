<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class DateTimeTzTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var MockPlatform
     */
    private $_platform;

    /**
     * @var \Doctrine\DBAL\Types\DateTimeTzType
     */
    private $_type;

    protected function setUp()
    {
        $this->_platform = new MockPlatform();
        $this->_type = Type::getType('datetimetz');
    }

    public function testDateTimeConvertsToDatabaseValue()
    {
        $date = new \DateTime('1985-09-01 10:10:10');

        $expected = $date->format($this->_platform->getDateTimeTzFormatString());
        $actual = $this->_type->convertToDatabaseValue($date, $this->_platform);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider invalidPHPValuesProvider
     *
     * @param mixed $value
     */
    public function testInvalidTypeConversionToDatabaseValue($value)
    {
        $this->setExpectedException('Doctrine\DBAL\Types\ConversionException');

        $this->_type->convertToDatabaseValue($value, $this->_platform);
    }

    public function testDateTimeConvertsToPHPValue()
    {
        // Birthday of jwage and also birthday of Doctrine. Send him a present ;)
        $date = $this->_type->convertToPHPValue('1985-09-01 00:00:00', $this->_platform);
        $this->assertInstanceOf('DateTime', $date);
        $this->assertEquals('1985-09-01 00:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testInvalidDateFormatConversion()
    {
        $this->setExpectedException('Doctrine\DBAL\Types\ConversionException');
        $this->_type->convertToPHPValue('abcdefg', $this->_platform);
    }

    public function testNullConversion()
    {
        $this->assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }

    public function testConvertDateTimeToPHPValue()
    {
        $date = new \DateTime("now");
        $this->assertSame($date, $this->_type->convertToPHPValue($date, $this->_platform));
    }

    /**
     * @return mixed[][]
     */
    public function invalidPHPValuesProvider()
    {
        return [
            [0],
            [''],
            ['foo'],
            ['10:11:12'],
            ['2015-01-31'],
            ['2015-01-31 10:11:12'],
            [new \stdClass()],
            [$this],
            [27],
            [-1],
            [1.2],
            [[]],
            [['an array']],
        ];
    }
}
