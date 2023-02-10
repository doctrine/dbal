<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use Doctrine\DBAL\Driver\Mysqli\Exception\StatementError;
use Doctrine\DBAL\Driver\Mysqli\Result;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use mysqli_driver;

/** @requires extension mysqli */
class ResultTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('mysqli')) {
            $mysqliDriver = new mysqli_driver();
            $mysqliDriver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;

            $this->connection->executeQuery('CREATE TABLE my_table (my_col_1 INT NOT NULL);');

            return;
        }

        self::markTestSkipped('This test requires the mysqli driver.');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS my_table;');
    }

    public function testSuccessfulRowCountFromAffectedRows(): void
    {
        // var_dump($this->connection->getNativeConnection()->error);
        // var_dump($this->connection->getNativeConnection()->error_list);
        // var_dump($this->connection->getNativeConnection()->info);
        // self::assertSame(0, $this->connection->getNativeConnection()->affected_rows);

        self::assertSame(0, $this->connection->getNativeConnection()->affected_rows);

        $result = $this->connection->executeQuery('INSERT INTO my_table VALUES(7);');

        self::assertSame(1, $this->connection->getNativeConnection()->affected_rows);
        self::assertSame(1, $result->rowCount());
    }

    public function testFailingRowCountFromAffectedRows(): void
    {
        $mysqliStmt = $this->connection->getNativeConnection()
            ->prepare('INSERT INTO my_table VALUES(7);');

        self::assertSame(-1, $mysqliStmt->affected_rows);

        $this->expectException(ConnectionError::class);

        (new Result($mysqliStmt))->rowCount();
    }
}
