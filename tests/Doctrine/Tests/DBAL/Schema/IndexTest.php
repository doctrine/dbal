<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Index;

class IndexTest extends \PHPUnit\Framework\TestCase
{
    public function createIndex($unique = false, $primary = false, $options = array())
    {
        return new Index("foo", array("bar", "baz"), $unique, $primary, array(), $options);
    }

    public function testCreateIndex()
    {
        $idx = $this->createIndex();
        self::assertEquals("foo", $idx->getName());
        $columns = $idx->getColumns();
        self::assertEquals(2, count($columns));
        self::assertEquals(array("bar", "baz"), $columns);
        self::assertFalse($idx->isUnique());
        self::assertFalse($idx->isPrimary());
    }

    public function testCreatePrimary()
    {
        $idx = $this->createIndex(false, true);
        self::assertTrue($idx->isUnique());
        self::assertTrue($idx->isPrimary());
    }

    public function testCreateUnique()
    {
        $idx = $this->createIndex(true, false);
        self::assertTrue($idx->isUnique());
        self::assertFalse($idx->isPrimary());
    }

    /**
     * @group DBAL-50
     */
    public function testFulfilledByUnique()
    {
        $idx1 = $this->createIndex(true, false);
        $idx2 = $this->createIndex(true, false);
        $idx3 = $this->createIndex();

        self::assertTrue($idx1->isFullfilledBy($idx2));
        self::assertFalse($idx1->isFullfilledBy($idx3));
    }

    /**
     * @group DBAL-50
     */
    public function testFulfilledByPrimary()
    {
        $idx1 = $this->createIndex(true, true);
        $idx2 = $this->createIndex(true, true);
        $idx3 = $this->createIndex(true, false);

        self::assertTrue($idx1->isFullfilledBy($idx2));
        self::assertFalse($idx1->isFullfilledBy($idx3));
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

        self::assertTrue($idx1->isFullfilledBy($idx2));
        self::assertTrue($idx1->isFullfilledBy($pri));
        self::assertTrue($idx1->isFullfilledBy($uniq));
    }

    public function testFulfilledWithPartial()
    {
        $without = new Index('without', array('col1', 'col2'), true, false, array(), array());
        $partial = new Index('partial', array('col1', 'col2'), true, false, array(), array('where' => 'col1 IS NULL'));
        $another = new Index('another', array('col1', 'col2'), true, false, array(), array('where' => 'col1 IS NULL'));

        self::assertFalse($partial->isFullfilledBy($without));
        self::assertFalse($without->isFullfilledBy($partial));

        self::assertTrue($partial->isFullfilledBy($partial));

        self::assertTrue($partial->isFullfilledBy($another));
        self::assertTrue($another->isFullfilledBy($partial));
    }

    public function testFulfilledWithCoveringCompositeIndex()
    {
        $coveredIndex = new Index('foo', array('col1', 'col2'), false, false, array(), array());
        $compositeCoveringIndex = new Index('bar', array('col1', 'col2', 'col3'), false, false, array(), array());
        $compositeNotCoveringIndex = new Index('baz', array('col1', 'col3', 'col2'), false, false, array(), array());

        self::assertTrue(
            $coveredIndex
                ->isFullfilledBy($compositeCoveringIndex),
            'The index should be covered by the composite index where all of its columns appear first, in the same order.'
        );

        self::assertFalse(
            $coveredIndex
                ->isFullfilledBy($compositeNotCoveringIndex),
            'The index should be not covered by the composite index where of its columns appear in a different order.'
        );
    }

    public function testUniquePartialNotFulfilledByUniqueComposite()
    {
        $uniquePartial = new Index('unique_idx_foo', array('col1', 'col2'), true, false, array(), array());
        $uniqueComposite = new Index('unique_idx_bar', array('col1', 'col2', 'col3'), true, false, array(), array());

        self::assertFalse($uniquePartial->isFullfilledBy($uniqueComposite));
    }

    public function testUniquePartialNotFulfilledByPrimaryComposite()
    {
        $uniquePartial = new Index('unique_idx_foo', array('col1', 'col2'), true, false, array(), array());
        $primaryComposite = new Index('unique_idx_bar', array('col1', 'col2', 'col3'), false, true, array(), array());

        self::assertFalse($uniquePartial->isFullfilledBy($primaryComposite));
    }

    public function testUniqueIndexFulfilledByPrimaryIndexWithSameColumns()
    {
        $uniqueComposite = new Index('unique_idx_foo', array('col1', 'col2'), true, false, array(), array());
        $primaryComposite = new Index('primary', array('col1', 'col2'), false, true, array(), array());

        self::assertTrue($uniqueComposite->isFullfilledBy($primaryComposite));
    }

    public function testUniqueIndexNotFulfilledByPrimaryIndexWithDifferentColumnOrder()
    {
        $uniqueComposite = new Index('unique_idx_foo', array('col1', 'col2'), true, false, array(), array());
        $primaryComposite = new Index('primary', array('col2', 'col1'), false, true, array(), array());

        self::assertFalse($uniqueComposite->isFullfilledBy($primaryComposite));
    }

    public function testOverrulesWithPartial()
    {
        $without = new Index('without', array('col1', 'col2'), true, false, array(), array());
        $partial = new Index('partial', array('col1', 'col2'), true, false, array(), array('where' => 'col1 IS NULL'));
        $another = new Index('another', array('col1', 'col2'), true, false, array(), array('where' => 'col1 IS NULL'));

        self::assertFalse($partial->overrules($without));
        self::assertFalse($without->overrules($partial));

        self::assertTrue($partial->overrules($partial));

        self::assertTrue($partial->overrules($another));
        self::assertTrue($another->overrules($partial));
    }

    /**
     * @group DBAL-220
     */
    public function testFlags()
    {
        $idx1 = $this->createIndex();
        self::assertFalse($idx1->hasFlag('clustered'));
        self::assertEmpty($idx1->getFlags());

        $idx1->addFlag('clustered');
        self::assertTrue($idx1->hasFlag('clustered'));
        self::assertTrue($idx1->hasFlag('CLUSTERED'));
        self::assertSame(array('clustered'), $idx1->getFlags());

        $idx1->removeFlag('clustered');
        self::assertFalse($idx1->hasFlag('clustered'));
        self::assertEmpty($idx1->getFlags());
    }

    /**
     * @group DBAL-285
     */
    public function testIndexQuotes()
    {
        $index = new Index("foo", array("`bar`", "`baz`"));

        self::assertTrue($index->spansColumns(array("bar", "baz")));
        self::assertTrue($index->hasColumnAtPosition("bar", 0));
        self::assertTrue($index->hasColumnAtPosition("baz", 1));

        self::assertFalse($index->hasColumnAtPosition("bar", 1));
        self::assertFalse($index->hasColumnAtPosition("baz", 0));
    }

    public function testOptions()
    {
        $idx1 = $this->createIndex();
        self::assertFalse($idx1->hasOption('where'));
        self::assertEmpty($idx1->getOptions());

        $idx2 = $this->createIndex(false, false, array('where' => 'name IS NULL'));
        self::assertTrue($idx2->hasOption('where'));
        self::assertTrue($idx2->hasOption('WHERE'));
        self::assertSame('name IS NULL', $idx2->getOption('where'));
        self::assertSame('name IS NULL', $idx2->getOption('WHERE'));
        self::assertSame(array('where' => 'name IS NULL'), $idx2->getOptions());
    }
}
