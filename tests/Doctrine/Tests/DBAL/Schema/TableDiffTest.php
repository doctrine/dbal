<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use PHPUnit\Framework\TestCase;

class TableDiffTest extends TestCase
{
    /**
     * @group DBAL-1013
     */
    public function testReturnsName()
    {
        $tableDiff = new TableDiff('foo');

        self::assertEquals(new Identifier('foo'), $tableDiff->getName(new MockPlatform()));
    }

    /**
     * @group DBAL-1016
     */
    public function testPrefersNameFromTableObject()
    {
        $platformMock = new MockPlatform();
        $tableMock    = $this->getMockBuilder(Table::class)
            ->disableOriginalConstructor()
            ->getMock();

        $tableDiff            = new TableDiff('foo');
        $tableDiff->fromTable = $tableMock;

        $tableMock->expects($this->once())
            ->method('getQuotedName')
            ->with($platformMock)
            ->will($this->returnValue('foo'));

        self::assertEquals(new Identifier('foo'), $tableDiff->getName($platformMock));
    }

    /**
     * @group DBAL-1013
     */
    public function testReturnsNewName()
    {
        $tableDiff = new TableDiff('foo');

        self::assertFalse($tableDiff->getNewName());

        $tableDiff->newName = 'bar';

        self::assertEquals(new Identifier('bar'), $tableDiff->getNewName());
    }
}
