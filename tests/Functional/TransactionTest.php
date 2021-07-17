<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function sleep;

use const E_ALL;
use const E_WARNING;
use const PHP_VERSION_ID;

class TransactionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDatabasePlatform() instanceof MySQLPlatform) {
            return;
        }

        self::markTestSkipped('Restricted to MySQL.');
    }

    public function testCommitFailure(): void
    {
        $this->expectConnectionLoss(static function (Connection $connection): void {
            $connection->commit();
        });
    }

    public function testRollbackFailure(): void
    {
        $this->expectConnectionLoss(static function (Connection $connection): void {
            $connection->rollBack();
        });
    }

    private function expectConnectionLoss(callable $scenario): void
    {
        if (PHP_VERSION_ID < 70413 && $this->connection->getDriver() instanceof PDO\MySQL\Driver) {
            self::markTestSkipped('See https://bugs.php.net/bug.php?id=66528.');
        }

        $this->connection->executeStatement('SET SESSION wait_timeout=1');
        $this->connection->beginTransaction();

        // during the sleep MySQL will close the connection
        sleep(2);

        // prevent the PHPUnit error handler from handling the "MySQL server has gone away" warning
        $this->iniSet('error_reporting', (string) (E_ALL & ~E_WARNING));

        $this->expectException(ConnectionLost::class);
        $scenario($this->connection);
    }
}
