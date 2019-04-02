<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Types;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use function get_class;

class DateTimeImmutableTypeTest extends TestCase
{
    /** @var AbstractPlatform|ObjectProphecy */
    private $platform;

    /** @var DateTimeImmutableType */
    private $type;

    protected function setUp() : void
    {
        $this->type     = Type::getType('datetime_immutable');
        $this->platform = $this->prophesize(AbstractPlatform::class);
    }

    public function testFactoryCreatesCorrectType() : void
    {
        self::assertSame(DateTimeImmutableType::class, get_class($this->type));
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

    public function testConvertsDateTimeStringToPHPValue() : void
    {
        $this->platform->getDateTimeFormatString()->willReturn('Y-m-d H:i:s')->shouldBeCalled();

        $date = $this->type->convertToPHPValue('2016-01-01 15:58:59', $this->platform->reveal());

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2016-01-01 15:58:59', $date->format('Y-m-d H:i:s'));
    }

    /**
     * @group DBAL-415
     */
    public function testConvertsDateTimeStringWithMicrosecondsToPHPValue() : void
    {
        $this->platform->getDateTimeFormatString()->willReturn('Y-m-d H:i:s');

        $date = $this->type->convertToPHPValue('2016-01-01 15:58:59.123456', $this->platform->reveal());

        self::assertSame('2016-01-01 15:58:59', $date->format('Y-m-d H:i:s'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidDateTimeString() : void
    {
        $this->platform->getDateTimeFormatString()->willReturn('Y-m-d H:i:s')->shouldBeCalled();

        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid datetime string', $this->platform->reveal());
    }

    public function testRequiresSQLCommentHint() : void
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform->reveal()));
    }
}
