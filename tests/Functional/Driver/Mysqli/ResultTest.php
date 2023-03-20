<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Exception\UnknownAffectedRowsError;
use Doctrine\DBAL\Driver\Mysqli\Result;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use mysqli;
use mysqli_driver;
use mysqli_stmt;

use function sprintf;

use const MYSQLI_REPORT_OFF;

/** @requires extension mysqli */
class ResultTest extends FunctionalTestCase
{
    private const TABLE_NAME = 'tesult_test_table';

    private mysqli $nativeConnection;

    protected function setUp(): void
    {
        if (! TestUtil::isDriverOneOf('mysqli')) {
            self::markTestSkipped('This test requires the mysqli driver.');
        }

        $nativeConnection = $this->connection->getNativeConnection();

        self::assertInstanceOf(mysqli::class, $nativeConnection);

        $this->nativeConnection = $nativeConnection;

        $table = new Table(self::TABLE_NAME);
        $table->addColumn('my_col_1', 'integer', ['notnull' => true]);

        $this->dropAndCreateTable($table);
    }

    protected function tearDown(): void
    {
        $this->dropTableIfExists(self::TABLE_NAME);
    }

    public function testSuccessfulRowCountFromAffectedRows(): void
    {
        $result = $this->connection->executeQuery(sprintf('INSERT INTO %s VALUES(7);', self::TABLE_NAME));

        self::assertSame(1, $result->rowCount());
    }

    public function testFailingRowCountFromAffectedRows(): void
    {
        // Ensure report mode configuration is "off" (like the default value for PHP < 8.1).
        $mysqliDriver              = new mysqli_driver();
        $mysqliDriver->report_mode = MYSQLI_REPORT_OFF;

        $mysqliStmt = $this->nativeConnection
            ->prepare(sprintf('INSERT INTO %s VALUES (NULL);', self::TABLE_NAME));

        self::assertInstanceOf(mysqli_stmt::class, $mysqliStmt);

        $mysqliStmt->execute();

        $this->expectException(UnknownAffectedRowsError::class);

        (new Result($mysqliStmt))->rowCount();
    }
}
