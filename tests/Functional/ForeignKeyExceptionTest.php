<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class ForeignKeyExceptionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->connection->getDriver() instanceof ExceptionConverterDriver) {
            self::markTestSkipped('Driver does not support special exception handling.');
        }

        $schemaManager = $this->connection->getSchemaManager();

        $table = new Table('constraint_error_table');
        $table->addColumn('id', 'integer', []);
        $table->setPrimaryKey(['id']);

        $owningTable = new Table('owning_table');
        $owningTable->addColumn('id', 'integer', []);
        $owningTable->addColumn('constraint_id', 'integer', []);
        $owningTable->setPrimaryKey(['id']);
        $owningTable->addForeignKeyConstraint($table, ['constraint_id'], ['id']);

        $schemaManager->createTable($table);
        $schemaManager->createTable($owningTable);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $schemaManager = $this->connection->getSchemaManager();

        $schemaManager->dropTable('owning_table');
        $schemaManager->dropTable('constraint_error_table');
    }

    public function testForeignKeyConstraintViolationExceptionOnInsert(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SqlitePlatform) {
            $this->connection->exec('PRAGMA foreign_keys = ON');
        } elseif (! $platform->supportsForeignKeyConstraints()) {
            self::markTestSkipped('Only fails on platforms with foreign key constraints.');
        }

        $this->connection->insert('constraint_error_table', ['id' => 1]);
        $this->connection->insert('owning_table', ['id' => 1, 'constraint_id' => 1]);

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        $this->connection->insert('owning_table', ['id' => 2, 'constraint_id' => 2]);
    }

    public function testForeignKeyConstraintViolationExceptionOnUpdate(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SqlitePlatform) {
            $this->connection->exec('PRAGMA foreign_keys = ON');
        } elseif (! $platform->supportsForeignKeyConstraints()) {
            self::markTestSkipped('Only fails on platforms with foreign key constraints.');
        }

        $this->connection->insert('constraint_error_table', ['id' => 1]);
        $this->connection->insert('owning_table', ['id' => 1, 'constraint_id' => 1]);

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        $this->connection->update('constraint_error_table', ['id' => 2], ['id' => 1]);
    }

    public function testForeignKeyConstraintViolationExceptionOnDelete(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SqlitePlatform) {
            $this->connection->exec('PRAGMA foreign_keys = ON');
        } elseif (! $platform->supportsForeignKeyConstraints()) {
            self::markTestSkipped('Only fails on platforms with foreign key constraints.');
        }

        $this->connection->insert('constraint_error_table', ['id' => 1]);
        $this->connection->insert('owning_table', ['id' => 1, 'constraint_id' => 1]);

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        $this->connection->delete('constraint_error_table', ['id' => 1]);
    }

    public function testForeignKeyConstraintViolationExceptionOnTruncate(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SqlitePlatform) {
            $this->connection->exec('PRAGMA foreign_keys = ON');
        } elseif (! $platform->supportsForeignKeyConstraints()) {
            self::markTestSkipped('Only fails on platforms with foreign key constraints.');
        }

        $this->connection->insert('constraint_error_table', ['id' => 1]);
        $this->connection->insert('owning_table', ['id' => 1, 'constraint_id' => 1]);

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        $this->connection->executeUpdate($platform->getTruncateTableSQL('constraint_error_table'));
    }
}
