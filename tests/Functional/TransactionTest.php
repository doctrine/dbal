<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use SQLite3;

use function array_map;
use function file_exists;
use function func_get_args;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function strval;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const E_WARNING;

class TransactionTest extends FunctionalTestCase
{
    private ?Connection $reader = null;

    private ?Connection $writer = null;

    private ?string $refusedCommitPath = null;

    protected function tearDown(): void
    {
        $this->reader?->close();
        $this->reader = null;
        $this->writer?->close();
        $this->writer = null;

        if ($this->refusedCommitPath !== null && file_exists($this->refusedCommitPath)) {
            unlink($this->refusedCommitPath);
        }

        $this->refusedCommitPath = null;

        parent::tearDown();
    }

    public function testRefusedCommit(): void
    {
        $writer = $this->createWriterLockedOutByReader();
        $writer->beginTransaction();
        $writer->insert('refused_commit', ['id' => 1]);

        try {
            $writer->commit();
            self::fail('The commit was expected to be refused.');
        } catch (LockWaitTimeoutException) {
        }

        self::assertFalse($writer->isTransactionActive());

        $this->reader?->rollBack();

        $writer->beginTransaction();
        $writer->insert('refused_commit', ['id' => 2]);
        $writer->commit();

        self::assertSame(['2'], array_map(
            strval(...),
            $writer->fetchFirstColumn('SELECT id FROM refused_commit'),
        ));
    }

    public function testTransactionalWithRefusedCommit(): void
    {
        $writer = $this->createWriterLockedOutByReader();

        $this->expectException(LockWaitTimeoutException::class);
        $writer->transactional(static function (Connection $connection): void {
            $connection->insert('refused_commit', ['id' => 1]);
        });
    }

    public function testTransactionalWithRefusedSavepointReleaseRollsBack(): void
    {
        $native = $this->connection->getNativeConnection();

        if (! $native instanceof SQLite3) {
            self::markTestSkipped('Restricted to the sqlite3 driver, which can refuse an SQL statement.');
        }

        $this->dropAndCreateTable(
            Table::editor()
                ->setUnquotedName('refused_release')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('id')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                )
                ->create(),
        );

        $this->connection->beginTransaction();
        $native->setAuthorizer(static function (int $action, ?string $operation): int {
            return $action === SQLite3::SAVEPOINT && $operation === 'RELEASE' ? SQLite3::DENY : SQLite3::OK;
        });

        try {
            $this->connection->transactional(static function (Connection $connection): void {
                $connection->insert('refused_release', ['id' => 1]);
            });
            self::fail('The savepoint release was expected to be refused.');
        } catch (DriverException) {
        } finally {
            $native->setAuthorizer(null);
        }

        if ($this->connection->isTransactionActive()) {
            $this->connection->commit();
        }

        self::assertSame([], $this->connection->fetchFirstColumn('SELECT id FROM refused_release'));
    }

    /**
     * Returns a connection to an SQLite database on which another connection holds a read transaction,
     * so SQLite refuses the returned connection's commit of a write with "database is locked".
     */
    private function createWriterLockedOutByReader(): Connection
    {
        if (! $this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped('Restricted to SQLite.');
        }

        $params           = $this->connection->getParams();
        $params['memory'] = false;
        $path             = tempnam(sys_get_temp_dir(), 'doctrine_refused_commit_');
        self::assertIsString($path);
        $this->refusedCommitPath = $path;
        $params['path']          = $path;

        $writer = $this->writer = DriverManager::getConnection($params, $this->connection->getConfiguration());
        $writer->executeStatement('PRAGMA busy_timeout = 0');
        $writer->executeStatement('DROP TABLE IF EXISTS refused_commit');
        $writer->executeStatement('CREATE TABLE refused_commit (id INTEGER PRIMARY KEY)');

        $this->reader = DriverManager::getConnection($params, $this->connection->getConfiguration());
        $this->reader->beginTransaction();
        $this->reader->fetchOne('SELECT COUNT(*) FROM refused_commit');

        return $writer;
    }

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

    public function testTransactionalFailureDuringCallback(): void
    {
        $this->connection->transactional(
            function (): void {
                $this->expectConnectionLoss(static function (Connection $connection): void {
                    $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
                });
            },
        );
    }

    public function testTransactionalFailureDuringCommit(): void
    {
        $this->connection->transactional(
            function (): void {
                $this->expectConnectionLoss(static function (Connection $connection): void {
                });
            },
        );
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
            $databasePlatform instanceof AbstractMySQLPlatform => [
                'SELECT CONNECTION_ID()',
                'KILL %d',
            ],
            $databasePlatform instanceof PostgreSQLPlatform => [
                'SELECT pg_backend_pid()',
                'SELECT pg_terminate_backend(%d)',
            ],
            default => self::markTestSkipped('Unsupported test platform.'),
        };

        $killProcessStatement = sprintf(
            $killProcessStatement,
            (int) $this->connection->executeQuery($currentProcessQuery)->fetchOne(),
        );

        $privilegedConnection = TestUtil::getPrivilegedConnection();
        $privilegedConnection->executeStatement($killProcessStatement);
    }

    public function testNestedTransactionWalkthrough(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestIncomplete('Broken when savepoints are not supported.');
        }

        $table = Table::editor()
            ->setUnquotedName('storage')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('test_int')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('test_int')
                    ->create(),
            )
            ->create();

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
