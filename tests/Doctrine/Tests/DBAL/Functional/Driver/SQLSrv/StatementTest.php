<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLSrv;

use Doctrine\DBAL\Driver\SQLSrv\Driver;
use Doctrine\DBAL\Driver\SQLSrv\SQLSrvException;
use Doctrine\DBAL\Driver\SQLSrv\SQLSrvStatement;
use Doctrine\Tests\DbalFunctionalTestCase;

class StatementTest extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        if (!extension_loaded('sqlsrv')) {
            self::markTestSkipped('sqlsrv is not installed.');
        }

        parent::setUp();

        if (!$this->_conn->getDriver() instanceof Driver) {
            self::markTestSkipped('sqlsrv only test');
        }
    }

    public function testFailureToPrepareResultsInException()
    {
        // use the driver connection directly to avoid having exception wrapped
        $stmt = $this->_conn->getWrappedConnection()->prepare(null);

        // it's impossible to prepare the statement without bound variables for SQL Server,
        // so the preparation happens before the first execution when variables are already in place
        $this->expectException(SQLSrvException::class);
        $stmt->execute();
    }

    public function testObjectsWillBeCastedToString()
    {
        // use the driver connection directly to avoid having exception wrapped
        /** @var SQLSrvStatement $stmt */
        $stmt = $this->_conn->getWrappedConnection()->prepare("SELECT ?");

        // create and bind an object that implements magic method __toString()
        $object = new StringCastableObject('test string');
        $stmt->bindParam(1, $object, \PDO::PARAM_STR);

        // when executing the query, no Exception must be thrown
        $stmt->execute();
    }
}
