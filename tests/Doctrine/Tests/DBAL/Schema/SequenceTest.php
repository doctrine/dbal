<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalTestCase;

class SequenceTest extends DbalTestCase
{
    /**
     * @group DDC-1657
     */
    public function testIsAutoincrementFor()
    {
        $table = new Table('foo');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        $sequence  = new Sequence('foo_id_seq');
        $sequence2 = new Sequence('bar_id_seq');
        $sequence3 = new Sequence('other.foo_id_seq');

        self::assertTrue($sequence->isAutoIncrementsFor($table));
        self::assertFalse($sequence2->isAutoIncrementsFor($table));
        self::assertFalse($sequence3->isAutoIncrementsFor($table));
    }

    public function testIsAutoincrementForCaseInsensitive()
    {
        $table = new Table('foo');
        $table->addColumn('ID', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['ID']);

        $sequence  = new Sequence('foo_id_seq');
        $sequence1 = new Sequence('foo_ID_seq');
        $sequence2 = new Sequence('bar_id_seq');
        $sequence3 = new Sequence('bar_ID_seq');
        $sequence4 = new Sequence('other.foo_id_seq');

        self::assertTrue($sequence->isAutoIncrementsFor($table));
        self::assertTrue($sequence1->isAutoIncrementsFor($table));
        self::assertFalse($sequence2->isAutoIncrementsFor($table));
        self::assertFalse($sequence3->isAutoIncrementsFor($table));
        self::assertFalse($sequence4->isAutoIncrementsFor($table));
    }
}
