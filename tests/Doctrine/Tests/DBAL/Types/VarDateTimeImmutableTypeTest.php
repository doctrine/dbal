<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\VarDateTimeImmutableType;
use Doctrine\DBAL\Types\Type;

class VarDateTimeImmutableTypeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform|\Prophecy\Prophecy\ObjectProphecy
     */
    private $platform;

    /**
     * @var VarDateTimeImmutableType
     */
    private $type;

    protected function setUp()
    {
        if ( ! Type::hasType('vardatetime_immutable')) {
            Type::addType('vardatetime_immutable', VarDateTimeImmutableType::class);
        }

        $this->type     = Type::getType('vardatetime_immutable');
        $this->platform = $this->prophesize(AbstractPlatform::class);
    }

    public function testReturnsName()
    {
        self::assertSame('datetime_immutable', $this->type->getName());
    }

    public function testReturnsBindingType()
    {
        self::assertSame(\PDO::PARAM_STR, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue()
    {
        $date = $this->prophesize(\DateTimeImmutable::class);

        $this->platform->getDateTimeFormatString()->willReturn('Y-m-d H:i:s')->shouldBeCalled();
        $date->format('Y-m-d H:i:s')->willReturn('2016-01-01 15:58:59')->shouldBeCalled();

        self::assertSame(
            '2016-01-01 15:58:59',
            $this->type->convertToDatabaseValue($date->reveal(), $this->platform->reveal())
        );
    }

    public function testConvertsNullToDatabaseValue()
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform->reveal()));
    }

    public function testDoesNotSupportMutableDateTimeToDatabaseValueConversion()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue(new \DateTime(), $this->platform->reveal());
    }

    public function testConvertsDateTimeImmutableInstanceToPHPValue()
    {
        $date = new \DateTimeImmutable();

        self::assertSame($date, $this->type->convertToPHPValue($date, $this->platform->reveal()));
    }

    public function testConvertsNullToPHPValue()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform->reveal()));
    }

    public function testConvertsDateishStringToPHPValue()
    {
        $this->platform->getDateTimeFormatString()->shouldNotBeCalled();

        $date = $this->type->convertToPHPValue('2016-01-01 15:58:59.123456 UTC', $this->platform->reveal());

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2016-01-01 15:58:59.123456 UTC', $date->format('Y-m-d H:i:s.u T'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidDateishString()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid date-ish string', $this->platform->reveal());
    }

    public function testRequiresSQLCommentHint()
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform->reveal()));
    }
}
