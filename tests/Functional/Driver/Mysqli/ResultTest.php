<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\Mysqli;

use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

/** @requires extension mysqli */
class ResultTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('mysqli')) {
            $this->connection->executeQuery('CREATE TABLE my_table (my_col_1 INT NOT NULL);');

            return;
        }

        self::markTestSkipped('This test requires the mysqli driver.');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS my_table;');
    }

    public function testRowCount(): void
    {
        // $result = $this->connection->getNativeConnection()->query('SELECT 1 FROM my_table;', \MYSQLI_USE_RESULT);
        $result = $this->connection->executeQuery('INSERT INTO my_table VALUES(7);');
        $this->connection->getNativeConnection()->get_connection_stats();

        self::assertSame(-1, $result->rowCount());
    }
}
