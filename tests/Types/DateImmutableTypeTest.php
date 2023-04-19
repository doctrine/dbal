<?php

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateImmutableType;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function get_class;

class DateImmutableTypeTest extends TestCase
{
    use VerifyDeprecations;

    /** @var AbstractPlatform&MockObject */
    private AbstractPlatform $platform;

    private DateImmutableType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new DateImmutableType();
    }

    public function testFactoryCreatesCorrectType(): void
    {
        self::assertSame(DateImmutableType::class, get_class($this->type));
    }

    public function testReturnsName(): void
    {
        self::assertSame('date_immutable', $this->type->getName());
    }

    public function testReturnsBindingType(): void
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testConvertsDateTimeImmutableInstanceToDatabaseValue(): void
    {
        $date = $this->createMock(DateTimeImmutable::class);

        $this->platform->expects(self::once())
            ->method('getDateFormatString')
            ->willReturn('Y-m-d');
        $date->expects(self::exactly(2))
            ->method('format')
            ->willReturnCallback(static function (string $format): string {
                switch ($format) {
                    case 'O':
                        return 'UTC';
                    case 'Y-m-d':
                        return '2016-01-01';
                    default:
                        throw new InvalidArgumentException();
                }
            });

        self::assertSame(
            '2016-01-01',
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

    public function testConvertsDateStringToPHPValue(): void
    {
        $this->platform->expects(self::once())
            ->method('getDateFormatString')
            ->willReturn('Y-m-d');

        $date = $this->type->convertToPHPValue('2016-01-01', $this->platform);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2016-01-01', $date->format('Y-m-d'));
    }

    public function testResetTimeFractionsWhenConvertingToPHPValue(): void
    {
        $this->platform->expects(self::any())
            ->method('getDateFormatString')
            ->willReturn('Y-m-d');

        $date = $this->type->convertToPHPValue('2016-01-01', $this->platform);

        self::assertSame('2016-01-01 00:00:00.000000', $date->format('Y-m-d H:i:s.u'));
    }

    public function testThrowsExceptionDuringConversionToPHPValueWithInvalidDateString(): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('invalid date string', $this->platform);
    }

    public function testRequiresSQLCommentHint(): void
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }

    /** @dataProvider provideDateTimeInstancesWithTimezone */
    public function testTimezoneDeprecationFromConvertsToDatabaseValue(
        string $defaultTimeZone,
        DateTimeImmutable $date
    ): void {
        $this->iniSet('date.timezone', $defaultTimeZone);

        $defaultOffset = (new DateTimeImmutable())->format('O');

        self::assertFalse($defaultOffset === $date->format('O'));

        $this->platform->expects(self::once())
            ->method('getDateFormatString')
            ->willReturn('Y-m-d');

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6020');

        $this->type->convertToDatabaseValue($date, $this->platform);
    }

    /** @dataProvider provideDateTimeInstancesWithTimezone */
    public function testTimezoneDeprecationFromConvertToPHPValue(string $defaultTimeZone, DateTimeImmutable $date): void
    {
        $this->iniSet('date.timezone', $defaultTimeZone);

        $defaultOffset = (new DateTimeImmutable())->format('O');

        self::assertFalse($defaultOffset === $date->format('O'));

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6020');

        $this->type->convertToPHPValue($date, $this->platform);
    }

    /** @psalm-return iterable<string, array{0: string, 1: DateTimeImmutable}> */
    public function provideDateTimeInstancesWithTimezone(): iterable
    {
        yield 'timezone_utc' => [
            'UTC',
            (new DateTimeImmutable())->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires')),
        ];

        yield 'timezone_amsterdam' => [
            'Europe/Amsterdam',
            (new DateTimeImmutable())->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires')),
        ];

        yield 'offset_utc' => [
            'UTC',
            (new DateTimeImmutable())->setTimezone(new DateTimeZone('-0300')),
        ];
    }

    /** @dataProvider provideDateTimeInstancesWithNoTimezoneDiff */
    public function testNoTimezoneInValueConversion(string $defaultTimeZone, DateTimeImmutable $date): void
    {
        $this->iniSet('date.timezone', $defaultTimeZone);

        $this->platform->expects(self::once())
            ->method('getDateFormatString')
            ->willReturn('Y-m-d');

        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6020');

        $this->type->convertToDatabaseValue($date, $this->platform);
        $this->type->convertToPHPValue($date, $this->platform);
    }

    /** @psalm-return iterable<string, array{0: string, 1: DateTimeImmutable}> */
    public function provideDateTimeInstancesWithNoTimezoneDiff(): iterable
    {
        yield 'timezone_utc' => [
            'UTC',
            (new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC')),
        ];

        yield 'timezone_buenos_aires' => [
            'America/Argentina/Buenos_Aires',
            (new DateTimeImmutable())->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires')),
        ];

        yield 'same_offset_with_different_timezones' => [
            'America/Sao_Paulo',
            (new DateTimeImmutable())->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires')),
        ];

        yield 'offset_-0300' => [
            'America/Argentina/Buenos_Aires',
            (new DateTimeImmutable())->setTimezone(new DateTimeZone('-0300')),
        ];
    }
}
