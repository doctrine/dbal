<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function sprintf;

/**
 * @see https://github.com/doctrine/dbal/issues/3423
 */
class GH3423Test extends FunctionalTestCase
{
    /** @var bool */
    private static $tableCreated = false;

    /** @var string */
    private $constraintName = '';

    protected function setUp(): void
    {
        parent::setUp();

        $platformName = $this->connection->getDatabasePlatform()->getName();

        if ($platformName === 'oracle') {
            $constraintName = 'GH3423_UNIQUE';
        } elseif ($platformName === 'postgresql') {
            $constraintName = 'gh3423_unique';
        } else {
            self::markTestSkipped('Only databases supporting deferrable constraints are eligible for this test.');
        }

        $this->constraintName = $constraintName;

        if (self::$tableCreated) {
            return;
        }

        $this->connection->executeStatement(
            <<<SQL
            CREATE TABLE gh3423 (
                unique_field INTEGER NOT NULL
                    CONSTRAINT $constraintName
                        UNIQUE
                        DEFERRABLE INITIALLY DEFERRED
            )
            SQL
        );
        $this->connection->executeStatement('INSERT INTO gh3423 VALUES (1)');

        self::$tableCreated = true;
    }

    /**
     * @group GH3423
     */
    public function testTransactionalWithDeferredConstraint(): void
    {
        $this->connection->transactional(function (Connection $connection): void {
            $connection->executeStatement(sprintf('SET CONSTRAINTS "%s" DEFERRED', $this->constraintName));
            $connection->executeStatement('INSERT INTO gh3423 VALUES (1)');

            $this->expectException(Exception::class);
            $this->expectExceptionMessage(sprintf('violates unique constraint "%s"', $this->constraintName));
        });
    }

    /**
     * @group GH3423
     */
    public function testTransactionalWithDeferredConstraintAndTransactionNesting(): void
    {
        $this->connection->setNestTransactionsWithSavepoints(true);

        $this->connection->transactional(function (Connection $connection): void {
            $connection->executeStatement(sprintf('SET CONSTRAINTS "%s" DEFERRED', $this->constraintName));
            $connection->beginTransaction();
            $connection->executeStatement('INSERT INTO gh3423 VALUES (1)');
            $connection->commit();

            $this->expectException(Exception::class);
            $this->expectExceptionMessage(sprintf('violates unique constraint "%s"', $this->constraintName));
        });
    }

    /**
     * @group GH3423
     */
    public function testCommitWithDeferredConstraint(): void
    {
        $this->connection->beginTransaction();
        $this->connection->executeStatement(sprintf('SET CONSTRAINTS "%s" DEFERRED', $this->constraintName));
        $this->connection->executeStatement('INSERT INTO gh3423 VALUES (1)');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(sprintf('violates unique constraint "%s"', $this->constraintName));

        $this->connection->commit();
    }

    /**
     * @group GH3423
     */
    public function testCommitWithDeferredConstraintAndTransactionNesting(): void
    {
        $this->connection->setNestTransactionsWithSavepoints(true);

        $this->connection->beginTransaction();
        $this->connection->executeStatement(sprintf('SET CONSTRAINTS "%s" DEFERRED', $this->constraintName));
        $this->connection->beginTransaction();
        $this->connection->executeStatement('INSERT INTO gh3423 VALUES (1)');
        $this->connection->commit();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(sprintf('violates unique constraint "%s"', $this->constraintName));

        $this->connection->commit();
    }
}
