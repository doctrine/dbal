<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Types;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use PHPUnit\Framework\MockObject\MockObject;
use function get_class;

class DateTimeTzImmutableTypeTest extends TestCase
{
    /** @var AbstractPlatform|MockObject */
    private $platform;

    /** @var DateTimeTzImmutableType */
    private $type;

    protected function setUp() : void
    {
        $this->type     = Type::getType('datetimetz_immutable');
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testFactoryCreatesCorrectType()
    {
        self::assertSame(DateTimeTzImmutableType::class, get_class($this->type));
    }

    public function testReturnsName()
    {
        self::assertSame('datetimetz_immutable', $this->type->getName());
    }

    public function testReturnsBindingType()
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue()
    {
        $date = $this->createMock(DateTimeImmutable::class);

        $this->platform->expects($this->once())
            ->method('getDateTimeTzFormatString')
            ->willReturn('Y-m-d H:i:s T');
        $date->expects($this->once())
            ->method('format')
            ->with('Y-m-d H:i:s T')
            ->willReturn('2016-01-01 15:58:59 UTC');

        self::assertSame(
            '2016-01-01 15:58:59 UTC',
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

    public function testConvertsDateTimeWithTimezoneStringToPHPValue()
    {
        $this->platform->expects($this->once())
            ->method('getDateTimeTzFormatString')
            ->willReturn('Y-m-d H:i:s T');

        $date = $this->type->convertToPHPValue('2016-01-01 15:58:59 UTC', $this->platform);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2016-01-01 15:58:59 UTC', $date->format('Y-m-d H:i:s T'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidDateTimeWithTimezoneString()
    {
        $this->platform->expects($this->atLeastOnce())
            ->method('getDateTimeTzFormatString')
            ->willReturn('Y-m-d H:i:s T');

        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid datetime with timezone string', $this->platform);
    }

    public function testRequiresSQLCommentHint()
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}
