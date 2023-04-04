<?php

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\StringType;

class ForeignKeyUpdateTest extends FunctionalTestCase
{
    public function testChangeSelfReferencingForeignKeyFieldFromIntToString(): void
    {
        $table = new Table('test_foreign_key_self_update');
        $table->addColumn('id', 'integer');
        $table->addColumn('fk', 'integer', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint(
            'test_foreign_key_self_update',
            ['fk'],
            ['id'],
            [],
            'fk_test_foreign_key_self_update',
        );

        $this->dropAndCreateTable($table);

        $this->connection->insert('test_foreign_key_self_update', ['id' => 1]);
        $this->connection->insert('test_foreign_key_self_update', ['id' => 2, 'fk' => 1]);

        $table = clone $table;

        $table->modifyColumn('id', ['type' => new StringType()]);
        $table->modifyColumn('fk', ['type' => new StringType()]);

        $schemaManager = $this->connection->createSchemaManager();
        $comparator    = $schemaManager->createComparator();

        $schemaManager->alterTable($comparator->compareTables(
            $schemaManager->introspectTable('test_foreign_key_self_update'),
            $table,
        ));

        self::assertInstanceOf(
            StringType::class,
            $schemaManager->introspectTable('test_foreign_key_self_update')->getColumn('id')->getType(),
        );
    }

    public function testUpdateForeignKeyFieldFromIntToString(): void
    {
        $table1 = new Table('test_foreign_parent');
        $table1->addColumn('id', 'integer');
        $table1->setPrimaryKey(['id']);

        $table2 = new Table('test_foreign_child');
        $table2->addColumn('id', 'integer');
        $table2->addColumn('fk', 'integer');
        $table2->setPrimaryKey(['id']);
        $table2->addForeignKeyConstraint(
            'test_foreign_parent',
            ['fk'],
            ['id'],
            [],
            'fk_test_foreign_child',
        );

        $this->dropTableIfExists('test_foreign_child');
        $this->dropAndCreateTable($table1);
        $this->dropAndCreateTable($table2);

        $this->connection->insert('test_foreign_parent', ['id' => 1]);
        $this->connection->insert('test_foreign_child', ['id' => 1, 'fk' => 1]);

        $table1 = clone $table1;
        $table1->modifyColumn('id', ['type' => new StringType()]);

        $table2 = clone $table2;
        $table2->modifyColumn('fk', ['type' => new StringType()]);

        $schemaManager = $this->connection->createSchemaManager();
        $comparator    = $schemaManager->createComparator();

        $oldSchema = new Schema([
            $schemaManager->introspectTable('test_foreign_parent'),
            $schemaManager->introspectTable('test_foreign_child'),
        ]);
        $newSchema = new Schema([$table1, $table2]);

        $schemaManager->alterSchema($comparator->compareSchemas($oldSchema, $newSchema));

        self::assertInstanceOf(
            StringType::class,
            $schemaManager->introspectTable('test_foreign_parent')->getColumn('id')->getType(),
        );
    }
}
