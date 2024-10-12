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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use PHPUnit\Framework\Assert;
use Throwable;

use function sprintf;

final class UniqueConstraintViolationsTest extends FunctionalTestCase
{
    private string $constraintName = '';

    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof OraclePlatform) {
            $constraintName = 'C1_UNIQUE';
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $constraintName = 'c1_unique';
        } else {
            $constraintName = 'c1_unique';
        }

        $this->constraintName = $constraintName;

        $schemaManager = $this->connection->createSchemaManager();

        $table = new Table('unique_constraint_violations');
        $table->addColumn('unique_field', 'integer', ['notnull' => true]);
        $schemaManager->createTable($table);

        if ($platform instanceof OraclePlatform) {
            $createConstraint = sprintf(
                'ALTER TABLE unique_constraint_violations ' .
                'ADD CONSTRAINT %s UNIQUE (unique_field) DEFERRABLE INITIALLY IMMEDIATE',
                $constraintName,
            );
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $createConstraint = sprintf(
                'ALTER TABLE unique_constraint_violations ' .
                'ADD CONSTRAINT %s UNIQUE (unique_field) DEFERRABLE INITIALLY IMMEDIATE',
                $constraintName,
            );
        } elseif ($platform instanceof SqlitePlatform) {
            $createConstraint = sprintf(
                'CREATE UNIQUE INDEX %s ON unique_constraint_violations(unique_field)',
                $constraintName,
            );
        } else {
            $createConstraint = new UniqueConstraint($constraintName, ['unique_field']);
        }

        if ($createConstraint instanceof UniqueConstraint) {
            $schemaManager->createUniqueConstraint($createConstraint, 'unique_constraint_violations');
        } else {
            $this->connection->executeStatement($createConstraint);
        }

        $this->connection->executeStatement('INSERT INTO unique_constraint_violations VALUES (1)');
    }

    public function testTransactionalViolatesDeferredConstraint(): void
    {
        $this->skipIfDeferrableIsNotSupported();

        $this->connection->transactional(function (Connection $connection): void {
            $connection->executeStatement(sprintf('SET CONSTRAINTS "%s" DEFERRED', $this->constraintName));

            $connection->executeStatement('INSERT INTO unique_constraint_violations VALUES (1)');

            $this->expectUniqueConstraintViolation(true);
        });
    }

    public function testTransactionalViolatesConstraint(): void
    {
        $this->connection->transactional(function (Connection $connection): void {
            $this->expectUniqueConstraintViolation(false);
            $connection->executeStatement('INSERT INTO unique_constraint_violations VALUES (1)');
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
            $connection->executeStatement('INSERT INTO unique_constraint_violations VALUES (1)');
            $connection->commit();

            $this->expectUniqueConstraintViolation(true);
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
                $this->connection->executeStatement('INSERT INTO unique_constraint_violations VALUES (1)');
            } catch (Throwable $t) {
                $this->connection->rollBack();

                $this->expectUniqueConstraintViolation(false);

                throw $t;
            }
        });
    }

    public function testCommitViolatesDeferredConstraint(): void
    {
        $this->skipIfDeferrableIsNotSupported();

        $this->connection->beginTransaction();
        $this->connection->executeStatement(sprintf('SET CONSTRAINTS "%s" DEFERRED', $this->constraintName));
        $this->connection->executeStatement('INSERT INTO unique_constraint_violations VALUES (1)');

        $this->expectUniqueConstraintViolation(true);
        $this->connection->commit();
    }

    public function testInsertViolatesConstraint(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->connection->executeStatement('INSERT INTO unique_constraint_violations VALUES (1)');
        } catch (Throwable $t) {
            $this->connection->rollBack();

            $this->expectUniqueConstraintViolation(false);

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
        $this->connection->executeStatement('INSERT INTO unique_constraint_violations VALUES (1)');
        $this->connection->commit();

        $this->expectUniqueConstraintViolation(true);

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
            $this->connection->executeStatement('INSERT INTO unique_constraint_violations VALUES (1)');
        } catch (Throwable $t) {
            $this->connection->rollBack();

            $this->expectUniqueConstraintViolation(false);

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

    private function expectUniqueConstraintViolation(bool $deferred): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLServerPlatform) {
            $this->expectExceptionMessage(sprintf("Violation of UNIQUE KEY constraint '%s'", $this->constraintName));

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
                    sprintf('~unique constraint \(.+\.%s\) violated~', $this->constraintName),
                );

                return;
            }

            $driver = $this->connection->getDriver();
            if ($driver instanceof AbstractPostgreSQLDriver) {
                $this->expectExceptionMessageMatches(
                    sprintf('~duplicate key value violates unique constraint "%s"~', $this->constraintName),
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
            $this->expectException(UniqueConstraintViolationException::class);
        }
    }

    protected function tearDown(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->dropTable('unique_constraint_violations');

        $this->markConnectionNotReusable();

        parent::tearDown();
    }
}
