<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

use function func_get_args;
use function restore_error_handler;
use function set_error_handler;
use function sleep;

use const E_WARNING;

class TransactionTest extends FunctionalTestCase
{
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
        if (! $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('Restricted to MySQL.');
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

    public function testNestedTransactionWalkthrough(): void
    {
        $table = new Table('storage');
        $table->addColumn('test_int', Types::INTEGER);
        $table->setPrimaryKey(['test_int']);

        $this->dropAndCreateTable($table);

        $query = 'SELECT count(test_int) FROM storage';

        self::assertSame('0', (string) $this->connection->fetchOne($query));

        $result = $this->connection->transactional(
            static fn (Connection $connection) => $connection->transactional(
                static function (Connection $connection) use ($query) {
                    $connection->insert('storage', ['test_int' => 1]);

                    return $connection->fetchOne($query);
                },
            ),
        );

        self::assertSame('1', (string) $result);
        self::assertSame('1', (string) $this->connection->fetchOne($query));
    }
}
