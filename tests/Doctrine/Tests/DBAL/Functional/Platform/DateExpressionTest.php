<?php

namespace Doctrine\Tests\DBAL\Functional\Platform;

use DateTimeImmutable;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use function sprintf;

class DateExpressionTest extends DbalFunctionalTestCase
{
    /**
     * @dataProvider differenceProvider
     */
    public function testDifference(string $date1, string $date2, int $expected) : void
    {
        $table = new Table('date_expr_test');
        $table->addColumn('date1', 'datetime');
        $table->addColumn('date2', 'datetime');
        $this->connection->getSchemaManager()->dropAndCreateTable($table);
        $this->connection->insert('date_expr_test', [
            'date1' => $date1,
            'date2' => $date2,
        ]);

        $platform = $this->connection->getDatabasePlatform();

        $sql  = sprintf('SELECT %s FROM date_expr_test', $platform->getDateDiffExpression('date1', 'date2'));
        $diff = $this->connection->query($sql)->fetchColumn();

        self::assertEquals($expected, $diff);
    }

    /**
     * @return string[][]|int[][]
     */
    public static function differenceProvider() : iterable
    {
        $date1    = new DateTimeImmutable();
        $date2    = new DateTimeImmutable('2018-04-10 10:10:10');
        $expected = $date1->modify('midnight')->diff(
            $date2->modify('midnight')
        )->days;

        return [
            'dynamic'  => [
                $date1->format('Y-m-d H:i:s'),
                $date2->format('Y-m-d H:i:s'),
                $expected,
            ],
            'same day' => ['2018-04-14 23:59:59', '2018-04-14 00:00:00', 0],
            'midnight' => ['2018-04-14 00:00:00', '2018-04-13 23:59:59', 1],
        ];
    }
}
