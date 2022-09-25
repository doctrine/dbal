<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

use function pcntl_fork;
use function sleep;

class DeadlockTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_mysql', 'mysqli')) {
            $this->prepareDatabase();

            return;
        }

        self::markTestSkipped('This test requires the pdo_mysql driver.');
    }

    private function prepareDatabase(): void
    {
        $table = new Table('test1');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $table = new Table('test2');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $this->connection->executeStatement('INSERT INTO `test1` VALUES()');
        $this->connection->executeStatement('INSERT INTO `test2` VALUES()');
    }

    public function testNestedTransactionsDeadlockExceptionHandling(): void
    {
        $firstConnection = new Connection($this->connection->getParams(), $this->connection->getDriver());
        $firstConnection->setNestTransactionsWithSavepoints(true);
        $secondConnection = new Connection($this->connection->getParams(), $this->connection->getDriver());
        $secondConnection->setNestTransactionsWithSavepoints(true);

        try {
            $firstConnection->beginTransaction();
            $firstConnection->beginTransaction();
            $firstConnection->executeStatement('DELETE FROM `test1`; SELECT SLEEP(2)');

            $pid = pcntl_fork();
            if (! $pid) {
                $secondConnection->beginTransaction();
                $secondConnection->beginTransaction();
                $secondConnection->executeStatement('DELETE FROM `test2`');
                $secondConnection->executeStatement('DELETE FROM `test1`');
                $secondConnection->commit();
                $secondConnection->commit();

                return;
            }

            sleep(2); //sleep to make sure that the other process is in table lock state.
            $firstConnection->executeStatement('DELETE FROM `test2`;');
            $firstConnection->commit();
            $firstConnection->commit();
        } catch (DeadlockException $ex) {
            self::assertFalse($firstConnection->isTransactionActive());

            return;
        }

        $this->fail('Expected deadlock exception did not happen.');
    }
}
