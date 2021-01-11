<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function array_unique;

class SchemaDiffTest extends TestCase
{
    public function testSchemaDiffToSql(): void
    {
        $diff     = $this->createSchemaDiff();
        $platform = $this->createPlatform(true);

        $sql = $diff->toSql($platform);

        self::assertEquals([
            'create_schema',
            'drop_orphan_fk',
            'alter_seq',
            'drop_seq',
            'create_seq',
            'create_table',
            'create_foreign_key',
            'drop_table',
            'alter_table',
        ], $sql);

        $diff     = $this->createSchemaDiff2();
        $platform = new MySqlPlatform();

        $sql = $diff->toSql($platform);

        self::assertEquals($sql, array_unique($sql));
    }

    public function testSchemaDiffToSaveSql(): void
    {
        $diff     = $this->createSchemaDiff();
        $platform = $this->createPlatform(false);

        $sql = $diff->toSaveSql($platform);

        $expected = ['create_schema', 'alter_seq', 'create_seq', 'create_table', 'create_foreign_key', 'alter_table'];

        self::assertEquals($expected, $sql);
    }

    /**
     * @return AbstractPlatform&MockObject
     */
    private function createPlatform(bool $unsafe)
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects($this->exactly(1))
            ->method('getCreateSchemaSQL')
            ->with('foo_ns')
            ->will($this->returnValue('create_schema'));
        if ($unsafe) {
            $platform->expects($this->exactly(1))
                ->method('getDropSequenceSql')
                ->with($this->isInstanceOf(Sequence::class))
                ->will($this->returnValue('drop_seq'));
        }

        $platform->expects($this->exactly(1))
            ->method('getAlterSequenceSql')
            ->with($this->isInstanceOf(Sequence::class))
            ->will($this->returnValue('alter_seq'));
        $platform->expects($this->exactly(1))
            ->method('getCreateSequenceSql')
            ->with($this->isInstanceOf(Sequence::class))
            ->will($this->returnValue('create_seq'));
        if ($unsafe) {
            $platform->expects($this->exactly(1))
                ->method('getDropTableSql')
                ->with($this->isInstanceOf(Table::class))
                ->will($this->returnValue('drop_table'));
        }

        $platform->expects($this->exactly(1))
            ->method('getCreateTableSql')
            ->with($this->isInstanceOf(Table::class))
            ->will($this->returnValue(['create_table']));
        $platform->expects($this->exactly(1))
            ->method('getCreateForeignKeySQL')
            ->with($this->isInstanceOf(ForeignKeyConstraint::class))
            ->will($this->returnValue('create_foreign_key'));
        $platform->expects($this->exactly(1))
            ->method('getAlterTableSql')
            ->with($this->isInstanceOf(TableDiff::class))
            ->will($this->returnValue(['alter_table']));
        if ($unsafe) {
            $platform->expects($this->exactly(1))
                ->method('getDropForeignKeySql')
                ->with(
                    $this->isInstanceOf(ForeignKeyConstraint::class),
                    $this->isInstanceOf(Table::class)
                )
                ->will($this->returnValue('drop_orphan_fk'));
        }

        $platform->expects($this->exactly(1))
            ->method('supportsSchemas')
            ->will($this->returnValue(true));
        $platform->expects($this->exactly(1))
            ->method('supportsSequences')
            ->will($this->returnValue(true));
        $platform->expects($this->exactly(2))
            ->method('supportsForeignKeyConstraints')
            ->will($this->returnValue(true));

        return $platform;
    }

    public function createSchemaDiff(): SchemaDiff
    {
        $diff                              = new SchemaDiff();
        $diff->newNamespaces['foo_ns']     = 'foo_ns';
        $diff->removedNamespaces['bar_ns'] = 'bar_ns';
        $diff->changedSequences['foo_seq'] = new Sequence('foo_seq');
        $diff->newSequences['bar_seq']     = new Sequence('bar_seq');
        $diff->removedSequences['baz_seq'] = new Sequence('baz_seq');
        $diff->newTables['foo_table']      = new Table('foo_table');
        $diff->removedTables['bar_table']  = new Table('bar_table');
        $diff->changedTables['baz_table']  = new TableDiff('baz_table');
        $diff->newTables['foo_table']->addColumn('foreign_id', 'integer');
        $diff->newTables['foo_table']->addForeignKeyConstraint('foreign_table', ['foreign_id'], ['id']);
        $fk = new ForeignKeyConstraint(['id'], 'foreign_table', ['id']);
        $fk->setLocalTable(new Table('local_table'));
        $diff->orphanedForeignKeys[] = $fk;

        return $diff;
    }

    /**
     * Schema difference after a name change of a foreign table of
     * a unidirectional one-to-one relationship
     *
     * @throws Exception
     */
    public function createSchemaDiff2(): SchemaDiff
    {
        $diff     = new SchemaDiff();
        $oldTable = new Table('old_name_table');
        $oldTable->addColumn('id', 'integer');
        $tableToChange = new Table('changing_table');
        $tableToChange->addColumn('foreign_id', 'integer');
        $tableToChange->addForeignKeyConstraint('old_name_table', ['foreign_id'], ['id'], [], 'fk-to-update');
        $fromSchema       = new Schema([$oldTable, $tableToChange]);
        $diff->fromSchema = $fromSchema;
        $newTable         = new Table('new_name_table');
        $newTable->addColumn('id', 'integer');
        $diff->newTables['new_name_table'] = $newTable;
        $tableChange                       = new TableDiff('changing_table');
        $changedFK                         = new ForeignKeyConstraint(['foreign_id'], 'new_name_table', ['id'], 'fk-to-update');
        $changedFK->setLocalTable($tableToChange);
        $tableChange->changedForeignKeys[]     = $changedFK;
        $tableChange->fromTable                = $tableToChange;
        $diff->changedTables['changing_table'] = $tableChange;
        $diff->removedTables['old_name_table'] = new Table('old_name_table');
        $diff->orphanedForeignKeys[]           = $tableToChange->getForeignKey('fk-to-update');

        return $diff;
    }
}
