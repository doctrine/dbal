<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\SQLAnywhere\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\Tests\DbalFunctionalTestCase;
use function extension_loaded;

class StatementTest extends DbalFunctionalTestCase
{
    protected function setUp()
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

    public function testNonPersistentStatement()
    {
        $params               = $this->connection->getParams();
        $params['persistent'] = false;

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        self::assertTrue($conn->isConnected(), 'No SQLAnywhere-Connection established');

        $prepStmt = $conn->prepare('SELECT 1');
        self::assertTrue($prepStmt->execute(), ' Statement non-persistent failed');
    }

    public function testPersistentStatement()
    {
        $params               = $this->connection->getParams();
        $params['persistent'] = true;

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        self::assertTrue($conn->isConnected(), 'No SQLAnywhere-Connection established');

        $prepStmt = $conn->prepare('SELECT 1');
        self::assertTrue($prepStmt->execute(), ' Statement persistent failed');
    }
}
