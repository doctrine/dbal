<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
            ->willReturn('create_schema');
        if ($unsafe) {
            $platform->expects($this->exactly(1))
                 ->method('getDropSequenceSql')
                 ->with($this->isInstanceOf(Sequence::class))
                 ->willReturn('drop_seq');
        }

        $platform->expects($this->exactly(1))
                 ->method('getAlterSequenceSql')
                 ->with($this->isInstanceOf(Sequence::class))
                 ->willReturn('alter_seq');
        $platform->expects($this->exactly(1))
                 ->method('getCreateSequenceSql')
                 ->with($this->isInstanceOf(Sequence::class))
                 ->willReturn('create_seq');
        if ($unsafe) {
            $platform->expects($this->exactly(1))
                     ->method('getDropTableSql')
                     ->with($this->isInstanceOf(Table::class))
                     ->willReturn('drop_table');
        }

        $platform->expects($this->exactly(1))
                 ->method('getCreateTableSql')
                 ->with($this->isInstanceOf(Table::class))
                 ->willReturn(['create_table']);
        $platform->expects($this->exactly(1))
                 ->method('getCreateForeignKeySQL')
                 ->with($this->isInstanceOf(ForeignKeyConstraint::class))
                 ->willReturn('create_foreign_key');
        $platform->expects($this->exactly(1))
                 ->method('getAlterTableSql')
                 ->with($this->isInstanceOf(TableDiff::class))
                 ->willReturn(['alter_table']);
        if ($unsafe) {
            $platform->expects($this->exactly(1))
                     ->method('getDropForeignKeySql')
                     ->with(
                         $this->isInstanceOf(ForeignKeyConstraint::class),
                         $this->isInstanceOf(Table::class)
                     )
                     ->willReturn('drop_orphan_fk');
        }

        $platform->expects($this->exactly(1))
                ->method('supportsSchemas')
                ->willReturn(true);
        $platform->expects($this->exactly(1))
                ->method('supportsSequences')
                ->willReturn(true);
        $platform->expects($this->exactly(2))
                ->method('supportsForeignKeyConstraints')
                ->willReturn(true);

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
}
