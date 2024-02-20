<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Result;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use mysqli;
use mysqli_driver;
use mysqli_sql_exception;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

use function sprintf;

use const MYSQLI_REPORT_ERROR;
use const MYSQLI_REPORT_OFF;
use const MYSQLI_REPORT_STRICT;

#[RequiresPhpExtension('mysqli')]
final class ResultTest extends FunctionalTestCase
{
    private const TABLE_NAME = 'result_test_table';

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

    public function testIntegerOnFailingRowCountFromAffectedRows(): void
    {
        $mysqliStmt = $this->nativeConnection
            ->prepare(sprintf('INSERT INTO %s VALUES (NULL);', self::TABLE_NAME));
        self::assertNotFalse($mysqliStmt);

        $mysqliDriver = new mysqli_driver();

        $mysqliReportMode = $mysqliDriver->report_mode;

        // Set MySQL's driver report mode to `MYSQLI_REPORT_OFF` in order to avoid exception on errors.
        $mysqliDriver->report_mode = MYSQLI_REPORT_OFF;

        try {
            $mysqliStmt->execute();

            self::assertSame(-1, $mysqliStmt->affected_rows);
            self::assertSame(-1, (new Result($mysqliStmt))->rowCount());
        } finally {
            // Restore default configuration.
            $mysqliDriver->report_mode = $mysqliReportMode;
        }
    }

    public function testExceptionOnFailingRowCountFromAffectedRows(): void
    {
        $mysqliStmt = $this->nativeConnection
            ->prepare(sprintf('INSERT INTO %s VALUES (NULL);', self::TABLE_NAME));
        self::assertNotFalse($mysqliStmt);

        $mysqliDriver = new mysqli_driver();

        $mysqliReportMode = $mysqliDriver->report_mode;

        // Set MySQL's driver report mode to `MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT` in order to throw exception on
        // errors.
        $mysqliDriver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;

        try {
            $mysqliStmt->execute();
        } catch (mysqli_sql_exception) {
            $this->expectException(mysqli_sql_exception::class);
            $this->expectExceptionMessage('Column \'my_col_1\' cannot be null');

            new Result($mysqliStmt);
        } finally {
            // Restore default configuration.
            $mysqliDriver->report_mode = $mysqliReportMode;
        }
    }
}
