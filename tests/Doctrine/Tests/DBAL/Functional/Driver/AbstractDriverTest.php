<?php

namespace Doctrine\Tests\DBAL\Functional\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\Tests\DbalFunctionalTestCase;

abstract class AbstractDriverTest extends DbalFunctionalTestCase
{
    /**
     * The driver instance under test.
     *
     * @var \Doctrine\DBAL\Driver
     */
    private $driver;

    protected function setUp()
    {
        parent::setUp();

        $this->driver = $this->createDriver();
    }

    /**
     * @group DBAL-1215
     */
    public function testConnectsWithoutDatabaseNameParameter()
    {
        $params = $this->_conn->getParams();
        unset($params['dbname']);

        $user = isset($params['user']) ? $params['user'] : null;
        $password = isset($params['password']) ? $params['password'] : null;

        $connection = $this->driver->connect($params, $user, $password);

        $this->assertInstanceOf('Doctrine\DBAL\Driver\Connection', $connection);
    }

    /**
     * @group DBAL-1215
     */
    public function testReturnsDatabaseNameWithoutDatabaseNameParameter()
    {
        $params = $this->_conn->getParams();
        unset($params['dbname']);

        $connection = new Connection(
            $params,
            $this->_conn->getDriver(),
            $this->_conn->getConfiguration(),
            $this->_conn->getEventManager()
        );

        $this->assertSame(
            $this->getDatabaseNameForConnectionWithoutDatabaseNameParameter(),
            $this->driver->getDatabase($connection)
        );
    }

    /**
     * @return \Doctrine\DBAL\Driver
     */
    abstract protected function createDriver();

    /**
     * @return string|null
     */
    protected function getDatabaseNameForConnectionWithoutDatabaseNameParameter()
    {
        return null;
    }
}
