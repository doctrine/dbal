<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use DateTimeInterface;
use Doctrine\DBAL\Platforms\OracleHptPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqliteHptPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Iterator;

use function date_create;
use function get_class;
use function sprintf;

final class DateTimeTest extends FunctionalTestCase
{
    /** @return Iterator<DateTimeInterface[]> */
    public static function dataValuesProvider(): Iterator
    {
        $datetimeList = [
            ['2013-04-15 10:10:10.1234567'],
            ['2013-04-15 10:10:10.123456'],
            ['2013-04-15 10:10:10.12345'],
            ['2013-04-15 10:10:10.1234'],
            ['2013-04-15 10:10:10.123'],
            ['2013-04-15 10:10:10.12'],
            ['2013-04-15 10:10:10.1'],
            ['2013-04-15 10:10:10'],

            ['2013-04-15 10:10:10.9876543'],
            ['2013-04-15 10:10:10.9876549'],
            ['2013-04-15 10:10:10.987654'],
            ['2013-04-15 10:10:10.98765'],
            ['2013-04-15 10:10:10.9876'],
            ['2013-04-15 10:10:10.987'],
            ['2013-04-15 10:10:10.98'],
            ['2013-04-15 10:10:10.9'],

            ['1999-12-31 23:59:59.99999'],
        ];

        foreach ($datetimeList as $datetime) {
            yield [date_create($datetime[0])];
        }
    }

    /** @dataProvider dataValuesProvider */
    public function testInsertAndRetrieveDateTime(DateTimeInterface $expected): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (
            ($platform instanceof SqlitePlatform && ! $platform instanceof SqliteHptPlatform) ||
            ($platform instanceof OraclePlatform && ! $platform instanceof OracleHptPlatform)
        ) {
            self::markTestSkipped(sprintf("Platform %s doesn't support variable precision time", get_class($platform)));
        }

        $table = new Table('datetime_test_table');

        $vals  = [];
        $types = [];

        for ($i = 0; $i <= 6; $i++) {
            $table->addColumn('val' . $i, Types::DATETIME_MUTABLE, ['scale' => $i]);
            $vals['val' . $i]  = Type::getType(Types::DATETIME_MUTABLE)
                                     ->convertToDatabaseValue($expected, $platform);
            $types['val' . $i] = Types::STRING;
        }

        $this->dropAndCreateTable($table);

        $this->connection->insert('datetime_test_table', $vals, $types);

        for ($i = 0; $i <= 6; $i++) {
            $value = Type::getType(Types::DATETIME_MUTABLE)->convertToPHPValue(
                $this->connection->fetchOne('SELECT val' . $i . ' FROM datetime_test_table'),
                $platform,
            );

            // PHP stores datetimes with microsecond precision so there will be a difference when column precision is
            // less than 6.
            self::assertEqualsWithDelta($expected, $value, 10 ** -$i);
        }
    }
}
