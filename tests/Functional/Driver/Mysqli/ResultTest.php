<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use Doctrine\DBAL\Driver\Mysqli\Exception\StatementError;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

/** @requires extension mysqli */
class ResultTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('mysqli')) {
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
        var_dump($this->connection->getNativeConnection()->error);
        var_dump($this->connection->getNativeConnection()->error_list);
        var_dump($this->connection->getNativeConnection()->info);
        self::assertSame(0, $this->connection->getNativeConnection()->affected_rows);

        $this->connection->executeQuery('CREATE TABLE my_table (my_col_1 INT NOT NULL);');

        // self::assertSame(-1, $this->connection->getNativeConnection()->affected_rows);

        $result = $this->connection->executeQuery('INSERT INTO my_table VALUES(7);');

        self::assertSame(1, $this->connection->getNativeConnection()->affected_rows);
        self::assertSame(1, $result->rowCount());
    }

    public function testFailingRowCountFromAffectedRows(): void
    {
        $result = $this->connection->executeQuery('CREATE TABLE my_table (my_col_1 INT NOT NULL);');

        self::assertSame(-1, $this->connection->getNativeConnection()->affected_rows);

        $this->expectException(ConnectionError::class);

        $result->rowCount();
    }
}
