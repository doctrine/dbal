<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;

class SequenceTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @group DDC-1657
     */
    public function testIsAutoincrementFor()
    {
        $table = new Table("foo");
        $table->addColumn("id", "integer", array("autoincrement" => true));
        $table->setPrimaryKey(array("id"));

        $sequence = new Sequence("foo_id_seq");
        $sequence2 = new Sequence("bar_id_seq");
        $sequence3 = new Sequence("other.foo_id_seq");

        $this->assertTrue($sequence->isAutoIncrementsFor($table));
        $this->assertFalse($sequence2->isAutoIncrementsFor($table));
        $this->assertFalse($sequence3->isAutoIncrementsFor($table));
    }
}

