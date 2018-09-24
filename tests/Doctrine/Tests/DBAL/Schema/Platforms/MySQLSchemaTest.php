<?php

namespace Doctrine\Tests\DBAL\Schema\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;

class MySQLSchemaTest extends TestCase
{
    /** @var Comparator */
    private $comparator;

    /** @var AbstractPlatform */
    private $platform;

    protected function setUp()
    {
        $this->comparator = new Comparator();
        $this->platform   = new MySqlPlatform();
    }

    public function testSwitchPrimaryKeyOrder()
    {
        $tableOld = new Table('test');
        $tableOld->addColumn('foo_id', 'integer');
        $tableOld->addColumn('bar_id', 'integer');
        $tableNew = clone $tableOld;

        $tableOld->setPrimaryKey(['foo_id', 'bar_id']);
        $tableNew->setPrimaryKey(['bar_id', 'foo_id']);

        $diff = $this->comparator->diffTable($tableOld, $tableNew);
        $sql  = $this->platform->getAlterTableSQL($diff);

        self::assertEquals(
            [
                'ALTER TABLE test DROP PRIMARY KEY',
                'ALTER TABLE test ADD PRIMARY KEY (bar_id, foo_id)',
            ],
            $sql
        );
    }

    /**
     * @group DBAL-132
     */
    public function testGenerateForeignKeySQL()
    {
        $tableOld = new Table('test');
        $tableOld->addColumn('foo_id', 'integer');
        $tableOld->addUnnamedForeignKeyConstraint('test_foreign', ['foo_id'], ['foo_id']);

        $sqls = [];
        foreach ($tableOld->getForeignKeys() as $fk) {
            $sqls[] = $this->platform->getCreateForeignKeySQL($fk, $tableOld);
        }

        self::assertEquals(['ALTER TABLE test ADD CONSTRAINT FK_D87F7E0C8E48560F FOREIGN KEY (foo_id) REFERENCES test_foreign (foo_id)'], $sqls);
    }

    /**
     * @group DDC-1737
     */
    public function testClobNoAlterTable()
    {
        $tableOld = new Table('test');
        $tableOld->addColumn('id', 'integer');
        $tableOld->addColumn('description', 'string', ['length' => 65536]);
        $tableNew = clone $tableOld;

        $tableNew->setPrimaryKey(['id']);

        $diff = $this->comparator->diffTable($tableOld, $tableNew);
        $sql  = $this->platform->getAlterTableSQL($diff);

        self::assertEquals(
            ['ALTER TABLE test ADD PRIMARY KEY (id)'],
            $sql
        );
    }
}
