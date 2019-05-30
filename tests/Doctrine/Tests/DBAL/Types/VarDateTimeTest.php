<?php

namespace Doctrine\Tests\DBAL\Types;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\VarDateTimeType;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class VarDateTimeTest extends DbalTestCase
{
    /** @var AbstractPlatform|MockObject */
    private $platform;

    /** @var VarDateTimeType */
    private $type;

    protected function setUp() : void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        if (! Type::hasType('vardatetime')) {
            Type::addType('vardatetime', VarDateTimeType::class);
        }
        $this->type = Type::getType('vardatetime');
    }

    public function testDateTimeConvertsToDatabaseValue() : void
    {
        $date = new DateTime('1985-09-01 10:10:10');

        $expected = $date->format($this->platform->getDateTimeTzFormatString());
        $actual   = $this->type->convertToDatabaseValue($date, $this->platform);

        self::assertEquals($expected, $actual);
    }

    public function testDateTimeConvertsToPHPValue() : void
    {
        // Birthday of jwage and also birthday of Doctrine. Send him a present ;)
        $date = $this->type->convertToPHPValue('1985-09-01 00:00:00', $this->platform);
        self::assertInstanceOf('DateTime', $date);
        self::assertEquals('1985-09-01 00:00:00', $date->format('Y-m-d H:i:s'));
        self::assertEquals('000000', $date->format('u'));
    }

    public function testInvalidDateTimeFormatConversion() : void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testConversionWithMicroseconds() : void
    {
        $date = $this->type->convertToPHPValue('1985-09-01 00:00:00.123456', $this->platform);
        self::assertInstanceOf('DateTime', $date);
        self::assertEquals('1985-09-01 00:00:00', $date->format('Y-m-d H:i:s'));
        self::assertEquals('123456', $date->format('u'));
    }

    public function testNullConversion() : void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertDateTimeToPHPValue() : void
    {
        $date = new DateTime('now');
        self::assertSame($date, $this->type->convertToPHPValue($date, $this->platform));
    }
}
