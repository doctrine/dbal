<?php

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Types;
use Doctrine\DBAL\Types\VarDateTimeImmutableType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VarDateTimeImmutableTypeTest extends TestCase
{
    /** @var AbstractPlatform&MockObject */
    private AbstractPlatform $platform;

    private VarDateTimeImmutableType $type;

    protected function setUp(): void
    {
        $this->platform = $this->getMockForAbstractClass(AbstractPlatform::class);
        $this->type     = new VarDateTimeImmutableType();
    }

    public function testReturnsName(): void
    {
        self::assertSame(Types::DATETIME_IMMUTABLE, $this->type->getName());
    }

    public function testReturnsBindingType(): void
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue(): void
    {
        $date = $this->getMockBuilder(DateTimeImmutable::class)->getMock();

        $date->expects(self::once())
            ->method('format')
            ->with('Y-m-d H:i:s')
            ->willReturn('2016-01-01 15:58:59');

        self::assertSame(
            '2016-01-01 15:58:59',
            $this->type->convertToDatabaseValue($date, $this->platform),
        );
    }

    public function testConvertsNullToDatabaseValue(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testDoesNotSupportMutableDateTimeToDatabaseValueConversion(): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue(new DateTime(), $this->platform);
    }

    public function testConvertsDateTimeImmutableInstanceToPHPValue(): void
    {
        $date = new DateTimeImmutable();

        self::assertSame($date, $this->type->convertToPHPValue($date, $this->platform));
    }

    public function testConvertsNullToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertsDateishStringToPHPValue(): void
    {
        $date = $this->type->convertToPHPValue('2016-01-01 15:58:59.123456 UTC', $this->platform);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2016-01-01 15:58:59.123456 UTC', $date->format('Y-m-d H:i:s.u T'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidDateishString(): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid date-ish string', $this->platform);
    }

    public function testRequiresSQLCommentHint(): void
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}
