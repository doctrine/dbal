<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use DateTimeInterface;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Iterator;

use function date_create;
use function get_class;
use function sprintf;
use function timezone_open;

/**
 * Tests inserting and selecting datetime format string with varying precisions of decimal seconds in different
 * timezones.
 *
 * Only PostgreSQL, Oracle and SQL server support storing timezone information in the database. On other platforms,
 * the timezone is lost and only the time is stored the timezone is ignored in these cases.
 */
final class DateTimeTzTest extends FunctionalTestCase
{
    /** @return Iterator<DateTimeInterface[]> */
    public static function dataValuesProvider(): Iterator
    {
        $datetimeTzList = [
            ['2013-04-15 10:10:10.1234567', '+00:00'],
            ['2013-04-15 10:10:10.123456', '+01:00'],
            ['2013-04-15 10:10:10.12345', '+06:00'],
            ['2013-04-15 10:10:10.1234', '+11:00'],
            ['2013-04-15 10:10:10.123', '-11:00'],
            ['2013-04-15 10:10:10.12', '-06:00'],
            ['2013-04-15 10:10:10.1', '-01:00'],
            ['2013-04-15 10:10:10', '+00:00'],

            ['2013-04-15 10:10:10.9876543', '+00:00'],
            ['2013-04-15 10:10:10.9876549', '-01:00'],
            ['2013-04-15 10:10:10.987654', '-06:00'],
            ['2013-04-15 10:10:10.98765', '-11:00'],
            ['2013-04-15 10:10:10.9876', '+11:00'],
            ['2013-04-15 10:10:10.987', '+06:00'],
            ['2013-04-15 10:10:10.98', '+01:00'],
            ['2013-04-15 10:10:10.9', '+00:00'],

            ['1999-12-31 23:59:59.99999', '+03:00'],
        ];

        foreach ($datetimeTzList as $datetimeTz) {
            yield [
                date_create($datetimeTz[0], timezone_open($datetimeTz[1])),
                date_create($datetimeTz[0]),
            ];
        }
    }

    /** @dataProvider dataValuesProvider */
    public function testInsertAndRetrieveDateTimeTz(
        DateTimeInterface $datetimeTz,
        DateTimeInterface $datetimeNoTz
    ): void {
        $platform = $this->connection->getDatabasePlatform();

        if (
            $platform instanceof SqlitePlatform ||
            $platform instanceof DB2Platform ||
            $platform instanceof OraclePlatform
        ) {
            self::markTestSkipped(sprintf("Platform %s doesn't support variable precision time", get_class($platform)));
        }

        $table = new Table('datetimetz_test_table');

        $vals  = [];
        $types = [];

        for ($i = 0; $i <= 6; $i++) {
            $table->addColumn('val' . $i, Types::DATETIMETZ_MUTABLE, ['scale' => $i]);
            $vals['val' . $i]  = Type::getType(Types::DATETIMETZ_MUTABLE)
                                     ->convertToDatabaseValue($datetimeTz, $platform);
            $types['val' . $i] = Types::STRING;
        }

        $this->dropAndCreateTable($table);

        $this->connection->insert('datetimetz_test_table', $vals, $types);

        for ($i = 0; $i <= 6; $i++) {
            $value = Type::getType(Types::DATETIMETZ_MUTABLE)->convertToPHPValue(
                $this->connection->fetchOne('SELECT val' . $i . ' FROM datetimetz_test_table'),
                $platform,
            );

            $expected =
                $platform instanceof PostgreSQLPlatform ||
                $platform instanceof SQLServerPlatform ||
                $platform instanceof OraclePlatform
            ? $datetimeTz : $datetimeNoTz;

            // PHP stores datetimes with microsecond precision so there will be a difference when column precision is
            // less than 6.
            self::assertEqualsWithDelta($expected, $value, 10 ** -$i);
        }
    }
}
