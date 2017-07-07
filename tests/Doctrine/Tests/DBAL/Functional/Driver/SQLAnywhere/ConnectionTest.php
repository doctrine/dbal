<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\SQLAnywhere\Driver;
use Doctrine\DBAL\DriverManager;

class ConnectionTest extends \Doctrine\Tests\DbalFunctionalTestCase
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

    public function testNonPersistentConnection()
    {
        $params = $this->_conn->getParams();
        $params['persistent'] = false;

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        $this->assertTrue($conn->isConnected(), 'No SQLAnywhere-nonpersistent connection established');
    }

    public function testPersistentConnection()
    {
        $params = $this->_conn->getParams();
        $params['persistent'] = true;

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        $this->assertTrue($conn->isConnected(), 'No SQLAnywhere-persistent connection established');
    }
}
