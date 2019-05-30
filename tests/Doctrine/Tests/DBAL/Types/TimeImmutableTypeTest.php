<?php

namespace Doctrine\Tests\DBAL\Types;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\TimeImmutableType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use function get_class;

class TimeImmutableTypeTest extends TestCase
{
    /** @var AbstractPlatform|ObjectProphecy */
    private $platform;

    /** @var TimeImmutableType */
    private $type;

    protected function setUp() : void
    {
        $this->type     = Type::getType('time_immutable');
        $this->platform = $this->prophesize(AbstractPlatform::class);
    }

    public function testFactoryCreatesCorrectType() : void
    {
        self::assertSame(TimeImmutableType::class, get_class($this->type));
    }

    public function testReturnsName() : void
    {
        self::assertSame('time_immutable', $this->type->getName());
    }

    public function testReturnsBindingType() : void
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue() : void
    {
        $date = $this->prophesize(DateTimeImmutable::class);

        $this->platform->getTimeFormatString()->willReturn('H:i:s')->shouldBeCalled();
        $date->format('H:i:s')->willReturn('15:58:59')->shouldBeCalled();

        self::assertSame(
            '15:58:59',
            $this->type->convertToDatabaseValue($date->reveal(), $this->platform->reveal())
        );
    }

    public function testConvertsNullToDatabaseValue() : void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform->reveal()));
    }

    public function testDoesNotSupportMutableDateTimeToDatabaseValueConversion() : void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue(new DateTime(), $this->platform->reveal());
    }

    public function testConvertsDateTimeImmutableInstanceToPHPValue() : void
    {
        $date = new DateTimeImmutable();

        self::assertSame($date, $this->type->convertToPHPValue($date, $this->platform->reveal()));
    }

    public function testConvertsNullToPHPValue() : void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform->reveal()));
    }

    public function testConvertsTimeStringToPHPValue() : void
    {
        $this->platform->getTimeFormatString()->willReturn('H:i:s')->shouldBeCalled();

        $date = $this->type->convertToPHPValue('15:58:59', $this->platform->reveal());

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('15:58:59', $date->format('H:i:s'));
    }

    public function testResetDateFractionsWhenConvertingToPHPValue() : void
    {
        $this->platform->getTimeFormatString()->willReturn('H:i:s');

        $date = $this->type->convertToPHPValue('15:58:59', $this->platform->reveal());

        self::assertSame('1970-01-01 15:58:59', $date->format('Y-m-d H:i:s'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidTimeString() : void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid time string', $this->platform->reveal());
    }

    public function testRequiresSQLCommentHint() : void
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform->reveal()));
    }
}
