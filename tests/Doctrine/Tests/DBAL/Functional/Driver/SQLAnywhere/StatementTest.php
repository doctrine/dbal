<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\SQLAnywhere\Driver;
use Doctrine\DBAL\DriverManager;

class StatementTest extends \Doctrine\Tests\DbalFunctionalTestCase
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

    public function testNonPersistentStatement()
    {
        $params = $this->_conn->getParams();
        $params['persistent'] = false;

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        $this->assertTrue($conn->isConnected(),'No SQLAnywhere-Connection established');

        $prepStmt = $conn->prepare('SELECT 1');
        $this->assertTrue($prepStmt->execute(),' Statement non-persistent failed');
    }

    public function testPersistentStatement()
    {
        $params = $this->_conn->getParams();
        $params['persistent'] = true;

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        $this->assertTrue($conn->isConnected(),'No SQLAnywhere-Connection established');

        $prepStmt = $conn->prepare('SELECT 1');
        $this->assertTrue($prepStmt->execute(),' Statement persistent failed');
    }

}
