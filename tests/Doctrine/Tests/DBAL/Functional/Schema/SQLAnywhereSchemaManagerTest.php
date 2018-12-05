<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\View;

class SQLAnywhereSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    public function testCreateAndListViews()
    {
        $this->createTestTable('view_test_table');

        $name = 'doctrine_test_view';
        $sql  = 'SELECT * from DBA.view_test_table';

        $view = new View($name, $sql);

        $this->schemaManager->dropAndCreateView($view);

        $views = $this->schemaManager->listViews();

        self::assertCount(1, $views, 'Database has to have one view.');
        self::assertInstanceOf(View::class, $views[$name]);
        self::assertEquals($name, $views[$name]->getName());
        self::assertRegExp('/^SELECT \* from "?DBA"?\."?view_test_table"?$/', $views[$name]->getSql());
    }

    public function testDropAndCreateAdvancedIndex()
    {
        $table = $this->getTestTable('test_create_advanced_index');
        $this->schemaManager->dropAndCreateTable($table);
        $this->schemaManager->dropAndCreateIndex(
            new Index('test', ['test'], true, false, ['clustered', 'with_nulls_not_distinct', 'for_olap_workload']),
            $table->getName()
        );

        $tableIndexes = $this->schemaManager->listTableIndexes('test_create_advanced_index');
        self::assertInternalType('array', $tableIndexes);
        self::assertEquals('test', $tableIndexes['test']->getName());
        self::assertEquals(['test'], $tableIndexes['test']->getColumns());
        self::assertTrue($tableIndexes['test']->isUnique());
        self::assertFalse($tableIndexes['test']->isPrimary());
        self::assertTrue($tableIndexes['test']->hasFlag('clustered'));
        self::assertTrue($tableIndexes['test']->hasFlag('with_nulls_not_distinct'));
        self::assertTrue($tableIndexes['test']->hasFlag('for_olap_workload'));
    }

    public function testListTableColumnsWithFixedStringTypeColumn()
    {
        $table = new Table('list_table_columns_char');
        $table->addColumn('id', 'integer', ['notnull' => true]);
        $table->addColumn('test', 'string', ['fixed' => true]);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('list_table_columns_char');

        self::assertArrayHasKey('test', $columns);
        self::assertTrue($columns['test']->getFixed());
    }
}
