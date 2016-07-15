<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateImmutableType;
use Doctrine\DBAL\Types\Type;

class DateImmutableTypeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform|\Prophecy\Prophecy\ObjectProphecy
     */
    private $platform;

    /**
     * @var DateImmutableType
     */
    private $type;

    protected function setUp()
    {
        $this->type = Type::getType('date_immutable');
        $this->platform = $this->prophesize(AbstractPlatform::class);
    }

    public function testFactoryCreatesCorrectType()
    {
        $this->assertSame(DateImmutableType::class, get_class($this->type));
    }

    public function testReturnsName()
    {
        $this->assertSame('date_immutable', $this->type->getName());
    }

    public function testReturnsBindingType()
    {
        $this->assertSame(\PDO::PARAM_STR, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue()
    {
        $date = $this->prophesize(\DateTimeImmutable::class);

        $this->platform->getDateFormatString()->willReturn('Y-m-d')->shouldBeCalled();
        $date->format('Y-m-d')->willReturn('2016-01-01')->shouldBeCalled();

        $this->assertSame(
            '2016-01-01',
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

    public function testConvertsDateStringToPHPValue()
    {
        $this->platform->getDateFormatString()->willReturn('Y-m-d')->shouldBeCalled();

        $date = $this->type->convertToPHPValue('2016-01-01', $this->platform->reveal());

        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
        $this->assertSame('2016-01-01', $date->format('Y-m-d'));
    }

    public function testResetTimeFractionsWhenConvertingToPHPValue()
    {
        $this->platform->getDateFormatString()->willReturn('Y-m-d');

        $date = $this->type->convertToPHPValue('2016-01-01', $this->platform->reveal());

        $this->assertSame('2016-01-01 00:00:00.000000', $date->format('Y-m-d H:i:s.u'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidDateString()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid date string', $this->platform->reveal());
    }

    public function testRequiresSQLCommentHint()
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform->reveal()));
    }
}
