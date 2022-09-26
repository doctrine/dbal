<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\MySQL;

use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

use function implode;
use function pcntl_fork;
use function sleep;
use function sprintf;

/** @require extension pcntl */
class DeadlockTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $supportedDrivers = ['pdo_mysql', 'mysqli', 'pdo_pgsql'];
        if (! TestUtil::isDriverOneOf(...$supportedDrivers)) {
            self::markTestSkipped(sprintf('This supports one of %s drivers', implode(', ', $supportedDrivers)));
        }

        $table = new Table('test1');
        $table->addColumn('id', 'integer');
        $this->dropAndCreateTable($table);

        $table = new Table('test2');
        $table->addColumn('id', 'integer');
        $this->dropAndCreateTable($table);

        $this->connection->executeStatement('INSERT INTO test1 VALUES(1)');
        $this->connection->executeStatement('INSERT INTO test2 VALUES(1)');
    }

    public function testNestedTransactionsDeadlockExceptionHandling(): void
    {
        $this->connection->setNestTransactionsWithSavepoints(true);

        try {
            $this->connection->beginTransaction();
            $this->connection->beginTransaction();
            $this->connection->executeStatement('DELETE FROM test1');

            $this->forceTableLockState();

            $this->connection->executeStatement('DELETE FROM test2');
            $this->connection->commit();
            $this->connection->commit();
        } catch (DeadlockException $ex) {
            self::assertFalse($this->connection->isTransactionActive());

            return;
        }

        $this->fail('Expected deadlock exception did not happen.');
    }

    private function forceTableLockState(): void
    {
        $pid = pcntl_fork();
        if ($pid) {
            $this->waitForTableLock();

            return;
        }

        $connection = TestUtil::getConnection();
        $connection->beginTransaction();
        $connection->executeStatement('DELETE FROM test2');
        $connection->executeStatement('DELETE FROM test1');
        $connection->commit();
    }

    private function waitForTableLock(): void
    {
        sleep(2);
    }
}
