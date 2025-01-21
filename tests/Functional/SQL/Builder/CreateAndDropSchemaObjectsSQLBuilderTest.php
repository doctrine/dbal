<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\SQL\Builder;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class CreateAndDropSchemaObjectsSQLBuilderTest extends FunctionalTestCase
{
    /** @throws Exception */
    public function testCreateAndDropTablesWithCircularForeignKeys(): void
    {
        $name1 = OptionallyQualifiedName::unquoted('t1');
        $name2 = OptionallyQualifiedName::unquoted('t2');

        $schema = new Schema();
        $this->createTable($schema, $name1, $name2);
        $this->createTable($schema, $name2, $name1);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);

        self::assertTrue($schemaManager->tablesExist([$name1->toString()]));
        self::assertTrue($schemaManager->tablesExist([$name2->toString()]));

        $this->introspectForeignKey($schemaManager, $name1, $name2);
        $this->introspectForeignKey($schemaManager, $name2, $name1);

        $schemaManager->dropSchemaObjects($schema);

        self::assertFalse($schemaManager->tablesExist([$name1->toString()]));
        self::assertFalse($schemaManager->tablesExist([$name2->toString()]));
    }

    private function createTable(
        Schema $schema,
        OptionallyQualifiedName $name,
        OptionallyQualifiedName $otherName,
    ): void {
        $table = $schema->createTable($name->toString());
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('other_id', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint($otherName->toString(), ['other_id'], ['id']);
    }

    /** @throws Exception */
    private function introspectForeignKey(
        AbstractSchemaManager $schemaManager,
        OptionallyQualifiedName $tableName,
        OptionallyQualifiedName $expectedReferencedTableName,
    ): void {
        $foreignKeys = $schemaManager->listTableForeignKeys($tableName->toString());
        self::assertCount(1, $foreignKeys);
        $this->assertOptionallyQualifiedNameEquals(
            $expectedReferencedTableName,
            $foreignKeys[0]->getReferencedTableName(),
        );
    }
}
