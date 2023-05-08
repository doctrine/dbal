<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\SQL\Builder;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

use function strtolower;

class CreateAndDropSchemaObjectsSQLBuilderTest extends FunctionalTestCase
{
    public function testCreateAndDropTablesWithCircularForeignKeys(): void
    {
        $schema = new Schema();
        $this->createTable($schema, 't1', 't2');
        $this->createTable($schema, 't2', 't1');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);

        $this->introspectForeignKey($schemaManager, 't1', 't2');
        $this->introspectForeignKey($schemaManager, 't2', 't1');

        $schemaManager->dropSchemaObjects($schema);

        self::assertFalse($schemaManager->tablesExist(['t1']));
        self::assertFalse($schemaManager->tablesExist(['t2']));
    }

    private function createTable(Schema $schema, string $name, string $otherName): void
    {
        $table = $schema->createTable($name);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn($otherName . '_id', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint($otherName, [$otherName . '_id'], ['id']);
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
