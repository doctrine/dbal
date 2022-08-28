<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\SQLSrv;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

/** @requires extension sqlsrv */
class StatementTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('sqlsrv')) {
            return;
        }

        self::markTestSkipped('This test requires the sqlsrv driver.');
    }

    public function testFailureToPrepareResultsInException(): void
    {
        // use the driver connection directly to avoid having exception wrapped
        $stmt = $this->connection->getWrappedConnection()->prepare('');

        // it's impossible to prepare the statement without bound variables for SQL Server,
        // so the preparation happens before the first execution when variables are already in place
        $this->expectException(Exception::class);
        $stmt->execute();
    }
}
