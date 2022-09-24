<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;
use Doctrine\DBAL\Tests\TestUtil;
use ReflectionObject;
use Throwable;

use function pcntl_fork;
use function sleep;

class DeadlockTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_mysql')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_mysql driver.');
    }

    public function testNestedTransactionsDeadlockExceptionHandling(): void
    {
        $firstConnection = new Connection($this->connection->getParams(), $this->driver);
        $firstConnection->setNestTransactionsWithSavepoints(true);
        $secondConnection = new Connection($this->connection->getParams(), $this->driver);
        $secondConnection->setNestTransactionsWithSavepoints(true);

        $this->assertNotSame($firstConnection, $secondConnection);

        $this->prepareDatabase($firstConnection);

        $executeQueries = static function (Connection $conn, int $thread): void {
            $conn->beginTransaction();
            $conn->beginTransaction();
            if ($thread === 1) {
                $conn->executeQuery('DELETE FROM `test1`');
                sleep(2);
                $conn->executeQuery('DELETE FROM `test2`');
            } else {
                $conn->executeQuery('DELETE FROM `test2`');
                $conn->executeQuery('DELETE FROM `test1`');
            }

            $conn->commit();
            $conn->commit();
        };

        $pid = pcntl_fork();
        if (! $pid) {
            //child process
            $executeQueries($secondConnection, 2);

            return;
        }

        try {
            $executeQueries($firstConnection, 1);
        } catch (Throwable $ex) {
            $this->assertInstanceOf(DeadlockException::class, $ex);
            $reflectionObject = new ReflectionObject($firstConnection);

            $transactionNestingLevel = $reflectionObject->getProperty('transactionNestingLevel');
            $transactionNestingLevel->setAccessible(true);

            $this->assertEquals(0, $transactionNestingLevel->getValue($firstConnection));

            return;
        }

        $this->fail('No deadlock exception in first thread');
    }

    private function prepareDatabase(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `test1`
                (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    PRIMARY KEY (`id`)
                ) ENGINE = InnoDB
                  DEFAULT CHARSET = `latin1`;',
        );
        $connection->executeStatement('INSERT INTO `test1` VALUES()');

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `test2`
                (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    PRIMARY KEY (`id`)
                ) ENGINE = InnoDB
                  DEFAULT CHARSET = `latin1`;',
        );
        $connection->executeQuery('INSERT INTO `test2` VALUES()');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
