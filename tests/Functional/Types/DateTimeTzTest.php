<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

final class DateTimeTzTest extends FunctionalTestCase
{
    /** @return DateTimeInterface[][] */
    public static function dataValuesProvider(): array
    {
        return [
            [new DateTime('1985-09-01 10:10:10')],
            [new DateTime('2013-10-07T04:23:19-04:00')],
        ];
    }

    #[DataProvider('dataValuesProvider')]
    public function testInsertAndRetrieveDateTimeTz(DateTimeInterface $expected): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform->getDateTimeTzFormatString() === $platform->getDateTimeFormatString()) {
            self::markTestSkipped('This test requires the platform to support DateTime with timezone.');
        }

        if ($platform instanceof OraclePlatform) {
            self::markTestSkipped('Oracle driver have a bug in DateTimeTz format string.');
        }

        $table = Table::editor()
            ->setUnquotedName('datetimetz_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('val')
                    ->setTypeName(Types::DATETIMETZ_MUTABLE)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->connection->insert(
            'datetimetz_table',
            ['val' => $expected],
            ['val' => Types::DATETIMETZ_MUTABLE],
        );

        $value = Type::getType(Types::DATETIMETZ_MUTABLE)->convertToPHPValue(
            $this->connection->fetchOne('SELECT val FROM datetimetz_table'),
            $platform,
        );

        self::assertInstanceOf(DateTime::class, $value);
        self::assertEquals($expected, $value);
    }
}
