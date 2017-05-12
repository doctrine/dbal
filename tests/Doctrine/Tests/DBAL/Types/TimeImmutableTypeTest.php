<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\TimeImmutableType;
use Doctrine\DBAL\Types\Type;

class TimeImmutableTypeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform|\Prophecy\Prophecy\ObjectProphecy
     */
    private $platform;

    /**
     * @var TimeImmutableType
     */
    private $type;

    protected function setUp()
    {
        $this->type = Type::getType('time_immutable');
        $this->platform = $this->prophesize(AbstractPlatform::class);
    }

    public function testFactoryCreatesCorrectType()
    {
        $this->assertSame(TimeImmutableType::class, get_class($this->type));
    }

    public function testReturnsName()
    {
        $this->assertSame('time_immutable', $this->type->getName());
    }

    public function testReturnsBindingType()
    {
        $this->assertSame(\PDO::PARAM_STR, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue()
    {
        $date = $this->prophesize(\DateTimeImmutable::class);

        $this->platform->getTimeFormatString()->willReturn('H:i:s')->shouldBeCalled();
        $date->format('H:i:s')->willReturn('15:58:59')->shouldBeCalled();

        $this->assertSame(
            '15:58:59',
            $this->type->convertToDatabaseValue($date->reveal(), $this->platform->reveal())
        );
    }

    public function testConvertsNullToDatabaseValue()
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform->reveal()));
    }

    public function testDoesNotSupportMutableDateTimeToDatabaseValueConversion()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue(new \DateTime(), $this->platform->reveal());
    }

    public function testConvertsDateTimeImmutableInstanceToPHPValue()
    {
        $date = new \DateTimeImmutable();

        $this->assertSame($date, $this->type->convertToPHPValue($date, $this->platform->reveal()));
    }

    public function testConvertsNullToPHPValue()
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform->reveal()));
    }

    public function testConvertsTimeStringToPHPValue()
    {
        $this->platform->getTimeFormatString()->willReturn('H:i:s')->shouldBeCalled();

        $date = $this->type->convertToPHPValue('15:58:59', $this->platform->reveal());

        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
        $this->assertSame('15:58:59', $date->format('H:i:s'));
    }

    public function testResetDateFractionsWhenConvertingToPHPValue()
    {
        $this->platform->getTimeFormatString()->willReturn('H:i:s');

        $date = $this->type->convertToPHPValue('15:58:59', $this->platform->reveal());

        $this->assertSame('1970-01-01 15:58:59', $date->format('Y-m-d H:i:s'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidTimeString()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid time string', $this->platform->reveal());
    }

    public function testRequiresSQLCommentHint()
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform->reveal()));
    }
}
