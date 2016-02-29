<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;

class ForeignKeyConstraintTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group DBAL-1062
     *
     * @dataProvider getIntersectsIndexColumnsData
     */
    public function testIntersectsIndexColumns(array $indexColumns, $expectedResult)
    {
        $foreignKey = new ForeignKeyConstraint(array('foo', 'bar'), 'foreign_table', array('fk_foo', 'fk_bar'));

        $index = $this->getMockBuilder('Doctrine\DBAL\Schema\Index')
            ->disableOriginalConstructor()
            ->getMock();
        $index->expects($this->once())
            ->method('getColumns')
            ->will($this->returnValue($indexColumns));

        $this->assertSame($expectedResult, $foreignKey->intersectsIndexColumns($index));
    }

    /**
     * @return array
     */
    public function getIntersectsIndexColumnsData()
    {
        return array(
            array(array('baz'), false),
            array(array('baz', 'bloo'), false),

            array(array('foo'), true),
            array(array('bar'), true),

            array(array('foo', 'bar'), true),
            array(array('bar', 'foo'), true),

            array(array('foo', 'baz'), true),
            array(array('baz', 'foo'), true),

            array(array('bar', 'baz'), true),
            array(array('baz', 'bar'), true),

            array(array('foo', 'bloo', 'baz'), true),
            array(array('bloo', 'foo', 'baz'), true),
            array(array('bloo', 'baz', 'foo'), true),

            array(array('FOO'), true),
        );
    }
}
