<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
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
