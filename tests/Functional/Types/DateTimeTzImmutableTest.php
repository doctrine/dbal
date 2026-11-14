<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

final class DateTimeTzImmutableTest extends FunctionalTestCase
{
    private const TABLE_NAME = 'datetimetz_immutable_table';

    /** @return list<array{DateTimeImmutable}> */
    public static function dataValuesProvider(): array
    {
        $timezone = new DateTimeZone('Europe/Berlin');

        return [
            [new DateTimeImmutable('1985-09-01 10:10:10', $timezone)],
            [new DateTimeImmutable('2013-10-07T04:23:19-04:00', $timezone)],
        ];
    }

    #[DataProvider('dataValuesProvider')]
    public function testInsertAndRetrieveDateTimeTz(DateTimeImmutable $expected): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform->getDateTimeTzFormatString() === $platform->getDateTimeFormatString()) {
            self::markTestSkipped('This test requires the platform to support DateTime with timezone.');
        }

        if ($platform instanceof OraclePlatform) {
            self::markTestSkipped('Oracle driver have a bug in DateTimeTz format string.');
        }

        $this->createDatabase();

        $this->connection->insert(
            self::TABLE_NAME,
            ['val' => $expected],
            ['val' => Types::DATETIMETZ_IMMUTABLE],
        );

        $value = $this->connection->convertToPHPValue(
            $this->connection->fetchOne('SELECT val FROM ' . self::TABLE_NAME),
            Types::DATETIMETZ_IMMUTABLE,
        );

        self::assertInstanceOf(DateTimeImmutable::class, $value);
        self::assertEquals($expected, $value);
    }

    #[DataProvider('dataValuesProvider')]
    public function testRetrievalLegacyValue(DateTimeImmutable $value): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform instanceof SQLitePlatform) {
            self::markTestSkipped('This test requires the platform to be SQLite.');
        }

        $this->createDatabase();

        $this->connection->insert(
            self::TABLE_NAME,
            ['val' => $value],
            ['val' => Types::DATETIME_IMMUTABLE],
        );

        $actual = $this->connection->convertToPHPValue(
            $this->connection->fetchOne('SELECT val FROM ' . self::TABLE_NAME),
            Types::DATETIMETZ_IMMUTABLE,
        );

        self::assertInstanceOf(DateTimeImmutable::class, $actual);
        $expected = DateTimeImmutable::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value->format($platform->getDateTimeFormatString()),
        );
        self::assertEquals($expected, $actual);
    }

    private function createDatabase(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE_NAME)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('val')
                    ->setTypeName(Types::DATETIMETZ_IMMUTABLE)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);
    }
}
