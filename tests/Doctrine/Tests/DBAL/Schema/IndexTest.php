<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Index;

class IndexTest extends \PHPUnit_Framework_TestCase
{
    public function createIndex($unique = false, $primary = false, $options = array())
    {
        return new Index("foo", array("bar", "baz"), $unique, $primary, array(), $options);
    }

    public function testCreateIndex()
    {
        $idx = $this->createIndex();
        $this->assertEquals("foo", $idx->getName());
        $columns = $idx->getColumns();
        $this->assertEquals(2, count($columns));
        $this->assertEquals(array("bar", "baz"), $columns);
        $this->assertFalse($idx->isUnique());
        $this->assertFalse($idx->isPrimary());
    }

    public function testCreatePrimary()
    {
        $idx = $this->createIndex(false, true);
        $this->assertTrue($idx->isUnique());
        $this->assertTrue($idx->isPrimary());
    }

    public function testCreateUnique()
    {
        $idx = $this->createIndex(true, false);
        $this->assertTrue($idx->isUnique());
        $this->assertFalse($idx->isPrimary());
    }

    /**
     * @group DBAL-50
     */
    public function testFulfilledByUnique()
    {
        $idx1 = $this->createIndex(true, false);
        $idx2 = $this->createIndex(true, false);
        $idx3 = $this->createIndex();

        $this->assertTrue($idx1->isFullfilledBy($idx2));
        $this->assertFalse($idx1->isFullfilledBy($idx3));
    }

    /**
     * @group DBAL-50
     */
    public function testFulfilledByPrimary()
    {
        $idx1 = $this->createIndex(true, true);
        $idx2 = $this->createIndex(true, true);
        $idx3 = $this->createIndex(true, false);

        $this->assertTrue($idx1->isFullfilledBy($idx2));
        $this->assertFalse($idx1->isFullfilledBy($idx3));
    }

    /**
     * @group DBAL-50
     */
    public function testFulfilledByIndex()
    {
        $idx1 = $this->createIndex();
        $idx2 = $this->createIndex();
        $pri = $this->createIndex(true, true);
        $uniq = $this->createIndex(true);

        $this->assertTrue($idx1->isFullfilledBy($idx2));
        $this->assertTrue($idx1->isFullfilledBy($pri));
        $this->assertTrue($idx1->isFullfilledBy($uniq));
    }

    public function testFulfilledWithPartial()
    {
        $without = new Index('without', array('col1', 'col2'), true, false, array(), array());
        $partial = new Index('partial', array('col1', 'col2'), true, false, array(), array('where' => 'col1 IS NULL'));
        $another = new Index('another', array('col1', 'col2'), true, false, array(), array('where' => 'col1 IS NULL'));

        $this->assertFalse($partial->isFullfilledBy($without));
        $this->assertFalse($without->isFullfilledBy($partial));

        $this->assertTrue($partial->isFullfilledBy($partial));

        $this->assertTrue($partial->isFullfilledBy($another));
        $this->assertTrue($another->isFullfilledBy($partial));
    }

    public function testOverrulesWithPartial()
    {
        $without = new Index('without', array('col1', 'col2'), true, false, array(), array());
        $partial = new Index('partial', array('col1', 'col2'), true, false, array(), array('where' => 'col1 IS NULL'));
        $another = new Index('another', array('col1', 'col2'), true, false, array(), array('where' => 'col1 IS NULL'));

        $this->assertFalse($partial->overrules($without));
        $this->assertFalse($without->overrules($partial));

        $this->assertTrue($partial->overrules($partial));

        $this->assertTrue($partial->overrules($another));
        $this->assertTrue($another->overrules($partial));
    }

    /**
     * @group DBAL-220
     */
    public function testFlags()
    {
        $idx1 = $this->createIndex();
        $this->assertFalse($idx1->hasFlag('clustered'));
        $this->assertEmpty($idx1->getFlags());

        $idx1->addFlag('clustered');
        $this->assertTrue($idx1->hasFlag('clustered'));
        $this->assertTrue($idx1->hasFlag('CLUSTERED'));
        $this->assertSame(array('clustered'), $idx1->getFlags());

        $idx1->removeFlag('clustered');
        $this->assertFalse($idx1->hasFlag('clustered'));
        $this->assertEmpty($idx1->getFlags());
    }

    /**
     * @group DBAL-285
     */
    public function testIndexQuotes()
    {
        $index = new Index("foo", array("`bar`", "`baz`"));

        $this->assertTrue($index->spansColumns(array("bar", "baz")));
        $this->assertTrue($index->hasColumnAtPosition("bar", 0));
        $this->assertTrue($index->hasColumnAtPosition("baz", 1));

        $this->assertFalse($index->hasColumnAtPosition("bar", 1));
        $this->assertFalse($index->hasColumnAtPosition("baz", 0));
    }

    public function testOptions()
    {
        $idx1 = $this->createIndex();
        $this->assertFalse($idx1->hasOption('where'));
        $this->assertEmpty($idx1->getOptions());

        $idx2 = $this->createIndex(false, false, array('where' => 'name IS NULL'));
        $this->assertTrue($idx2->hasOption('where'));
        $this->assertTrue($idx2->hasOption('WHERE'));
        $this->assertSame('name IS NULL', $idx2->getOption('where'));
        $this->assertSame('name IS NULL', $idx2->getOption('WHERE'));
        $this->assertSame(array('where' => 'name IS NULL'), $idx2->getOptions());
    }
}
