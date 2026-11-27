<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\SQL\Builder;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

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

    public function testDropTablesReferencedByPopulatedTable(): void
    {
        $this->dropTablesReferencedByPopulatedTable(
            $this->createAuthorTable('author'),
            $this->createBookTable('author'),
        );
    }

    public function testDropTablesReferencedBySelfReferencingPopulatedTable(): void
    {
        $book = $this->createBookTable('author');
        $book->addForeignKeyConstraint('book', ['previous_book_id'], ['id']);

        $this->dropTablesReferencedByPopulatedTable($this->createAuthorTable('author'), $book);
    }

    public function testDropTablesReferencedByPopulatedTableUsingDifferentCase(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped('This test covers the case-insensitive matching of table names on SQLite.');
        }

        $this->dropTablesReferencedByPopulatedTable(
            $this->createAuthorTable('Author'),
            $this->createBookTable('author'),
        );
    }

    private function dropTablesReferencedByPopulatedTable(Table $author, Table $book): void
    {
        $authorTableName = $author->getName();

        $this->dropTableIfExists('book');
        $this->dropTableIfExists($authorTableName);

        $schema = Schema::editor()
            ->setTables($author, $book)
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);

        $this->connection->insert($authorTableName, ['id' => 1]);
        $this->connection->insert('book', ['id' => 1, 'author_id' => 1]);

        $schemaManager->dropSchemaObjects($schema);

        self::assertFalse($schemaManager->tablesExist([$authorTableName]));
        self::assertFalse($schemaManager->tablesExist(['book']));
    }

    /** @param non-empty-string $name */
    private function createAuthorTable(string $name): Table
    {
        return Table::editor()
            ->setUnquotedName($name)
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
    }

    /** @param non-empty-string $authorTableName */
    private function createBookTable(string $authorTableName): Table
    {
        $table = Table::editor()
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

        $table->addForeignKeyConstraint($authorTableName, ['author_id'], ['id']);

        return $table;
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
