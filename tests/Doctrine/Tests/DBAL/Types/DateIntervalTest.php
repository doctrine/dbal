<?php

namespace Doctrine\Tests\DBAL\Types;

use DateInterval;
use DateTime;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateIntervalType;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;

final class DateIntervalTest extends DbalTestCase
{
    /** @var AbstractPlatform|MockObject */
    private $platform;

    /** @var DateIntervalType */
    private $type;

    /**
     * {@inheritDoc}
     */
    protected function setUp() : void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = Type::getType('dateinterval');

        self::assertInstanceOf(DateIntervalType::class, $this->type);
    }

    public function testDateIntervalConvertsToDatabaseValue() : void
    {
        $interval = new DateInterval('P2Y1DT1H2M3S');

        $expected = '+P02Y00M01DT01H02M03S';
        $actual   = $this->type->convertToDatabaseValue($interval, $this->platform);

        self::assertEquals($expected, $actual);
    }

    public function testDateIntervalConvertsToPHPValue() : void
    {
        $interval = $this->type->convertToPHPValue('+P02Y00M01DT01H02M03S', $this->platform);

        self::assertInstanceOf(DateInterval::class, $interval);
        self::assertEquals('+P02Y00M01DT01H02M03S', $interval->format(DateIntervalType::FORMAT));
    }

    public function testNegativeDateIntervalConvertsToDatabaseValue() : void
    {
        $interval         = new DateInterval('P2Y1DT1H2M3S');
        $interval->invert = 1;

        $actual = $this->type->convertToDatabaseValue($interval, $this->platform);

        self::assertEquals('-P02Y00M01DT01H02M03S', $actual);
    }

    public function testNegativeDateIntervalConvertsToPHPValue() : void
    {
        $interval = $this->type->convertToPHPValue('-P02Y00M01DT01H02M03S', $this->platform);

        self::assertInstanceOf(DateInterval::class, $interval);
        self::assertEquals('-P02Y00M01DT01H02M03S', $interval->format(DateIntervalType::FORMAT));
    }

    public function testDateIntervalFormatWithoutSignConvertsToPHPValue() : void
    {
        $interval = $this->type->convertToPHPValue('P02Y00M01DT01H02M03S', $this->platform);

        self::assertInstanceOf(DateInterval::class, $interval);
        self::assertEquals('+P02Y00M01DT01H02M03S', $interval->format(DateIntervalType::FORMAT));
    }

    public function testInvalidDateIntervalFormatConversion() : void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testDateIntervalNullConversion() : void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testDateIntervalEmptyStringConversion() : void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('', $this->platform);
    }

    /**
     * @group DBAL-1288
     */
    public function testRequiresSQLCommentHint() : void
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }

    /**
     * @param mixed $value
     *
     * @dataProvider invalidPHPValuesProvider
     */
    public function testInvalidTypeConversionToDatabaseValue($value) : void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue($value, $this->platform);
    }

    /**
     * @return mixed[][]
     */
    public static function invalidPHPValuesProvider() : iterable
    {
        return [
            [0],
            [''],
            ['foo'],
            ['10:11:12'],
            ['2015-01-31'],
            ['2015-01-31 10:11:12'],
            [new stdClass()],
            [27],
            [-1],
            [1.2],
            [[]],
            [['an array']],
            [new DateTime()],
        ];
    }
}
