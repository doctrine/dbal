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
use PHPUnit\Framework\MockObject\MockObject;

class VarDateTimeImmutableTypeTest extends TestCase
{
    /** @var AbstractPlatform|MockObject */
    private $platform;

    /** @var VarDateTimeImmutableType */
    private $type;

    protected function setUp() : void
    {
        if (! Type::hasType('vardatetime_immutable')) {
            Type::addType('vardatetime_immutable', VarDateTimeImmutableType::class);
        }

        $this->type     = Type::getType('vardatetime_immutable');
        $this->platform = $this->getMockBuilder(AbstractPlatform::class)->getMock();
    }

    public function testReturnsName()
    {
        self::assertSame('datetime_immutable', $this->type->getName());
    }

    public function testReturnsBindingType()
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue()
    {
        $date = $this->getMockBuilder(DateTimeImmutable::class)->getMock();

        $this->platform->expects($this->once())
            ->method('getDateTimeFormatString')
            ->willReturn('Y-m-d H:i:s');
        $date->expects($this->once())
            ->method('format')
            ->with('Y-m-d H:i:s')
            ->willReturn('2016-01-01 15:58:59');

        self::assertSame(
            '2016-01-01 15:58:59',
            $this->type->convertToDatabaseValue($date, $this->platform)
        );
    }

    public function testConvertsNullToDatabaseValue()
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testDoesNotSupportMutableDateTimeToDatabaseValueConversion()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue(new DateTime(), $this->platform);
    }

    public function testConvertsDateTimeImmutableInstanceToPHPValue()
    {
        $date = new DateTimeImmutable();

        self::assertSame($date, $this->type->convertToPHPValue($date, $this->platform));
    }

    public function testConvertsNullToPHPValue()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertsDateishStringToPHPValue()
    {
        $this->platform->expects($this->never())
            ->method('getDateTimeFormatString');

        $date = $this->type->convertToPHPValue('2016-01-01 15:58:59.123456 UTC', $this->platform);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2016-01-01 15:58:59.123456 UTC', $date->format('Y-m-d H:i:s.u T'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidDateishString()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid date-ish string', $this->platform);
    }

    public function testRequiresSQLCommentHint()
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}
