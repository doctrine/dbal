<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Sequence;

class SequenceTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var \Doctrine\DBAL\Schema\Sequence
     */
    protected $sequence;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->sequence = new Sequence('foo');
    }

    public function testConstructs()
    {
        $this->assertSame('foo', $this->sequence->getName());
        $this->assertSame(1, $this->sequence->getAllocationSize());
        $this->assertSame(1, $this->sequence->getInitialValue());
        $this->assertNull($this->sequence->getCacheSize());
    }

    /**
     * @dataProvider getInvalidCacheSizes
     * @expectedException \InvalidArgumentException
     */
    public function testThrowsExceptionOnConstructingWithInvalidCacheSize($cacheSize)
    {
        new Sequence('foo', 1, 1, $cacheSize);
    }

    /**
     * @dataProvider getValidAllocationSizes
     */
    public function testSetsAllocationSize($allocationSize, $expected)
    {
        $this->sequence->setAllocationSize($allocationSize);

        $this->assertSame($expected, $this->sequence->getAllocationSize());
    }

    /**
     * @dataProvider getValidCacheSizes
     */
    public function testSetsCacheSize($cacheSize, $expected)
    {
        $this->sequence->setCacheSize($cacheSize);

        $this->assertSame($expected, $this->sequence->getCacheSize());
    }

    /**
     * @dataProvider getInvalidCacheSizes
     * @expectedException \InvalidArgumentException
     */
    public function testThrowsExceptionOnSettingInvalidCacheSize($cacheSize)
    {
        $this->sequence->setCacheSize($cacheSize);
    }

    /**
     * @dataProvider getValidInitialValues
     */
    public function testSetsInitialValue($initialValue, $expected)
    {
        $this->sequence->setInitialValue($initialValue);

        $this->assertSame($expected, $this->sequence->getInitialValue());
    }

    /**
     * @dataProvider getEqualityComparisonData
     */
    public function testComparesForEquality(Sequence $fromSequence, Sequence $toSequence, $expected)
    {
        $this->assertSame($expected, $fromSequence->equals($toSequence));
    }

    public function testVisits()
    {
        /** @var \Doctrine\DBAL\Schema\Visitor\Visitor|\PHPUnit_Framework_MockObject_MockObject $visitor */
        $visitor = $this->getMock('Doctrine\DBAL\Schema\Visitor\Visitor');

        $visitor->expects($this->once())
            ->method('acceptSequence')
            ->with($this->sequence);

        $this->sequence->visit($visitor);
    }

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

    public function getEqualityComparisonData()
    {
        return array(
            array(new Sequence('foo'), new Sequence('foo'), true),
            array(new Sequence('foo'), new Sequence('bar'), true),
            array(new Sequence('bar'), new Sequence('foo'), true),
            array(new Sequence('foo', 5), new Sequence('foo', '5'), true),
            array(new Sequence('foo', 5), new Sequence('foo', 10), false),
            array(new Sequence('foo', 5, 5), new Sequence('foo', 5, '5'), true),
            array(new Sequence('foo', 5, 5), new Sequence('foo', 5, 10), false),
            array(new Sequence('foo', 5, 5, 0), new Sequence('foo', 5, 5, 0), true),
            array(new Sequence('foo', 5, 5, '0'), new Sequence('foo', 5, 5, 0), true),
            array(new Sequence('foo', 5, 5, 0), new Sequence('foo', 5, 5, '0'), true),
            array(new Sequence('foo', 5, 5, '666'), new Sequence('foo', 5, 5, 666), true),
            array(new Sequence('foo', 5, 5, 666), new Sequence('foo', 5, 5, '666'), true),
            array(new Sequence('foo', 5, 5, null), new Sequence('foo', 5, 5, 0), false),
            array(new Sequence('foo', 5, 5, 0), new Sequence('foo', 5, 5, null), false),
            array(new Sequence('foo', 5, 5, null), new Sequence('foo', 5, 5, '0'), false),
            array(new Sequence('foo', 5, 5, '0'), new Sequence('foo', 5, 5, null), false),
            array(new Sequence('foo', 5, 5, 5), new Sequence('foo', 5, 5, 10), false),
        );
    }

    public function getInvalidCacheSizes()
    {
        return array(
            array(false),
            array(true),
            array(1.0),
            array('1.0'),
            array(1e4),
            array('1e4'),
            array('foo'),
            array(array()),
            array(array(666)),
            array(new \stdClass()),
            array(-1),
            array('-1')
        );
    }

    public function getValidAllocationSizes()
    {
        return array(
            array(666, 666),
            array('666', '666'),
            array(null, 1),
            array(false, 1),
            array(true, 1),
            array('foo', 1),
            array(array(), 1),
            array(array(666), 1),
            array(new \stdClass(), 1),
        );
    }

    public function getValidCacheSizes()
    {
        return array(
            array(0, 0),
            array('0', '0'),
            array(1, 1),
            array('1', '1'),
            array(666, 666),
            array('666', '666'),
            array(null, null),
        );
    }

    public function getValidInitialValues()
    {
        return array(
            array(666, 666),
            array('666', '666'),
            array(null, 1),
            array(false, 1),
            array(true, 1),
            array('foo', 1),
            array(array(), 1),
            array(array(666), 1),
            array(new \stdClass(), 1),
        );
    }
}
