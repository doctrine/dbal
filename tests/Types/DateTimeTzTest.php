<?php

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeTzType;

class DateTimeTzTest extends BaseDateTypeTestCase
{
    protected function setUp(): void
    {
        $this->type = new DateTimeTzType();

        parent::setUp();
    }

    public function testDateTimeConvertsToDatabaseValue(): void
    {
        $date = new DateTime('1985-09-01 10:10:10');

        $expected = $date->format($this->platform->getDateTimeTzFormatString());
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

    public function testInvalidDateFormatConversion(): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testNativeTimezoneSupport(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        self::assertTrue($this->type->requiresSQLCommentHint($platform));

        $platform->expects(self::any())
            ->method('hasNativeTimezoneType')
            ->will(self::returnValue(true));

        self::assertFalse($this->type->requiresSQLCommentHint($platform));
    }
}
