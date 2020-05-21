<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SqliteSchemaDiffTest extends TestCase
{
    public function testSchemaDiffToSql() : void
    {
        $diff     = $this->createSchemaDiff();
        $platform = $this->createPlatform(true);

        $sql = $diff->toSql($platform);

        $expected = ['create_table'];

        self::assertEquals($expected, $sql);
    }

    public function testSchemaDiffToSaveSql() : void
    {
        $diff     = $this->createSchemaDiff();
        $platform = $this->createPlatform(false);

        $sql = $diff->toSaveSql($platform);

        $expected = ['create_table'];

        self::assertEquals($expected, $sql);
    }

    /**
     * @return AbstractPlatform|MockObject
     */
    private function createPlatform(bool $unsafe)
    {
        $platform = $this->createMock(AbstractPlatform::class);

        $platform->expects($this->exactly(1))
                ->method('supportsSchemas')
                ->will($this->returnValue(false));

        $platform->expects($this->exactly(1))
                ->method('supportsSequences')
                ->will($this->returnValue(false));

        $platform->expects($this->exactly(0))
                ->method('supportsForeignKeyConstraints')
                ->will($this->returnValue(true));

        $platform->expects($this->any())
            ->method('supportsCreateDropForeignKeyConstraints')
            ->will($this->returnValue(false));

        $platform->expects($this->exactly(1))
            ->method('getCreateTableSql')
            ->with($this->isInstanceOf(Table::class))
            ->will($this->returnValue(['create_table']));

        $platform->expects($this->exactly(0))
                 ->method('getCreateForeignKeySQL')
                 ->with($this->isInstanceOf(ForeignKeyConstraint::class))
                 ->will($this->throwException(
                     new DBALException('Sqlite platform does not support alter foreign key, the table must be fully recreated using getAlterTableSQL.')
                 ));

        if ($unsafe) {
            $platform->expects($this->exactly(0))
                     ->method('getDropForeignKeySql')
                     ->with(
                         $this->isInstanceOf(ForeignKeyConstraint::class),
                         $this->isInstanceOf(Table::class)
                     )
                     ->will($this->throwException(
                         new DBALException('Sqlite platform does not support alter foreign key, the table must be fully recreated using getAlterTableSQL.')
                     ));
        }

        return $platform;
    }

    public function createSchemaDiff() : SchemaDiff
    {
        $diff = new SchemaDiff();

        $diff->newTables['foo_table'] = new Table('foo_table');
        $diff->newTables['foo_table']->addColumn('foreign_id', 'integer');
        $diff->newTables['foo_table']->addForeignKeyConstraint('foreign_table', ['foreign_id'], ['id']);

        $fk = new ForeignKeyConstraint(['id'], 'foreign_table', ['id']);
        $fk->setLocalTable(new Table('local_table'));

        $diff->orphanedForeignKeys[] = $fk;

        return $diff;
    }
}
