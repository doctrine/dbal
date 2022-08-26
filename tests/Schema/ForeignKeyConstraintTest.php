<?php

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;

class ForeignKeyConstraintTest extends TestCase
{
    /**
     * @param string[] $indexColumns
     *
     * @dataProvider getIntersectsIndexColumnsData
     */
    public function testIntersectsIndexColumns(array $indexColumns, bool $expectedResult): void
    {
        $foreignKey = new ForeignKeyConstraint(['foo', 'bar'], 'foreign_table', ['fk_foo', 'fk_bar']);

        $index = $this->getMockBuilder(Index::class)
            ->disableOriginalConstructor()
            ->getMock();
        $index->expects(self::once())
            ->method('getColumns')
            ->willReturn($indexColumns);

        self::assertSame($expectedResult, $foreignKey->intersectsIndexColumns($index));
    }

    /** @return mixed[][] */
    public static function getIntersectsIndexColumnsData(): iterable
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

    /**
     * @param string|Table $foreignTableName
     *
     * @dataProvider getUnqualifiedForeignTableNameData
     */
    public function testGetUnqualifiedForeignTableName($foreignTableName, string $expectedUnqualifiedTableName): void
    {
        $foreignKey = new ForeignKeyConstraint(['foo', 'bar'], $foreignTableName, ['fk_foo', 'fk_bar']);

        self::assertSame($expectedUnqualifiedTableName, $foreignKey->getUnqualifiedForeignTableName());
    }

    /** @return mixed[][] */
    public static function getUnqualifiedForeignTableNameData(): iterable
    {
        return [
            ['schema.foreign_table', 'foreign_table'],
            ['foreign_table', 'foreign_table'],
            [new Table('schema.foreign_table'), 'foreign_table'],
            [new Table('foreign_table'), 'foreign_table'],
        ];
    }
}
