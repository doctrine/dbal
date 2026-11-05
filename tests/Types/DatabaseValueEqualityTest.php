<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ChangeDetectingType;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateImmutableType;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\DateTimeUtcImmutableType;
use Doctrine\DBAL\Types\DateTimeUtcType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\TimeImmutableType;
use Doctrine\DBAL\Types\TimeType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class DatabaseValueEqualityTest extends TestCase
{
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->platform = new SQLitePlatform();
    }

    /**
     * A change is detected only when the database representation differs: a
     * difference the type does not persist is not reported as a change.
     */
    #[DataProvider('changeProvider')]
    public function testHasValueChanged(
        ChangeDetectingType $type,
        object $oldValue,
        object $newValue,
        bool $expected,
    ): void {
        self::assertSame($expected, $type->hasValueChanged($oldValue, $newValue, $this->platform));
        self::assertSame($expected, $type->hasValueChanged($newValue, $oldValue, $this->platform));
    }

    public function testHasValueChangedThrowsConversionExceptionForUnsupportedValue(): void
    {
        $type = new DateType();

        $this->expectException(ConversionException::class);

        $type->hasValueChanged(new stdClass(), new stdClass(), $this->platform);
    }

    /** @return iterable<string, array{ChangeDetectingType, object, object, bool}> */
    public static function changeProvider(): iterable
    {
        $date      = new DateTime('2020-01-01 10:00:00');
        $immutable = new DateTimeImmutable('2020-01-01 10:00:00');

        // Date types only store the day.
        yield 'date: same day, different time' => [new DateType(), $date, new DateTime('2020-01-01 23:59:59'), false];
        yield 'date: different day' => [new DateType(), $date, new DateTime('2020-01-02 10:00:00'), true];
        yield 'date_immutable: same day, different time' => [
            new DateImmutableType(),
            $immutable,
            new DateTimeImmutable('2020-01-01 23:59:59'),
            false,
        ];

        yield 'date_immutable: different day' => [
            new DateImmutableType(),
            $immutable,
            new DateTimeImmutable('2020-01-02 10:00:00'),
            true,
        ];

        // Time types only store the time of day.
        yield 'time: same time, different day' => [new TimeType(), $date, new DateTime('2021-06-15 10:00:00'), false];
        yield 'time: different time' => [new TimeType(), $date, new DateTime('2020-01-01 11:00:00'), true];
        yield 'time_immutable: same time, different day' => [
            new TimeImmutableType(),
            $immutable,
            new DateTimeImmutable('2021-06-15 10:00:00'),
            false,
        ];

        yield 'time_immutable: different time' => [
            new TimeImmutableType(),
            $immutable,
            new DateTimeImmutable('2020-01-01 11:00:00'),
            true,
        ];

        // Datetime types store the instant, without timezone information.
        yield 'datetime: same instant' => [new DateTimeType(), $date, new DateTime('2020-01-01 10:00:00'), false];
        yield 'datetime: different second' => [new DateTimeType(), $date, new DateTime('2020-01-01 10:00:01'), true];
        yield 'datetime_immutable: same instant' => [
            new DateTimeImmutableType(),
            $immutable,
            new DateTimeImmutable('2020-01-01 10:00:00'),
            false,
        ];

        yield 'datetime_immutable: different second' => [
            new DateTimeImmutableType(),
            $immutable,
            new DateTimeImmutable('2020-01-01 10:00:01'),
            true,
        ];

        // Datetimetz types store the timezone offset, so the same instant in two
        // timezones maps to two different database values.
        yield 'datetimetz: same offset' => [
            new DateTimeTzType(),
            new DateTime('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTime('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            false,
        ];

        yield 'datetimetz: same instant, different offset' => [
            new DateTimeTzType(),
            new DateTime('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTime('2020-01-01 11:00:00', new DateTimeZone('+01:00')),
            true,
        ];

        yield 'datetimetz_immutable: same offset' => [
            new DateTimeTzImmutableType(),
            new DateTimeImmutable('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            false,
        ];

        yield 'datetimetz_immutable: same instant, different offset' => [
            new DateTimeTzImmutableType(),
            new DateTimeImmutable('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2020-01-01 11:00:00', new DateTimeZone('+01:00')),
            true,
        ];

        // Datetime UTC types convert to UTC before storing, so the same instant
        // in two timezones maps to the same database value.
        yield 'datetime_utc: same instant, different timezone' => [
            new DateTimeUtcType(),
            new DateTime('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTime('2020-01-01 11:00:00', new DateTimeZone('+01:00')),
            false,
        ];

        yield 'datetime_utc: different instant' => [
            new DateTimeUtcType(),
            new DateTime('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTime('2020-01-01 10:00:01', new DateTimeZone('UTC')),
            true,
        ];

        yield 'datetime_utc_immutable: same instant, different timezone' => [
            new DateTimeUtcImmutableType(),
            new DateTimeImmutable('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2020-01-01 11:00:00', new DateTimeZone('+01:00')),
            false,
        ];

        yield 'datetime_utc_immutable: different instant' => [
            new DateTimeUtcImmutableType(),
            new DateTimeImmutable('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2020-01-01 10:00:01', new DateTimeZone('UTC')),
            true,
        ];
    }
}
