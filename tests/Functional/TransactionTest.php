<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function func_get_args;
use function restore_error_handler;
use function set_error_handler;
use function sleep;

use const E_WARNING;
use const PHP_VERSION_ID;

class TransactionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
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

        $this->expectException(ConnectionLost::class);

        // prevent the PHPUnit error handler from handling the "MySQL server has gone away" warning
        /** @var callable|null $previous */
        $previous = null;
        $previous = set_error_handler(static function (int $errno) use (&$previous): bool {
            if (($errno & ~E_WARNING) === 0) {
                return true;
            }

            return $previous !== null && $previous(...func_get_args());
        });
        try {
            $scenario($this->connection);
        } finally {
            restore_error_handler();
        }
    }
}
