<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLAnywhere;

use Doctrine\DBAL\DriverManager;

class ConnectionTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
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
