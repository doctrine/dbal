<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\SQLAnywhere\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function extension_loaded;

class StatementTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('sqlanywhere')) {
            self::markTestSkipped('sqlanywhere is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        self::markTestSkipped('sqlanywhere only test.');
    }

    public function testNonPersistentStatement(): void
    {
        $params               = $this->connection->getParams();
        $params['persistent'] = false;

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        self::assertTrue($conn->isConnected(), 'No SQLAnywhere-Connection established');

        $prepStmt = $conn->prepare('SELECT 1');
        $prepStmt->execute();
    }

    public function testPersistentStatement(): void
    {
        $params               = $this->connection->getParams();
        $params['persistent'] = true;

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        self::assertTrue($conn->isConnected(), 'No SQLAnywhere-Connection established');

        $prepStmt = $conn->prepare('SELECT 1');
        $prepStmt->execute();
    }
}
