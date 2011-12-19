<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Schema;

require_once __DIR__ . '/../../../TestInit.php';

class MySqlSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    public function testSwitchPrimaryKeyColumns()
    {
        $tableOld = new Table("switch_primary_key_columns");
        $tableOld->addColumn('foo_id', 'integer');
        $tableOld->addColumn('bar_id', 'integer');
        $tableNew = clone $tableOld;

        $this->_sm->createTable($tableOld);
        $tableFetched = $this->_sm->listTableDetails("switch_primary_key_columns");
        $tableNew = clone $tableFetched;
        $tableNew->setPrimaryKey(array('bar_id', 'foo_id'));

        $comparator = new \Doctrine\DBAL\Schema\Comparator;
        $this->_sm->alterTable($comparator->diffTable($tableFetched, $tableNew));
    }

    public function testDiffTableBug()
    {
        $schema = new Schema();
        $table = $schema->createTable('diffbug_routing_translations');
        $table->addColumn('id', 'integer');
        $table->addColumn('route', 'string');
        $table->addColumn('locale', 'string');
        $table->addColumn('attribute', 'string');
        $table->addColumn('localized_value', 'string');
        $table->addColumn('original_value', 'string');
        $table->setPrimaryKey(array('id'));
        $table->addUniqueIndex(array('route', 'locale', 'attribute'));
        $table->addIndex(array('localized_value')); // this is much more selective than the unique index

        $this->_sm->createTable($table);
        $tableFetched = $this->_sm->listTableDetails("diffbug_routing_translations");

        $comparator = new \Doctrine\DBAL\Schema\Comparator;
        $diff = $comparator->diffTable($tableFetched, $table);

        $this->assertFalse($diff, "no changes expected.");
    }
}