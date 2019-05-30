<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\SQLAnywhere\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\Tests\DbalFunctionalTestCase;
use function extension_loaded;

class ConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp() : void
    {
        if (! extension_loaded('sqlanywhere')) {
            $this->markTestSkipped('sqlanywhere is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('sqlanywhere only test.');
    }

    public function testNonPersistentConnection() : void
    {
        $params               = $this->connection->getParams();
        $params['persistent'] = false;

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        self::assertTrue($conn->isConnected(), 'No SQLAnywhere-nonpersistent connection established');
    }

    public function testPersistentConnection() : void
    {
        $params               = $this->connection->getParams();
        $params['persistent'] = true;

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        self::assertTrue($conn->isConnected(), 'No SQLAnywhere-persistent connection established');
    }
}
