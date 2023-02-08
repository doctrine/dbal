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
        // Unbuffered query is not producing -1 as "SELECT" statements use `Statement::$num_rows` instead of `Statement::$affected_rows`.
        // $result = $this->connection->getNativeConnection()->query('SELECT 1 FROM my_table;', \MYSQLI_USE_RESULT);

        $result = $this->connection->executeQuery('INSERT INTO my_table VALUES(7);');

        // Calling `mysqli::get_connection_stats()` after an "INSERT" statement is not producing -1. See https://bugs.php.net/bug.php?id=67348.
        // $this->connection->getNativeConnection()->get_connection_stats();

        self::assertSame(-1, $result->rowCount());
    }
}
