<?php

namespace Doctrine\DBAL\Tests\Schema\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQL;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
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

    public function testPrimaryKeyLengthAutoFix(): void
    {
        $schema= new Schema();
        $tableOld = $schema->createTable('test');

        $tableOld->addColumn('bar_integer', 'integer');
        $tableOld->addColumn('foo_text', 'text');
        $tableOld->addColumn('foo_blob', 'blob');

        $tableOld->setPrimaryKey(['bar_integer', 'foo_text', 'foo_blob']);

        $sql = $schema->toSql($this->platform);

        self::assertEquals(
            ['CREATE TABLE test (bar_integer INT NOT NULL, foo_text LONGTEXT NOT NULL, foo_blob LONGBLOB NOT NULL, PRIMARY KEY(bar_integer, foo_text(255), foo_blob(255))) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB'],
            $sql
        );
    }

    public function testPrimaryKeyLengthOptions(): void
    {
        $schema= new Schema();
        $tableOld = $schema->createTable('test');

        $tableOld->addColumn('bar_integer', 'integer');
        $tableOld->addColumn('foo_text', 'text');
        $tableOld->addColumn('foo_blob', 'blob');

        $tableOld->setPrimaryKey(['bar_integer', 'foo_text', 'foo_blob'],false,["lengths"=>[null,200,210]]);

        $sql = $schema->toSql($this->platform);

        self::assertEquals(
            ['CREATE TABLE test (bar_integer INT NOT NULL, foo_text LONGTEXT NOT NULL, foo_blob LONGBLOB NOT NULL, PRIMARY KEY(bar_integer, foo_text(200), foo_blob(210))) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB'],
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
