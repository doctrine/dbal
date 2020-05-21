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
    /**
     * @return AbstractPlatform|MockObject
     */
    private function getGenericPlatform()
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

        $platform->expects($this->exactly(1))
            ->method('getCreateTableSql')
            ->with($this->isInstanceOf(Table::class))
            ->will($this->returnValue(['create_table']));

        return $platform;
    }

    /**
     * @param bool $safe Whether the method should output only safe SQL code or not
     *
     * @return AbstractPlatform|MockObject
     */
    private function getForeignKeyConstraintsOnlyPlatform(bool $safe = true)
    {
        $platform = $this->getGenericPlatform();

        $platform->expects($this->any())
                 ->method('supportsCreateDropForeignKeyConstraints')
                 ->will($this->returnValue(false));

        $platform->expects($this->exactly(0))
                 ->method('getCreateForeignKeySQL')
                 ->with($this->isInstanceOf(ForeignKeyConstraint::class))
                 ->will($this->throwException(
                     new DBALException('This platform does not support alter foreign key, the table must be fully recreated using getAlterTableSQL.')
                 ));

        if (! $safe) {
            $platform->expects($this->exactly(0))
                        ->method('getDropForeignKeySql')
                        ->with(
                            $this->isInstanceOf(ForeignKeyConstraint::class),
                            $this->isInstanceOf(Table::class)
                        )
                        ->will($this->throwException(
                            new DBALException('This platform does not support alter foreign key, the table must be fully recreated using getAlterTableSQL.')
                        ));
        }

        return $platform;
    }

    /**
     * @param bool $safe Whether the method should output only safe SQL code or not
     *
     * @return AbstractPlatform|MockObject
     */
    private function getCreateDropForeignKeyConstraintsPlatform(bool $safe = true)
    {
        $platform = $this->getGenericPlatform();

        $platform->expects($this->any())
                 ->method('supportsCreateDropForeignKeyConstraints')
                 ->will($this->returnValue(true));

        $platform->expects($this->exactly(1))
                 ->method('getCreateForeignKeySQL')
                 ->with($this->isInstanceOf(ForeignKeyConstraint::class))
                 ->will($this->returnValue('create_foreign_key'));

        if (! $safe) {
            $platform->expects($this->exactly(1))
                     ->method('getDropForeignKeySql')
                     ->with(
                         $this->isInstanceOf(ForeignKeyConstraint::class),
                         $this->isInstanceOf(Table::class)
                     )
                     ->will($this->returnValue('drop_orphan_fk'));
        }

        return $platform;
    }

    /**
     * @return mixed[]
     */
    public function provider() : array
    {
        $diff = $this->createSchemaDiff();

        return [
            'supportsForeignKeyConstraintsOnly' => [
                ['create_table'],
                $diff->toSql($this->getForeignKeyConstraintsOnlyPlatform(false)),
            ],
            'supportsForeignKeyConstraintsOnlySaveSql' => [
                ['create_table'],
                $diff->toSaveSql($this->getForeignKeyConstraintsOnlyPlatform()),
            ],
            'supportsCreateDropForeignKeyConstraints' => [
                ['drop_orphan_fk', 'create_table', 'create_foreign_key'],
                $diff->toSql($this->getCreateDropForeignKeyConstraintsPlatform(false)),
            ],
            'supportsCreateDropForeignKeyConstraintsSaveSql' => [
                ['create_table', 'create_foreign_key'],
                $diff->toSaveSql($this->getCreateDropForeignKeyConstraintsPlatform()),
            ],
        ];
    }

    /**
     * @param string[] $expectedActions An array of actions to be taken on the database
     * @param string[] $sql             An array of actions gnerated by the SchemaDiff
     *
     * @dataProvider provider
     */
    public function testSchemaDiff(array $expectedActions, array $sql) : void
    {
        self::assertEquals($expectedActions, $sql);
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
