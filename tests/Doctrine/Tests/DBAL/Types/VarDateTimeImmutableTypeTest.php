<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Types;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\VarDateTimeImmutableType;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;

class VarDateTimeImmutableTypeTest extends TestCase
{
    /** @var AbstractPlatform|ObjectProphecy */
    private $platform;

    /** @var VarDateTimeImmutableType */
    private $type;

    protected function setUp() : void
    {
        if (! Type::hasType('vardatetime_immutable')) {
            Type::addType('vardatetime_immutable', VarDateTimeImmutableType::class);
        }

        $this->type     = Type::getType('vardatetime_immutable');
        $this->platform = $this->prophesize(AbstractPlatform::class);
    }

    public function testReturnsName() : void
    {
        self::assertSame('datetime_immutable', $this->type->getName());
    }

    public function testReturnsBindingType() : void
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue() : void
    {
        $date = $this->prophesize(DateTimeImmutable::class);

        $this->platform->getDateTimeFormatString()->willReturn('Y-m-d H:i:s')->shouldBeCalled();
        $date->format('Y-m-d H:i:s')->willReturn('2016-01-01 15:58:59')->shouldBeCalled();

        self::assertSame(
            '2016-01-01 15:58:59',
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

    public function testConvertsDateishStringToPHPValue() : void
    {
        $this->platform->getDateTimeFormatString()->shouldNotBeCalled();

        $date = $this->type->convertToPHPValue('2016-01-01 15:58:59.123456 UTC', $this->platform->reveal());

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2016-01-01 15:58:59.123456 UTC', $date->format('Y-m-d H:i:s.u T'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidDateishString() : void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid date-ish string', $this->platform->reveal());
    }

    public function testRequiresSQLCommentHint() : void
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform->reveal()));
    }
}
