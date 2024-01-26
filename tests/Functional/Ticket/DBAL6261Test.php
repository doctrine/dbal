<?php

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class DBAL6261Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            return;
        }

        self::markTestSkipped('Related to SQLite only');
    }

    public function testUnsignedIntegerDetection(): void
    {
        $testTable        = 'dbal6261tbl';
        $testTableService = $testTable . '_service';
        $testTableAgency  = $testTable . '_agency';

        $tableService = new Table($testTableService);
        $tableService->addColumn('id', Types::INTEGER);
        $tableService->addColumn('end_at', Types::DATETIME_MUTABLE);
        $tableService->addColumn('agency_id', Types::INTEGER);
        $tableService->setPrimaryKey(['id']);

        $tableAgency = new Table($testTableAgency);
        $tableAgency->addColumn('id', Types::INTEGER);
        $tableAgency->addColumn('utc_offset', Types::INTEGER);
        $tableAgency->setPrimaryKey(['id']);

        $this->dropAndCreateTable($tableService);
        $this->dropAndCreateTable($tableAgency);

        $this->connection->insert($testTableAgency, [
            'id' => 1,
            'utc_offset' => 120,
        ]);

        $match1     = [
            'id' => 1,
            'end_at' => '2023-01-05 16:59:59',
            'agency_id' => 1,
        ];
        $match2     = [
            'id' => 2,
            'end_at' => '2023-01-05 17:00:00',
            'agency_id' => 1,
        ];
        $wontMatch1 = [
            'id' => 3,
            'end_at' => '2023-01-05 17:00:01',
            'agency_id' => 1,
        ];

        $this->connection->insert($testTableService, $match1);
        $this->connection->insert($testTableService, $match2);
        $this->connection->insert($testTableService, $wontMatch1);

        $platfom = $this->connection->getDatabasePlatform();
        $result  = $this->connection->createQueryBuilder()
            ->select('s.*')
            ->from($testTableService, 's')
            ->leftJoin('s', $testTableAgency, 'a', 's.agency_id = a.id')
            ->where(
                's.end_at <= ' . $platfom->getDateAddMinutesExpression(
                    "DATETIME('2023-01-05 15:00:00')",
                    'a.utc_offset',
                ),
            )
            ->fetchAllAssociative();

        self::assertEquals(
            [
                $match1,
                $match2,
            ],
            $result,
        );
    }
}
