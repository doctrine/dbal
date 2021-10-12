<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\IBMDB2;

use Doctrine\DBAL\Driver\IBMDB2\Driver;
use Doctrine\DBAL\Driver\IBMDB2\Exception\StatementError;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use const E_ALL;
use const E_NOTICE;
use const E_WARNING;

/**
 * @require extension ibm_db2
 */
class StatementTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        self::markTestSkipped('ibm_db2 only test.');
    }

    public function testExecutionErrorsAreNotSuppressed(): void
    {
        $driverConnection = $this->connection->getWrappedConnection();

        $stmt = $driverConnection->prepare('SELECT * FROM SYSIBM.SYSDUMMY1 WHERE \'foo\' = ?');

        // prevent the PHPUnit error handler from handling the errors that db2_execute() may trigger
        $this->iniSet('error_reporting', (string) (E_ALL & ~E_WARNING & ~E_NOTICE));

        $this->expectException(StatementError::class);
        $stmt->execute([[]]);
    }
}
