<?php

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;

class ComparatorMySQLTest extends ComparatorTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = new MySQLPlatform();
    }

    public function testCompareChangedBlobColumn(): void
    {
        $oldSchema = new Schema();

        $tableFoo = $oldSchema->createTable('foo');
        $tableFoo->addColumn('id', 'blob', ['length' => MySQLPlatform::LENGTH_LIMIT_BLOB]);

        $newSchema = new Schema();
        $table     = $newSchema->createTable('foo');
        $table->addColumn('id', 'blob', ['length' => MySQLPlatform::LENGTH_LIMIT_MEDIUMBLOB]);

        $expected             = new SchemaDiff();
        $expected->fromSchema = $oldSchema;

        $tableDiff            = $expected->changedTables['foo'] = new TableDiff('foo');
        $tableDiff->fromTable = $tableFoo;

        $columnDiff = $tableDiff->changedColumns['id'] = new ColumnDiff('id', $table->getColumn('id'));

        $columnDiff->fromColumn        = $tableFoo->getColumn('id');
        $columnDiff->changedProperties = ['length'];

        self::assertEquals($expected, Comparator::compareSchemas($oldSchema, $newSchema, $this->platform));
    }

    public function testCompareChangedTextColumn(): void
    {
        $oldSchema = new Schema();

        $tableFoo = $oldSchema->createTable('foo');
        $tableFoo->addColumn('id', 'text', ['length' => MySQLPlatform::LENGTH_LIMIT_TEXT]);

        $newSchema = new Schema();
        $table     = $newSchema->createTable('foo');
        $table->addColumn('id', 'text', ['length' => MySQLPlatform::LENGTH_LIMIT_MEDIUMTEXT]);

        $expected             = new SchemaDiff();
        $expected->fromSchema = $oldSchema;

        $tableDiff            = $expected->changedTables['foo'] = new TableDiff('foo');
        $tableDiff->fromTable = $tableFoo;

        $columnDiff = $tableDiff->changedColumns['id'] = new ColumnDiff('id', $table->getColumn('id'));

        $columnDiff->fromColumn        = $tableFoo->getColumn('id');
        $columnDiff->changedProperties = ['length'];

        self::assertEquals($expected, Comparator::compareSchemas($oldSchema, $newSchema, $this->platform));
    }

    public function testDiffColumnPlatformOptions(): void
    {
        $column1 = new Column('foo', Type::getType('string'), [
            'platformOptions' => ['charset' => 'utf8'],
        ]);

        $column2 = new Column('foo', Type::getType('string'), [
            'platformOptions' => ['collation' => 'BINARY'],
        ]);

        $column3 = new Column('foo', Type::getType('string'), [
            'platformOptions' => [
                'charset' => 'utf8',
                'collation' => 'BINARY',
            ],
        ]);

        $column4 = new Column('foo', Type::getType('string'));

        $comparator = new Comparator();

        $diff1 = $comparator->diffColumn($column1, $column2, $this->platform);
        $diff2 = $comparator->diffColumn($column2, $column1, $this->platform);

        self::assertContains('charset', $diff1);
        self::assertContains('charset', $diff2);
        self::assertContains('collation', $diff1);
        self::assertContains('collation', $diff2);

        self::assertEquals(['collation'], $comparator->diffColumn($column1, $column3, $this->platform));
        self::assertEquals(['collation'], $comparator->diffColumn($column3, $column1, $this->platform));
        self::assertEquals(['charset'], $comparator->diffColumn($column1, $column4, $this->platform));
        self::assertEquals(['charset'], $comparator->diffColumn($column4, $column1, $this->platform));
    }
}
