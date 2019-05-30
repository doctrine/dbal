<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use PHPUnit\Framework\TestCase;

class ForeignKeyConstraintTest extends TestCase
{
    /**
     * @param string[] $indexColumns
     *
     * @group DBAL-1062
     * @dataProvider getIntersectsIndexColumnsData
     */
    public function testIntersectsIndexColumns(array $indexColumns, bool $expectedResult) : void
    {
        $foreignKey = new ForeignKeyConstraint(['foo', 'bar'], 'foreign_table', ['fk_foo', 'fk_bar']);

        $index = $this->getMockBuilder(Index::class)
            ->disableOriginalConstructor()
            ->getMock();
        $index->expects($this->once())
            ->method('getColumns')
            ->will($this->returnValue($indexColumns));

        self::assertSame($expectedResult, $foreignKey->intersectsIndexColumns($index));
    }

    /**
     * @return mixed[][]
     */
    public static function getIntersectsIndexColumnsData() : iterable
    {
        return [
            [['baz'], false],
            [['baz', 'bloo'], false],

            [['foo'], true],
            [['bar'], true],

            [['foo', 'bar'], true],
            [['bar', 'foo'], true],

            [['foo', 'baz'], true],
            [['baz', 'foo'], true],

            [['bar', 'baz'], true],
            [['baz', 'bar'], true],

            [['foo', 'bloo', 'baz'], true],
            [['bloo', 'foo', 'baz'], true],
            [['bloo', 'baz', 'foo'], true],

            [['FOO'], true],
        ];
    }
}
