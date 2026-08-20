<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

final class UniqueConstraintTest extends FunctionalTestCase
{
    /**
     * A table created from a desired schema must introspect back equal to it.
     *
     * @throws Exception
     */
    public function testTableWithUniqueConstraint(): void
    {
        $this->dropTableIfExists('uc_users');

        $users = $this->usersTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($users);

        $diff = $schemaManager->createComparator()
            ->compareTables($schemaManager->introspectTableByUnquotedName('uc_users'), $users);

        self::assertTrue($diff->isEmpty());
    }

    /**
     * A table whose foreign key and unique constraint cover the same column must round-trip.
     *
     * @throws Exception
     */
    public function testTableWithUniqueConstraintOnForeignKeyColumn(): void
    {
        $this->dropTableIfExists('uc_orders');
        $this->dropTableIfExists('uc_articles');

        $articles = Table::editor()
            ->setUnquotedName('uc_articles')
            ->setColumns($this->intColumn('id'))
            ->setPrimaryKeyConstraint($this->primaryKeyOn('id'))
            ->create();

        $orders = Table::editor()
            ->setUnquotedName('uc_orders')
            ->setColumns($this->intColumn('id'), $this->intColumn('article_id'))
            ->setPrimaryKeyConstraint($this->primaryKeyOn('id'))
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedName('uc_orders_article_uq')
                    ->setUnquotedColumnNames('article_id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    // Prefixed, like this test's tables: MySQL scopes foreign key names to the
                    // schema and compares them case-insensitively, and Db2 folds unquoted names to
                    // upper case, where they would meet SchemaManagerTest's quoted 'Articles'.
                    ->setUnquotedName('uc_orders_articles_fk')
                    ->setUnquotedReferencingColumnNames('article_id')
                    ->setUnquotedReferencedTableName('uc_articles')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($articles);
        $schemaManager->createTable($orders);

        $diff = $schemaManager->createComparator()
            ->compareTables($schemaManager->introspectTableByUnquotedName('uc_orders'), $orders);

        self::assertTrue($diff->isEmpty());
    }

    /**
     * Migrating a table towards a schema that declares a unique constraint must leave the
     * constraint enforced.
     *
     * @throws Exception
     */
    public function testUniquenessIsEnforcedAfterMigratingToDeclaredSchema(): void
    {
        $this->dropTableIfExists('uc_users');

        $users = $this->usersTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($users);

        $diff = $schemaManager->createComparator()
            ->compareTables($schemaManager->introspectTableByUnquotedName('uc_users'), $users);

        if (! $diff->isEmpty()) {
            $schemaManager->alterTable($diff);
        }

        $this->connection->insert('uc_users', ['id' => 1, 'email' => 'jwage@example.com']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->connection->insert('uc_users', ['id' => 2, 'email' => 'jwage@example.com']);
    }

    /**
     * A table declaring a unique index must round-trip, and keep enforcing uniqueness.
     *
     * @throws Exception
     */
    public function testTableWithUniqueIndex(): void
    {
        $this->dropTableIfExists('uc_members');

        $members = $this->membersTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($members);

        $diff = $schemaManager->createComparator()
            ->compareTables($schemaManager->introspectTableByUnquotedName('uc_members'), $members);

        self::assertTrue($diff->isEmpty());

        $this->connection->insert('uc_members', ['id' => 1, 'email' => 'jwage@example.com']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->connection->insert('uc_members', ['id' => 2, 'email' => 'jwage@example.com']);
    }

    /**
     * Dropping a column drops the unique constraint that covers it.
     *
     * @throws Exception
     */
    public function testDropColumnCoveredByUniqueConstraint(): void
    {
        $this->dropTableIfExists('uc_users');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($this->usersTable());

        $desired = Table::editor()
            ->setUnquotedName('uc_users')
            ->setColumns($this->intColumn('id'))
            ->setPrimaryKeyConstraint($this->primaryKeyOn('id'))
            ->create();

        $diff = $schemaManager->createComparator()
            ->compareTables($schemaManager->introspectTableByUnquotedName('uc_users'), $desired);

        $schemaManager->alterTable($diff);

        $introspected = $schemaManager->introspectTableByUnquotedName('uc_users');

        self::assertFalse($introspected->hasColumn('email'));
        self::assertCount(0, $introspected->getUniqueConstraints());
    }

    private function membersTable(): Table
    {
        return Table::editor()
            ->setUnquotedName('uc_members')
            ->setColumns(
                $this->intColumn('id'),
                Column::editor()
                    ->setUnquotedName('email')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->create(),
            )
            ->setPrimaryKeyConstraint($this->primaryKeyOn('id'))
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('uc_members_email_uidx')
                    ->setUnquotedColumnNames('email')
                    ->setType(IndexType::UNIQUE)
                    ->create(),
            )
            ->create();
    }

    private function usersTable(): Table
    {
        return Table::editor()
            ->setUnquotedName('uc_users')
            ->setColumns(
                $this->intColumn('id'),
                Column::editor()
                    ->setUnquotedName('email')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->create(),
            )
            ->setPrimaryKeyConstraint($this->primaryKeyOn('id'))
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedName('uc_users_email_uq')
                    ->setUnquotedColumnNames('email')
                    ->create(),
            )
            ->create();
    }

    /** @param non-empty-string $name */
    private function intColumn(string $name): Column
    {
        return Column::editor()
            ->setUnquotedName($name)
            ->setTypeName(Types::INTEGER)
            ->create();
    }

    /** @param non-empty-string $column */
    private function primaryKeyOn(string $column): PrimaryKeyConstraint
    {
        return PrimaryKeyConstraint::editor()
            ->setUnquotedColumnNames($column)
            ->create();
    }
}
