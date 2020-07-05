<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Driver\IBMDB2;

use Doctrine\DBAL\Driver\IBMDB2\DB2Driver;
use Doctrine\Tests\DbalFunctionalTestCase;
use PHPUnit\Framework\Error\Notice;

/**
 * @requires extension ibm_db2
 */
class DB2StatementTest extends DbalFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof DB2Driver) {
            return;
        }

        $this->markTestSkipped('ibm_db2 only test.');
    }

    public function testExecutionErrorsAreNotSuppressed(): void
    {
        $stmt = $this->connection->prepare('SELECT * FROM SYSIBM.SYSDUMMY1 WHERE \'foo\' = ?');

        // unwrap the statement to prevent the wrapper from handling the PHPUnit-originated exception
        $wrappedStmt = $stmt->getWrappedStatement();

        $this->expectException(Notice::class);
        $wrappedStmt->execute([[]]);
    }
}
