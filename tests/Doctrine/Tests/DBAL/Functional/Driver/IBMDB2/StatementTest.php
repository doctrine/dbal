<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Driver\IBMDB2;

use Doctrine\DBAL\Driver\IBMDB2\Driver;
use Doctrine\DBAL\Driver\IBMDB2\Exception\StatementError;
use Doctrine\Tests\DbalFunctionalTestCase;

use function extension_loaded;

use const E_ALL;
use const E_NOTICE;
use const E_WARNING;

class StatementTest extends DbalFunctionalTestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('ibm_db2')) {
            $this->markTestSkipped('ibm_db2 is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('ibm_db2 only test.');
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
