<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;

class DateExpressionTest extends FunctionalTestCase
{
    #[DataProvider('differenceProvider')]
    public function testDifference(string $date1, string $date2, int $expected): void
    {
        $table = new Table('date_expr_test');
        $table->addColumn('date1', Types::DATETIME_MUTABLE);
        $table->addColumn('date2', Types::DATETIME_MUTABLE);
        $this->dropAndCreateTable($table);
        $this->connection->insert('date_expr_test', [
            'date1' => $date1,
            'date2' => $date2,
        ]);

        $platform = $this->connection->getDatabasePlatform();

        $sql  = sprintf('SELECT %s FROM date_expr_test', $platform->getDateDiffExpression('date1', 'date2'));
        $diff = $this->connection->fetchOne($sql);

        self::assertEquals($expected, $diff);
    }

    /** @return array<string, array{string, string, int}> */
    public static function differenceProvider(): iterable
    {
        return [
            'same day' => ['2018-04-14 23:59:59', '2018-04-14 00:00:00', 0],
            'midnight' => ['2018-04-14 00:00:00', '2018-04-13 23:59:59', 1],
        ];
    }
}
