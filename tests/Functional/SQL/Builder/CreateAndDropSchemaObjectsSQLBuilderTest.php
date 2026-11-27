<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\SQL\Builder;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\TestWith;

use function strtolower;

class CreateAndDropSchemaObjectsSQLBuilderTest extends FunctionalTestCase
{
    public function testCreateAndDropTablesWithCircularForeignKeys(): void
    {
        $table1 = $this->createTable('t1', 't2');
        $table2 = $this->createTable('t2', 't1');

        $schema = Schema::editor()
            ->setTables($table1, $table2)
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);

        $this->introspectForeignKey($schemaManager, 't1', 't2');
        $this->introspectForeignKey($schemaManager, 't2', 't1');

        $schemaManager->dropSchemaObjects($schema);

        self::assertFalse($schemaManager->tablesExist(['t1']));
        self::assertFalse($schemaManager->tablesExist(['t2']));
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testDropTablesReferencedByPopulatedTable(bool $isReferencingTableSelfReferencing): void
    {
        $this->dropTableIfExists('book');
        $this->dropTableIfExists('author');

        $author = Table::editor()
            ->setUnquotedName('author')
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

        $book = Table::editor()
            ->setUnquotedName('book')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('author_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('previous_book_id')
                    ->setTypeName(Types::INTEGER)
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $book->addForeignKeyConstraint('author', ['author_id'], ['id']);

        if ($isReferencingTableSelfReferencing) {
            $book->addForeignKeyConstraint('book', ['previous_book_id'], ['id']);
        }

        $schema = Schema::editor()
            ->setTables($author, $book)
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);

        $this->connection->insert('author', ['id' => 1]);
        $this->connection->insert('book', ['id' => 1, 'author_id' => 1]);

        $schemaManager->dropSchemaObjects($schema);

        self::assertFalse($schemaManager->tablesExist(['author']));
        self::assertFalse($schemaManager->tablesExist(['book']));
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string $otherName
     */
    private function createTable(string $name, string $otherName): Table
    {
        $table = Table::editor()
            ->setUnquotedName($name)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('other_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $table->addForeignKeyConstraint($otherName, ['other_id'], ['id']);

        return $table;
    }

    private function introspectForeignKey(
        AbstractSchemaManager $schemaManager,
        string $tableName,
        string $expectedForeignTableName,
    ): void {
        $foreignKeys = $schemaManager->listTableForeignKeys($tableName);
        self::assertCount(1, $foreignKeys);
        self::assertSame($expectedForeignTableName, strtolower($foreignKeys[0]->getForeignTableName()));
    }
}
