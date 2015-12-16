<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLite3;

use Doctrine\DBAL\Driver\SQLite3\Driver;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;

class DriverTest extends AbstractDriverTest
{
    protected function setUp()
    {
        if (!extension_loaded('sqlite3')) {
            $this->markTestSkipped('sqlite3 is not loaded.');
        }

        parent::setUp();

        if (!$this->_conn->getDriver() instanceof Driver) {
            $this->markTestSkipped('sqlite3 only test.');
        }
    }

    protected function createDriver()
    {
        return new Driver();
    }

    public function testConnectsWithoutDatabaseNameParameter()
    {
        $this->markTestSkipped("dbname not supported on sqlite");
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter()
    {
        $this->markTestSkipped("dbname not supported on sqlite");
    }

    public function testConnectsWithoutPathParameter()
    {
        $params = $this->_conn->getParams();
        unset($params['path']);

        $connection = $this->driver->connect($params);

        $this->assertInstanceOf('Doctrine\DBAL\Driver\SQLite3\SQLite3Connection', $connection);
    }

    public function testConnectsWithBlankPathParameter()
    {
        $params = $this->_conn->getParams();
        $params['path'] = '';

        $connection = $this->driver->connect($params);

        $this->assertInstanceOf('Doctrine\DBAL\Driver\SQLite3\SQLite3Connection', $connection);
    }

    /**
     * @expectedException \Doctrine\DBAL\Exception\SyntaxErrorException
     */
    public function testSyntaxErrorException()
    {
        $this->_conn->query("KWYJIBO!");
    }

    public function testQueryExceptionIsChainedProperly()
    {
        $exception = null;
        try {
            $this->_conn->query("KWYJIBO!");
        } catch (SyntaxErrorException $e) {
            $exception = $e;
        }

        $this->assertInstanceOf('Doctrine\DBAL\Exception\SyntaxErrorException', $exception);
        $this->assertInstanceOf('Doctrine\DBAL\Driver\SQLite3\SQLite3Exception', $exception->getPrevious());
        $this->assertInstanceOf('Exception', $exception->getPrevious()->getPrevious());
    }
}
