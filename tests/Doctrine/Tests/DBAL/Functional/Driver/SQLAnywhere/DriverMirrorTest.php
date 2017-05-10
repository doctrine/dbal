<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLAnywhere;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Driver\SQLAnywhere\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;

class DriverMirrorTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        if (! extension_loaded('sqlanywhere')) {
            $this->markTestSkipped('sqlanywhere is not installed.');
        }

        parent::setUp();

        if (! $this->_conn->getDriver() instanceof Driver) {
            $this->markTestSkipped('sqlanywhere only test.');
        }
    }

    public function testConnectDatabaseNameWithoutHostParameter()
    {
        $params = $this->_conn->getParams();
        unset($params['host']);
        //servername must be set if no host specified
        $params['server'] = $params['dbname'];

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        $this->assertTrue($conn->isConnected(), 'No SQLAnywhere connection established');
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver()
    {
        return new Driver();
    }
}
