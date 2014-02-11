<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\Table;

require_once __DIR__ . '/../../../TestInit.php';

class DrizzleSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    public function testListTableWithBinary()
    {
        $tableName = 'test_binary_table';

        $table = new Table($tableName);
        $table->addColumn('id', 'integer');
        $table->addColumn('column_varbinary', 'binary', array());
        $table->addColumn('column_binary', 'binary', array('fixed' => true));
        $table->setPrimaryKey(array('id'));

        $this->_sm->createTable($table);

        $table = $this->_sm->listTableDetails($tableName);

        $this->assertInstanceOf('Doctrine\DBAL\Types\BinaryType', $table->getColumn('column_varbinary')->getType());
        $this->assertFalse($table->getColumn('column_varbinary')->getFixed());

        $this->assertInstanceOf('Doctrine\DBAL\Types\BinaryType', $table->getColumn('column_binary')->getType());
        $this->assertFalse($table->getColumn('column_binary')->getFixed());
    }

    public function testColumnCollation()
    {
        $table = new Table('test_collation');
        $table->addOption('collate', $collation = 'utf8_unicode_ci');
        $table->addColumn('id', 'integer');
        $table->addColumn('text', 'text');
        $table->addColumn('foo', 'text')->setPlatformOption('collation', 'utf8_swedish_ci');
        $table->addColumn('bar', 'text')->setPlatformOption('collation', 'utf8_general_ci');
        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns('test_collation');

        $this->assertArrayNotHasKey('collation', $columns['id']->getPlatformOptions());
        $this->assertEquals('utf8_unicode_ci', $columns['text']->getPlatformOption('collation'));
        $this->assertEquals('utf8_swedish_ci', $columns['foo']->getPlatformOption('collation'));
        $this->assertEquals('utf8_general_ci', $columns['bar']->getPlatformOption('collation'));
    }
}
