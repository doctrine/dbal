<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TableDiffTest extends TestCase
{
    /** @var AbstractPlatform&MockObject */
    private $platform;

    public function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testReturnsName(): void
    {
        $tableDiff = new TableDiff('foo');

        self::assertEquals(new Identifier('foo'), $tableDiff->getName($this->platform));
    }

    public function testPrefersNameFromTableObject(): void
    {
        $tableMock = $this->getMockBuilder(Table::class)
            ->disableOriginalConstructor()
            ->getMock();

        $tableDiff            = new TableDiff('foo');
        $tableDiff->fromTable = $tableMock;

        $tableMock->expects($this->once())
            ->method('getQuotedName')
            ->with($this->platform)
            ->willReturn('foo');

        self::assertEquals(new Identifier('foo'), $tableDiff->getName($this->platform));
    }

    public function testReturnsNewName(): void
    {
        $tableDiff = new TableDiff('foo');

        self::assertFalse($tableDiff->getNewName());

        $tableDiff->newName = 'bar';

        self::assertEquals(new Identifier('bar'), $tableDiff->getNewName());
    }
}
