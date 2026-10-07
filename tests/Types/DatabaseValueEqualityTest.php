<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\DateImmutableType;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\DateTimeUtcImmutableType;
use Doctrine\DBAL\Types\DateTimeUtcType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\EquatableType;
use Doctrine\DBAL\Types\TimeImmutableType;
use Doctrine\DBAL\Types\TimeType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseValueEqualityTest extends TestCase
{
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->platform = new SQLitePlatform();
    }

    /**
     * Equality is based on the database representation: a difference the type
     * does not persist is not reported as a change.
     */
    #[DataProvider('equalityProvider')]
    public function testValuesAreEqual(EquatableType $type, object $a, object $b, bool $expected): void
    {
        self::assertSame($expected, $type->valuesAreEqual($a, $b, $this->platform));
        self::assertSame($expected, $type->valuesAreEqual($b, $a, $this->platform));
    }

    /** @return iterable<string, array{EquatableType, object, object, bool}> */
    public static function equalityProvider(): iterable
    {
        $date      = new DateTime('2020-01-01 10:00:00');
        $immutable = new DateTimeImmutable('2020-01-01 10:00:00');

        // Date types only store the day.
        yield 'date: same day, different time' => [new DateType(), $date, new DateTime('2020-01-01 23:59:59'), true];
        yield 'date: different day' => [new DateType(), $date, new DateTime('2020-01-02 10:00:00'), false];
        yield 'date_immutable: same day, different time' => [
            new DateImmutableType(),
            $immutable,
            new DateTimeImmutable('2020-01-01 23:59:59'),
            true,
        ];

        yield 'date_immutable: different day' => [
            new DateImmutableType(),
            $immutable,
            new DateTimeImmutable('2020-01-02 10:00:00'),
            false,
        ];

        // Time types only store the time of day.
        yield 'time: same time, different day' => [new TimeType(), $date, new DateTime('2021-06-15 10:00:00'), true];
        yield 'time: different time' => [new TimeType(), $date, new DateTime('2020-01-01 11:00:00'), false];
        yield 'time_immutable: same time, different day' => [
            new TimeImmutableType(),
            $immutable,
            new DateTimeImmutable('2021-06-15 10:00:00'),
            true,
        ];

        yield 'time_immutable: different time' => [
            new TimeImmutableType(),
            $immutable,
            new DateTimeImmutable('2020-01-01 11:00:00'),
            false,
        ];

        // Datetime types store the instant, without timezone information.
        yield 'datetime: same instant' => [new DateTimeType(), $date, new DateTime('2020-01-01 10:00:00'), true];
        yield 'datetime: different second' => [new DateTimeType(), $date, new DateTime('2020-01-01 10:00:01'), false];
        yield 'datetime_immutable: same instant' => [
            new DateTimeImmutableType(),
            $immutable,
            new DateTimeImmutable('2020-01-01 10:00:00'),
            true,
        ];

        yield 'datetime_immutable: different second' => [
            new DateTimeImmutableType(),
            $immutable,
            new DateTimeImmutable('2020-01-01 10:00:01'),
            false,
        ];

        // Datetimetz types store the timezone offset, so the same instant in two
        // timezones maps to two different database values.
        yield 'datetimetz: same offset' => [
            new DateTimeTzType(),
            new DateTime('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTime('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            true,
        ];

        yield 'datetimetz: same instant, different offset' => [
            new DateTimeTzType(),
            new DateTime('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTime('2020-01-01 11:00:00', new DateTimeZone('+01:00')),
            false,
        ];

        yield 'datetimetz_immutable: same offset' => [
            new DateTimeTzImmutableType(),
            new DateTimeImmutable('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            true,
        ];

        yield 'datetimetz_immutable: same instant, different offset' => [
            new DateTimeTzImmutableType(),
            new DateTimeImmutable('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2020-01-01 11:00:00', new DateTimeZone('+01:00')),
            false,
        ];

        // Datetime UTC types convert to UTC before storing, so the same instant
        // in two timezones maps to the same database value.
        yield 'datetime_utc: same instant, different timezone' => [
            new DateTimeUtcType(),
            new DateTime('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTime('2020-01-01 11:00:00', new DateTimeZone('+01:00')),
            true,
        ];

        yield 'datetime_utc: different instant' => [
            new DateTimeUtcType(),
            new DateTime('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTime('2020-01-01 10:00:01', new DateTimeZone('UTC')),
            false,
        ];

        yield 'datetime_utc_immutable: same instant, different timezone' => [
            new DateTimeUtcImmutableType(),
            new DateTimeImmutable('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2020-01-01 11:00:00', new DateTimeZone('+01:00')),
            true,
        ];

        yield 'datetime_utc_immutable: different instant' => [
            new DateTimeUtcImmutableType(),
            new DateTimeImmutable('2020-01-01 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2020-01-01 10:00:01', new DateTimeZone('UTC')),
            false,
        ];
    }
}
