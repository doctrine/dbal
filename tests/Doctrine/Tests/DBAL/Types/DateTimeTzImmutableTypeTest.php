<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\Type;

class DateTimeTzImmutableTypeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform|\Prophecy\Prophecy\ObjectProphecy
     */
    private $platform;

    /**
     * @var DateTimeTzImmutableType
     */
    private $type;

    protected function setUp()
    {
        $this->type = Type::getType('datetimetz_immutable');
        $this->platform = $this->prophesize(AbstractPlatform::class);
    }

    public function testFactoryCreatesCorrectType()
    {
        $this->assertSame(DateTimeTzImmutableType::class, get_class($this->type));
    }

    public function testReturnsName()
    {
        $this->assertSame('datetimetz_immutable', $this->type->getName());
    }

    public function testReturnsBindingType()
    {
        $this->assertSame(\PDO::PARAM_STR, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue()
    {
        $date = $this->prophesize(\DateTimeImmutable::class);

        $this->platform->getDateTimeTzFormatString()->willReturn('Y-m-d H:i:s T')->shouldBeCalled();
        $date->format('Y-m-d H:i:s T')->willReturn('2016-01-01 15:58:59 UTC')->shouldBeCalled();

        $this->assertSame(
            '2016-01-01 15:58:59 UTC',
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

    public function testConvertsDateTimeWithTimezoneStringToPHPValue()
    {
        $this->platform->getDateTimeTzFormatString()->willReturn('Y-m-d H:i:s T')->shouldBeCalled();

        $date = $this->type->convertToPHPValue('2016-01-01 15:58:59 UTC', $this->platform->reveal());

        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
        $this->assertSame('2016-01-01 15:58:59 UTC', $date->format('Y-m-d H:i:s T'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidDateTimeWithTimezoneString()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid datetime with timezone string', $this->platform->reveal());
    }

    public function testRequiresSQLCommentHint()
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform->reveal()));
    }
}
