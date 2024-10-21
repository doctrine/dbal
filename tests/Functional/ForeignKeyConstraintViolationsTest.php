<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;
use Doctrine\DBAL\Driver\PDO\PDOException;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver as PDOPgSQLDriver;
use Doctrine\DBAL\Driver\PgSQL\Driver as PgSQLDriver;
use Doctrine\DBAL\Driver\PgSQL\Exception as PgSQLException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use PHPUnit\Framework\Assert;
use Throwable;

use function sprintf;

final class ForeignKeyConstraintViolationsTest extends FunctionalTestCase
{
    private string $constraintName = '';

    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof OraclePlatform) {
            $constraintName = 'FK1';
        } else {
            $constraintName = 'fk1';
        }

        $this->constraintName = $constraintName;

        $schemaManager = $this->connection->createSchemaManager();

        $table = new Table('test_t1');
        $table->addColumn('ref_id', 'integer', ['notnull' => true]);
        $schemaManager->createTable($table);

        $table2 = new Table('test_t2');
        $table2->addColumn('id', 'integer', ['notnull' => true]);
        $table2->setPrimaryKey(['id']);
        $schemaManager->createTable($table2);

        if ($platform instanceof OraclePlatform) {
            $this->connection->executeStatement(
                <<<SQL
                    ALTER TABLE test_t1 ADD CONSTRAINT $constraintName
                    FOREIGN KEY (ref_id) REFERENCES test_t2 (id)
                    DEFERRABLE INITIALLY IMMEDIATE
                    SQL,
            );
        } else {
            $createConstraint = new ForeignKeyConstraint(['ref_id'], 'test_t2', ['id'], $constraintName);

            $schemaManager->createForeignKey($createConstraint, 'test_t1');
            if (! $this->supportsDeferrableConstraints()) {
                return;
            }

            $this->connection->executeStatement(
                sprintf('ALTER TABLE test_t1 ALTER CONSTRAINT %s DEFERRABLE', $constraintName),
            );
        }
    }

    public function testTransactionalViolatesDeferredConstraint(): void
    {
        $this->skipIfDeferrableIsNotSupported();

        $this->connection->transactional(function (Connection $connection): void {
            $connection->executeStatement(sprintf('SET CONSTRAINTS "%s" DEFERRED', $this->constraintName));

            $connection->executeStatement('INSERT INTO test_t1 VALUES (1)');

            $this->expectConstraintViolation(true);
        });
    }

    public function testTransactionalViolatesConstraint(): void
    {
        $this->connection->transactional(function (Connection $connection): void {
            $this->expectConstraintViolation(false);
            $connection->executeStatement('INSERT INTO test_t1 VALUES (1)');
        });
    }

    public function testTransactionalViolatesDeferredConstraintWhileUsingTransactionNesting(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->skipIfDeferrableIsNotSupported();

        $this->connection->setNestTransactionsWithSavepoints(true);

        $this->connection->transactional(function (Connection $connection): void {
            $connection->executeStatement(sprintf('SET CONSTRAINTS "%s" DEFERRED', $this->constraintName));
            $connection->beginTransaction();
            $connection->executeStatement('INSERT INTO test_t1 VALUES (1)');
            $connection->commit();

            $this->expectConstraintViolation(true);
        });
    }

    public function testTransactionalViolatesConstraintWhileUsingTransactionNesting(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->connection->setNestTransactionsWithSavepoints(true);

        $this->connection->transactional(function (Connection $connection): void {
            $connection->beginTransaction();

            try {
                $this->connection->executeStatement('INSERT INTO test_t1 VALUES (1)');
            } catch (Throwable $t) {
                $this->connection->rollBack();

                $this->expectConstraintViolation(false);

                throw $t;
            }
        });
    }

    public function testCommitViolatesDeferredConstraint(): void
    {
        $this->skipIfDeferrableIsNotSupported();

        $this->connection->beginTransaction();
        $this->connection->executeStatement(sprintf('SET CONSTRAINTS "%s" DEFERRED', $this->constraintName));
        $this->connection->executeStatement('INSERT INTO test_t1 VALUES (1)');

        $this->expectConstraintViolation(true);
        $this->connection->commit();
    }

    public function testInsertViolatesConstraint(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->connection->executeStatement('INSERT INTO test_t1 VALUES (1)');
        } catch (Throwable $t) {
            $this->connection->rollBack();

            $this->expectConstraintViolation(false);

            throw $t;
        }
    }

    public function testCommitViolatesDeferredConstraintWhileUsingTransactionNesting(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->skipIfDeferrableIsNotSupported();

        $this->connection->setNestTransactionsWithSavepoints(true);

        $this->connection->beginTransaction();
        $this->connection->executeStatement(sprintf('SET CONSTRAINTS "%s" DEFERRED', $this->constraintName));
        $this->connection->beginTransaction();
        $this->connection->executeStatement('INSERT INTO test_t1 VALUES (1)');
        $this->connection->commit();

        $this->expectConstraintViolation(true);

        $this->connection->commit();
    }

    public function testCommitViolatesConstraintWhileUsingTransactionNesting(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->skipIfDeferrableIsNotSupported();

        $this->connection->setNestTransactionsWithSavepoints(true);

        $this->connection->beginTransaction();
        $this->connection->beginTransaction();

        try {
            $this->connection->executeStatement('INSERT INTO test_t1 VALUES (1)');
        } catch (Throwable $t) {
            $this->connection->rollBack();

            $this->expectConstraintViolation(false);

            throw $t;
        }
    }

    private function supportsDeferrableConstraints(): bool
    {
        $platform = $this->connection->getDatabasePlatform();

        return $platform instanceof OraclePlatform || $platform instanceof PostgreSQLPlatform;
    }

    private function skipIfDeferrableIsNotSupported(): void
    {
        if ($this->supportsDeferrableConstraints()) {
            return;
        }

        self::markTestSkipped('Only databases supporting deferrable constraints are eligible for this test.');
    }

    private function expectConstraintViolation(bool $deferred): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLServerPlatform) {
            $this->expectExceptionMessage(
                sprintf('conflicted with the FOREIGN KEY constraint "%s"', $this->constraintName),
            );

            return;
        }

        if ($this->connection->getDatabasePlatform() instanceof DB2Platform) {
            // No concrete message is provided
            $this->expectException(DriverException::class);

            return;
        }

        if ($deferred) {
            if ($this->connection->getDatabasePlatform() instanceof OraclePlatform) {
                $this->expectExceptionMessageMatches(
                    sprintf('~integrity constraint \(.+\.%s\) violated~', $this->constraintName),
                );

                return;
            }

            $driver = $this->connection->getDriver();
            if ($driver instanceof AbstractPostgreSQLDriver) {
                $this->expectExceptionMessageMatches(
                    sprintf('~violates foreign key constraint "%s"~', $this->constraintName),
                );

                if ($driver instanceof PDOPgSQLDriver) {
                    $this->expectException(PDOException::class);

                    return;
                }

                if ($driver instanceof PgSQLDriver) {
                    $this->expectException(PgSQLException::class);

                    return;
                }

                Assert::fail('Unsupported PG driver');
            }

            Assert::fail('Unsupported platform');
        } else {
            $this->expectException(ForeignKeyConstraintViolationException::class);
        }
    }

    protected function tearDown(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->dropTable('test_t1');
        $schemaManager->dropTable('test_t2');

        $this->markConnectionNotReusable();

        parent::tearDown();
    }
}
