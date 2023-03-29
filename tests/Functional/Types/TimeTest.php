<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function get_class;
use function sprintf;

final class TimeTest extends FunctionalTestCase
{
    /** @return string[][] */
    public static function dataValuesProvider(): array
    {
        return [
            ['10:10:10.123456'],
            ['10:10:10.12345'],
            ['10:10:10.1234'],
            ['10:10:10.123'],
            ['10:10:10.12'],
            ['10:10:10.1'],
            ['10:10:10'],

            ['22:10:10.987654'],
            ['22:10:10.98765'],
            ['22:10:10.9876'],
            ['22:10:10.987'],
            ['22:10:10.98'],
            ['22:10:10.9'],

            ['23:59:59.99999'],
        ];
    }

    /** @dataProvider dataValuesProvider */
    public function testInsertAndRetrieveTime(string $expected): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (
            $platform instanceof SqlitePlatform ||
            $platform instanceof DB2Platform ||
            $platform instanceof OraclePlatform ||
            $platform instanceof SQLServerPlatform
        ) {
            self::markTestSkipped(sprintf(
                "Platform %s doesn't support variable precision TIME columns",
                get_class($platform),
            ));
        }

        $table = new Table('time_test_table');

        $vals  = [];
        $types = [];

        for ($i = 0; $i <= 6; $i++) {
            $table->addColumn('val' . $i, Types::TIME_MUTABLE, ['scale' => $i]);
            $vals['val' . $i]  = $expected;
            $types['val' . $i] = Types::STRING;
        }

        $this->dropAndCreateTable($table);

        $this->connection->insert('time_test_table', $vals, $types);

        for ($i = 0; $i <= 6; $i++) {
            $value = Type::getType(Types::TIME_MUTABLE)->convertToPHPValue(
                $this->connection->fetchOne('SELECT val' . $i . ' FROM time_test_table'),
                $platform,
            );

            $expected = Type::getType(Types::TIME_MUTABLE)->convertToPHPValue(
                $expected,
                $platform,
            );

            // PHP stores datetimes with microsecond precision so there will be a difference when column precision is
            // less than 6.
            self::assertEqualsWithDelta($expected, $value, 10 ** -$i);
        }
    }
}
