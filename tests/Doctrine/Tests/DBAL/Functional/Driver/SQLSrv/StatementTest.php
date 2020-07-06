<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLSrv;

use Doctrine\DBAL\Driver\SQLSrv\Driver;
use Doctrine\DBAL\Driver\SQLSrv\SQLSrvException;
use Doctrine\Tests\DbalFunctionalTestCase;

/**
 * @requires extension sqlsrv
 */
class StatementTest extends DbalFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        self::markTestSkipped('sqlsrv only test');
    }

    public function testFailureToPrepareResultsInException(): void
    {
        // use the driver connection directly to avoid having exception wrapped
        $stmt = $this->connection->getWrappedConnection()->prepare('');

        // it's impossible to prepare the statement without bound variables for SQL Server,
        // so the preparation happens before the first execution when variables are already in place
        $this->expectException(SQLSrvException::class);
        $stmt->execute();
    }
}
