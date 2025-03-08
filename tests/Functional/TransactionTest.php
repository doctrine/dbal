<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;

use function func_get_args;
use function restore_error_handler;
use function set_error_handler;

use const E_WARNING;

class TransactionTest extends FunctionalTestCase
{
    public function testBeginTransactionFailure(): void
    {
        $this->expectConnectionLoss(static function (Connection $connection): void {
            $connection->beginTransaction();
        });
    }

    public function testCommitFailure(): void
    {
        $this->connection->beginTransaction();

        $this->expectConnectionLoss(static function (Connection $connection): void {
            $connection->commit();
        });
    }

    public function testRollbackFailure(): void
    {
        $this->connection->beginTransaction();

        $this->expectConnectionLoss(static function (Connection $connection): void {
            $connection->rollBack();
        });
    }

    private function expectConnectionLoss(callable $scenario): void
    {
        $this->killCurrentSession();
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

    private function killCurrentSession(): void
    {
        $this->markConnectionNotReusable();

        $databasePlatform = $this->connection->getDatabasePlatform();

        [$currentProcessQuery, $killProcessStatement] = match (true) {
            $databasePlatform instanceof AbstractMySqlPlatform => [
                'SELECT CONNECTION_ID()',
                'KILL ?',
            ],
            $databasePlatform instanceof PostgreSQLPlatform => [
                'SELECT pg_backend_pid()',
                'SELECT pg_terminate_backend(?)',
            ],
            default => self::markTestSkipped('Unsupported test platform.'),
        };

        $privilegedConnection = TestUtil::getPrivilegedConnection();
        $privilegedConnection->executeStatement(
            $killProcessStatement,
            [$this->connection->executeQuery($currentProcessQuery)->fetchOne()],
        );
    }

    public function testNestedTransactionWalkthrough(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestIncomplete('Broken when savepoints are not supported.');
        }

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
