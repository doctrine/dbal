<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

class SequenceTest extends TestCase
{
    public function testIsAutoincrementFor(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        $sequence  = new Sequence('foo_id_seq');
        $sequence2 = new Sequence('bar_id_seq');
        $sequence3 = new Sequence('other.foo_id_seq');

        self::assertTrue($sequence->isAutoIncrementsFor($table));
        self::assertFalse($sequence2->isAutoIncrementsFor($table));
        self::assertFalse($sequence3->isAutoIncrementsFor($table));
    }

    public function testIsAutoincrementForCaseInsensitive(): void
    {
        $table = new Table('foo');
        $table->addColumn('ID', Types::INTEGER, ['autoincrement' => true]);
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
