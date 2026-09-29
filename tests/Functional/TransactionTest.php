<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PDOException;

use function sleep;

class TransactionTest extends FunctionalTestCase
{
    public function testCommitFalse(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->markTestSkipped('Restricted to MySQL.');
        }

        $this->connection->executeStatement('SET SESSION wait_timeout=1');

        self::assertTrue($this->connection->beginTransaction());

        sleep(2); // during the sleep mysql will close the connection

        try {
            self::assertFalse(@$this->connection->commit()); // we will ignore `MySQL server has gone away` warnings
        } catch (PDOException $e) {
            self::assertInstanceOf(DriverException::class, $e);

            /* For PDO, we are using ERRMODE EXCEPTION, so this catch should be
             * necessary as the equivalent of the error control operator above.
             * This seems to be the case only since PHP 8 */
        } finally {
            $this->connection->close();
        }
    }

    /**
     * MySQL implicitly commits the active transaction (and thus discards all savepoints) when a DDL
     * statement is executed. The same happens on a deadlock. In both cases the savepoint the nested
     * rollback targets no longer exists, and the rollback fails. The nesting level still has to be
     * decremented, otherwise the connection is stuck at a level it can never leave.
     *
     * @see https://github.com/doctrine/dbal/issues/4279
     * @see https://github.com/doctrine/dbal/issues/6651
     */
    public function testRollBackAfterImplicitCommitDecrementsNestingLevel(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('Restricted to MySQL.');
        }

        $this->markConnectionNotReusable();

        $table = new Table('transaction_nesting');
        $table->addColumn('test_int', Types::INTEGER);
        $table->setPrimaryKey(['test_int']);

        $this->dropAndCreateTable($table);
        $this->connection->setNestTransactionsWithSavepoints(true);

        $this->connection->beginTransaction();
        $this->connection->beginTransaction();
        $this->connection->insert('transaction_nesting', ['test_int' => 1]);

        // Implicitly commits the transaction and discards SAVEPOINT DOCTRINE_2.
        $this->connection->executeStatement('CREATE TABLE transaction_nesting_ddl (id INT NOT NULL)');

        self::assertSame(2, $this->connection->getTransactionNestingLevel());

        try {
            $this->connection->rollBack();
            self::fail('Rolling back to a discarded savepoint is expected to fail.');
        } catch (Exception $e) {
            self::assertSame(
                1,
                $this->connection->getTransactionNestingLevel(),
                'The nesting level must be decremented even though the savepoint is gone.',
            );
        }

        // Without decrementing the level above, the connection would be stuck at level 2 and every
        // further rollBack() would target the same discarded savepoint again.
        $this->connection->close();
        $this->connection->executeStatement('DROP TABLE transaction_nesting_ddl');
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
