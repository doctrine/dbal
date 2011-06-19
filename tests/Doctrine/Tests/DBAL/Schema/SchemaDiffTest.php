<?php

namespace Doctrine\Tests\DBAL\Schema;

require_once __DIR__ . '/../../TestInit.php';

use Doctrine\DBAL\Schema\Schema,
    Doctrine\DBAL\Schema\Table,
    Doctrine\DBAL\Schema\Column,
    Doctrine\DBAL\Schema\Index,
    Doctrine\DBAL\Schema\Sequence,
    Doctrine\DBAL\Schema\SchemaDiff,
    Doctrine\DBAL\Schema\TableDiff,
    Doctrine\DBAL\Schema\Comparator,
    Doctrine\DBAL\Types\Type;

class SchemaDiffTest extends \PHPUnit_Framework_TestCase
{
    public function testSchemaDiffToSql()
    {
        $diff = $this->createSchemaDiff();
        $platform = $this->createPlatform(true);

        $sql = $diff->toSql($platform);

        $expected = array('drop_orphan_fk', 'alter_seq', 'drop_seq', 'create_seq', 'create_table', 'create_foreign_key', 'drop_table', 'alter_table');

        $this->assertEquals($expected, $sql);
    }

    public function testSchemaDiffToSaveSql()
    {
        $diff = $this->createSchemaDiff();
        $platform = $this->createPlatform(false);

        $sql = $diff->toSaveSql($platform);

        $expected = array('alter_seq', 'create_seq', 'create_table', 'create_foreign_key', 'alter_table');

        $this->assertEquals($expected, $sql);
    }

    public function createPlatform($unsafe = false)
    {
        $platform = $this->getMock('Doctrine\Tests\DBAL\Mocks\MockPlatform');
        if ($unsafe) {
            $platform->expects($this->exactly(1))
                 ->method('getDropSequenceSql')
                 ->with($this->isInstanceOf('Doctrine\DBAL\Schema\Sequence'))
                 ->will($this->returnValue('drop_seq'));
        }
        $platform->expects($this->exactly(1))
                 ->method('getAlterSequenceSql')
                 ->with($this->isInstanceOf('Doctrine\DBAL\Schema\Sequence'))
                 ->will($this->returnValue('alter_seq'));
        $platform->expects($this->exactly(1))
                 ->method('getCreateSequenceSql')
                 ->with($this->isInstanceOf('Doctrine\DBAL\Schema\Sequence'))
                 ->will($this->returnValue('create_seq'));
        if ($unsafe) {
            $platform->expects($this->exactly(1))
                     ->method('getDropTableSql')
                     ->with($this->isInstanceof('Doctrine\DBAL\Schema\Table'))
                     ->will($this->returnValue('drop_table'));
        }
        $platform->expects($this->exactly(1))
                 ->method('getCreateTableSql')
                 ->with($this->isInstanceof('Doctrine\DBAL\Schema\Table'))
                 ->will($this->returnValue(array('create_table')));
        $platform->expects($this->exactly(1))
                 ->method('getCreateForeignKeySQL')
                 ->with($this->isInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint'))
                 ->will($this->returnValue('create_foreign_key'));
        $platform->expects($this->exactly(1))
                 ->method('getAlterTableSql')
                 ->with($this->isInstanceOf('Doctrine\DBAL\Schema\TableDiff'))
                 ->will($this->returnValue(array('alter_table')));
        if ($unsafe) {
            $platform->expects($this->exactly(1))
                     ->method('getDropForeignKeySql')
                     ->with($this->isInstanceof('Doctrine\DBAL\Schema\ForeignKeyConstraint'), $this->equalTo('local_table'))
                     ->will($this->returnValue('drop_orphan_fk'));
        }
        $platform->expects($this->exactly(1))
                ->method('supportsSequences')
                ->will($this->returnValue(true));
        $platform->expects($this->exactly(2))
                ->method('supportsForeignKeyConstraints')
                ->will($this->returnValue(true));
        return $platform;
    }

    public function createSchemaDiff()
    {
        $diff = new SchemaDiff();
        $diff->changedSequences['foo_seq'] = new Sequence('foo_seq');
        $diff->newSequences['bar_seq'] = new Sequence('bar_seq');
        $diff->removedSequences['baz_seq'] = new Sequence('baz_seq');
        $diff->newTables['foo_table'] = new Table('foo_table');
        $diff->removedTables['bar_table'] = new Table('bar_table');
        $diff->changedTables['baz_table'] = new TableDiff('baz_table');
        $diff->newTables['foo_table']->addColumn('foreign_id', 'integer');
        $diff->newTables['foo_table']->addForeignKeyConstraint('foreign_table', array('foreign_id'), array('id'));
        $fk = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(array('id'), 'foreign_table', array('id'));
        $fk->setLocalTable(new Table('local_table'));
        $diff->orphanedForeignKeys[] = $fk;
        return $diff;
    }
}
