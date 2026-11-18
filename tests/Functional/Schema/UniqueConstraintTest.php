<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
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
    /** @throws Exception */
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

    /** @throws Exception */
    public function testTableWithUniqueConstraintOnForeignKeyColumn(): void
    {
        $this->dropTableIfExists('uc_orders');
        $this->dropTableIfExists('uc_articles');

        $articles = Table::editor()
            ->setUnquotedName('uc_articles')
            ->setColumns($this->intColumn('id'))
            ->setPrimaryKeyConstraint($this->primaryKeyOnId())
            ->create();

        $orders = Table::editor()
            ->setUnquotedName('uc_orders')
            ->setColumns($this->intColumn('id'), $this->intColumn('article_id'))
            ->setPrimaryKeyConstraint($this->primaryKeyOnId())
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedName('uc_orders_article_uq')
                    ->setUnquotedColumnNames('article_id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    // The name has the prefix of this test's tables, because MySQL compares foreign key names
                    // case-insensitively within a schema and Db2 folds unquoted names to upper case. Without it,
                    // the name could clash with another test's foreign key.
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

    /** @throws Exception */
    public function testTableWithUniqueIndex(): void
    {
        $this->dropTableIfExists('uc_members');

        $members = $this->membersTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($members);

        $diff = $schemaManager->createComparator()
            ->compareTables($schemaManager->introspectTableByUnquotedName('uc_members'), $members);

        self::assertTrue($diff->isEmpty());
    }

    /** @throws Exception */
    public function testDropColumnCoveredByUniqueConstraint(): void
    {
        $this->dropTableIfExists('uc_users');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($this->usersTable());

        $desired = Table::editor()
            ->setUnquotedName('uc_users')
            ->setColumns($this->intColumn('id'))
            ->setPrimaryKeyConstraint($this->primaryKeyOnId())
            ->create();

        $diff = $schemaManager->createComparator()
            ->compareTables($schemaManager->introspectTableByUnquotedName('uc_users'), $desired);

        $schemaManager->alterTable($diff);

        $introspected = $schemaManager->introspectTableByUnquotedName('uc_users');

        self::assertFalse($introspected->hasColumn('email'));
        self::assertCount(0, $introspected->getUniqueConstraints());
    }

    /** @throws Exception */
    public function testModifyUniqueConstraint(): void
    {
        $this->dropTableIfExists('uc_users');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($this->usersTable());

        $desired = $this->usersTable()
            ->edit()
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedName('uc_users_email_uq')
                    ->setUnquotedColumnNames('id', 'email')
                    ->create(),
            )
            ->create();

        $comparator = $schemaManager->createComparator();

        $schemaManager->alterTable(
            $comparator->compareTables($schemaManager->introspectTableByUnquotedName('uc_users'), $desired),
        );

        self::assertTrue(
            $comparator->compareTables($schemaManager->introspectTableByUnquotedName('uc_users'), $desired)
                ->isEmpty(),
        );
    }

    /** @throws Exception */
    public function testTableWithUnnamedUniqueConstraint(): void
    {
        $this->dropTableIfExists('uc_users');

        $users = $this->usersTable()
            ->edit()
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedColumnNames('email')
                    ->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($users);

        $comparator   = $schemaManager->createComparator();
        $introspected = $schemaManager->introspectTableByUnquotedName('uc_users');

        self::assertTrue($comparator->compareTables($introspected, $users)->isEmpty());
        self::assertTrue($comparator->compareTables($users, $introspected)->isEmpty());
    }

    /**
     * A unique index whose name the desired table gives to another index must not count as the unique constraint the
     * desired table declares over its columns.
     *
     * @throws Exception
     */
    public function testUniqueIndexNameTakenByAnotherIndex(): void
    {
        $this->dropTableIfExists('uc_members');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($this->membersTable());

        $desired = $this->membersTable()
            ->edit()
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('uc_members_email_uidx')
                    ->setUnquotedColumnNames('id', 'email')
                    ->create(),
            )
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedColumnNames('email')
                    ->create(),
            )
            ->create();

        $comparator = $schemaManager->createComparator();

        $schemaManager->alterTable(
            $comparator->compareTables($schemaManager->introspectTableByUnquotedName('uc_members'), $desired),
        );

        self::assertTrue(
            $comparator->compareTables($schemaManager->introspectTableByUnquotedName('uc_members'), $desired)
                ->isEmpty(),
        );
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
            ->setPrimaryKeyConstraint($this->primaryKeyOnId())
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
            ->setPrimaryKeyConstraint($this->primaryKeyOnId())
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

    private function primaryKeyOnId(): PrimaryKeyConstraint
    {
        return PrimaryKeyConstraint::editor()
            ->setUnquotedColumnNames('id')
            ->create();
    }
}
