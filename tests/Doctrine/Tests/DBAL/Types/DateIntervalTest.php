<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class DateIntervalTest  extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var MockPlatform
     */
    private $platform;

    /**
     * @var \Doctrine\DBAL\Types\DateIntervalType
     */
    private $type;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('dateinterval');

        self::assertInstanceOf('Doctrine\DBAL\Types\DateIntervalType', $this->type);
    }

    public function testDateIntervalConvertsToDatabaseValue()
    {
        $interval = new \DateInterval('P2Y1DT1H2M3S');

        $expected = 'P02Y00M01DT01H02M03S';
        $actual = $this->type->convertToDatabaseValue($interval, $this->platform);

        self::assertEquals($expected, $actual);
    }

    public function testDateIntervalConvertsToPHPValue()
    {
        $date = $this->type->convertToPHPValue('P02Y00M01DT01H02M03S', $this->platform);
        self::assertInstanceOf('DateInterval', $date);
        self::assertEquals('P02Y00M01DT01H02M03S', $date->format('P%YY%MM%DDT%HH%IM%SS'));
    }

    public function testInvalidDateIntervalFormatConversion()
    {
        $this->expectException('Doctrine\DBAL\Types\ConversionException');
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testDateIntervalNullConversion()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    /**
     * @group DBAL-1288
     */
    public function testRequiresSQLCommentHint()
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }

    /**
     * @dataProvider invalidPHPValuesProvider
     *
     * @param mixed $value
     */
    public function testInvalidTypeConversionToDatabaseValue($value)
    {
        $this->expectException('Doctrine\DBAL\Types\ConversionException');

        $this->type->convertToDatabaseValue($value, $this->platform);
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
            [new \DateTime()],
        ];
    }
}
