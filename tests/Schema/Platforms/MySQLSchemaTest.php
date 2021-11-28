<?php

namespace Doctrine\DBAL\Tests\Schema\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQL;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;

class MySQLSchemaTest extends TestCase
{
    /** @var AbstractPlatform */
    private $platform;

    protected function setUp(): void
    {
        $this->platform = new MySQLPlatform();
    }

    /**
     * @dataProvider comparatorProvider
     */
    public function testSwitchPrimaryKeyOrder(Comparator $comparator): void
    {
        $tableOld = new Table('test');
        $tableOld->addColumn('foo_id', 'integer');
        $tableOld->addColumn('bar_id', 'integer');
        $tableNew = clone $tableOld;

        $tableOld->setPrimaryKey(['foo_id', 'bar_id']);
        $tableNew->setPrimaryKey(['bar_id', 'foo_id']);

        $diff = $comparator->diffTable($tableOld, $tableNew);
        self::assertNotFalse($diff);

        $sql = $this->platform->getAlterTableSQL($diff);

        self::assertEquals(
            [
                'ALTER TABLE test DROP PRIMARY KEY',
                'ALTER TABLE test ADD PRIMARY KEY (bar_id, foo_id)',
            ],
            $sql
        );
    }

    public function testGenerateForeignKeySQL(): void
    {
        $tableOld = new Table('test');
        $tableOld->addColumn('foo_id', 'integer');
        $tableOld->addForeignKeyConstraint('test_foreign', ['foo_id'], ['foo_id']);

        $sqls = [];
        foreach ($tableOld->getForeignKeys() as $fk) {
            $sqls[] = $this->platform->getCreateForeignKeySQL($fk, $tableOld);
        }

        self::assertEquals(
            [
                'ALTER TABLE test ADD CONSTRAINT FK_D87F7E0C8E48560F FOREIGN KEY (foo_id)'
                    . ' REFERENCES test_foreign (foo_id)',
            ],
            $sqls
        );
    }

    /**
     * @dataProvider comparatorProvider
     */
    public function testClobNoAlterTable(Comparator $comparator): void
    {
        $tableOld = new Table('test');
        $tableOld->addColumn('id', 'integer');
        $tableOld->addColumn('description', 'string', ['length' => 65536]);
        $tableNew = clone $tableOld;

        $tableNew->setPrimaryKey(['id']);

        $diff = $comparator->diffTable($tableOld, $tableNew);
        self::assertNotFalse($diff);

        $sql = $this->platform->getAlterTableSQL($diff);

        self::assertEquals(
            ['ALTER TABLE test ADD PRIMARY KEY (id)'],
            $sql
        );
    }

    /**
     * @return iterable<string,array{Comparator}>
     */
    public static function comparatorProvider(): iterable
    {
        yield 'Generic comparator' => [
            new Comparator(),
        ];

        yield 'MySQL comparator' => [
            new MySQL\Comparator(new MySQLPlatform()),
        ];
    }
}
