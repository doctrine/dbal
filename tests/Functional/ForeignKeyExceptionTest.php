<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver;
use Doctrine\DBAL\Driver\IBMDB2;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class ForeignKeyExceptionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $driver = $this->connection->getDriver();

        if ($driver instanceof IBMDB2\Driver || $driver instanceof AbstractSQLServerDriver) {
            self::markTestSkipped('Driver does not support special exception handling.');
        }

        $schemaManager = $this->connection->createSchemaManager();

        $table = Table::editor()
            ->setUnquotedName('constraint_error_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $owningTable = Table::editor()
            ->setUnquotedName('owning_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('constraint_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('constraint_id')
                    ->setUnquotedReferencedTableName('constraint_error_table')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $schemaManager->createTable($table);
        $schemaManager->createTable($owningTable);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $schemaManager = $this->connection->createSchemaManager();

        $schemaManager->dropTable('owning_table');
        $schemaManager->dropTable('constraint_error_table');
    }

    public function testForeignKeyConstraintViolationExceptionOnInsert(): void
    {
        $this->connection->insert('constraint_error_table', ['id' => 1]);
        $this->connection->insert('owning_table', ['id' => 1, 'constraint_id' => 1]);

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        $this->connection->insert('owning_table', ['id' => 2, 'constraint_id' => 2]);
    }

    public function testForeignKeyConstraintViolationExceptionOnUpdate(): void
    {
        $this->connection->insert('constraint_error_table', ['id' => 1]);
        $this->connection->insert('owning_table', ['id' => 1, 'constraint_id' => 1]);

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        $this->connection->update('constraint_error_table', ['id' => 2], ['id' => 1]);
    }

    public function testForeignKeyConstraintViolationExceptionOnDelete(): void
    {
        $this->connection->insert('constraint_error_table', ['id' => 1]);
        $this->connection->insert('owning_table', ['id' => 1, 'constraint_id' => 1]);

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        $this->connection->delete('constraint_error_table', ['id' => 1]);
    }

    public function testForeignKeyConstraintViolationExceptionOnTruncate(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $this->connection->insert('constraint_error_table', ['id' => 1]);
        $this->connection->insert('owning_table', ['id' => 1, 'constraint_id' => 1]);

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        $this->connection->executeStatement($platform->getTruncateTableSQL('constraint_error_table'));
    }
}
