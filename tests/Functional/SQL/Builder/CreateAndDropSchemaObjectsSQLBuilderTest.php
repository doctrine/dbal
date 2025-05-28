<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\SQL\Builder;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

use function array_values;

class CreateAndDropSchemaObjectsSQLBuilderTest extends FunctionalTestCase
{
    /** @throws Exception */
    public function testCreateAndDropTablesWithCircularForeignKeys(): void
    {
        $name1 = OptionallyQualifiedName::unquoted('t1');
        $name2 = OptionallyQualifiedName::unquoted('t2');

        $table1 = $this->createTable($name1, $name2);
        $table2 = $this->createTable($name2, $name1);

        $schema = new Schema([$table1, $table2]);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);

        self::assertTrue($schemaManager->tablesExist([$name1->toString()]));
        self::assertTrue($schemaManager->tablesExist([$name2->toString()]));

        $table1 = $schemaManager->introspectTable('t1');
        $table2 = $schemaManager->introspectTable('t2');

        $this->assertForeignKey($table1, $name2);
        $this->assertForeignKey($table2, $name1);

        $schema = new Schema([$table1, $table2]);

        $schemaManager->dropSchemaObjects($schema);

        self::assertFalse($schemaManager->tablesExist([$name1->toString()]));
        self::assertFalse($schemaManager->tablesExist([$name2->toString()]));
    }

    private function createTable(
        OptionallyQualifiedName $name,
        OptionallyQualifiedName $otherName,
    ): Table {
        return Table::editor()
            ->setName($name)
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
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('other_id')
                    ->setReferencedTableName($otherName)
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();
    }

    /** @throws Exception */
    private function assertForeignKey(Table $table, OptionallyQualifiedName $expectedReferencedTableName): void
    {
        $foreignKeys = array_values($table->getForeignKeys());
        self::assertCount(1, $foreignKeys);
        $this->assertOptionallyQualifiedNameEquals(
            $expectedReferencedTableName,
            $foreignKeys[0]->getReferencedTableName(),
        );
    }
}
