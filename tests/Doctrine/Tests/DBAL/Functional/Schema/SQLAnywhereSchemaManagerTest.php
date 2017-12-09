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

        $name = "doctrine_test_view";
        $sql = "SELECT * from DBA.view_test_table";

        $view = new View($name, $sql);

        $this->_sm->dropAndCreateView($view);

        $views = $this->_sm->listViews();

        self::assertCount(1, $views, "Database has to have one view.");
        self::assertInstanceOf('Doctrine\DBAL\Schema\View', $views[$name]);
        self::assertEquals($name, $views[$name]->getName());
        self::assertEquals($sql, $views[$name]->getSql());
    }

    public function testDropAndCreateAdvancedIndex()
    {
        $table = $this->getTestTable('test_create_advanced_index');
        $this->_sm->dropAndCreateTable($table);
        $this->_sm->dropAndCreateIndex(
            new Index('test', array('test'), true, false, array('clustered', 'with_nulls_not_distinct', 'for_olap_workload')),
            $table->getName()
        );

        $tableIndexes = $this->_sm->listTableIndexes('test_create_advanced_index');
        self::assertInternalType('array', $tableIndexes);
        self::assertEquals('test', $tableIndexes['test']->getName());
        self::assertEquals(array('test'), $tableIndexes['test']->getColumns());
        self::assertTrue($tableIndexes['test']->isUnique());
        self::assertFalse($tableIndexes['test']->isPrimary());
        self::assertTrue($tableIndexes['test']->hasFlag('clustered'));
        self::assertTrue($tableIndexes['test']->hasFlag('with_nulls_not_distinct'));
        self::assertTrue($tableIndexes['test']->hasFlag('for_olap_workload'));
    }

    public function testListTableColumnsWithFixedStringTypeColumn()
    {
        $table = new Table('list_table_columns_char');
        $table->addColumn('id', 'integer', array('notnull' => true));
        $table->addColumn('test', 'string', array('fixed' => true));
        $table->setPrimaryKey(array('id'));

        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns('list_table_columns_char');

        self::assertArrayHasKey('test', $columns);
        self::assertTrue($columns['test']->getFixed());
    }
}
