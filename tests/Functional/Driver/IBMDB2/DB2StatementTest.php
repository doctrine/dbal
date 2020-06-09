<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\IBMDB2;

use Doctrine\DBAL\Driver\IBMDB2\DB2Driver;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function assert;

use function extension_loaded;

class DB2StatementTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('ibm_db2')) {
            self::markTestSkipped('ibm_db2 is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof DB2Driver) {
            return;
        }

        self::markTestSkipped('ibm_db2 only test.');
    }

    public function testExecutionErrorsAreNotSuppressed(): void
    {
        $stmt = $this->connection->prepare('SELECT * FROM SYSIBM.SYSDUMMY1 WHERE \'foo\' = ?');
        assert($stmt instanceof Statement);

        // unwrap the statement to prevent the wrapper from handling the PHPUnit-originated exception
        $wrappedStmt = $stmt->getWrappedStatement();

        $this->expectNotice();
        $wrappedStmt->execute([[]]);
    }
}
