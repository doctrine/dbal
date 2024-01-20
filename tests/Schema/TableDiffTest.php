<?php

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TableDiffTest extends TestCase
{
    use VerifyDeprecations;

    /** @var AbstractPlatform&MockObject */
    private AbstractPlatform $platform;

    public function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testReturnsName(): void
    {
        $tableDiff = new TableDiff('foo');

        self::assertEquals(new Identifier('foo'), $tableDiff->getName($this->platform));
    }

    public function testRenamedColumnDeprecationLayer(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6080');

        /** @psalm-suppress InvalidArgument */
        $diff = new TableDiff(
            'foo',
            [],
            [
                new ColumnDiff(
                    'foo',
                    new Column('foo', Type::getType(Types::INTEGER)),
                    ['type'],
                    new Column('foo', Type::getType(Types::BIGINT)),
                ),
                new ColumnDiff(
                    'ba',
                    new Column('ba', Type::getType(Types::INTEGER)),
                    ['type'],
                    new Column('ba', Type::getType(Types::BIGINT)),
                ),
            ],
            [],
            [],
            [],
            [],
            new Table('foo'),
            [],
            [],
            [],
            [
                'foo' => new Column('baz', Type::getType(Types::INTEGER)),
                'bar' => new Column('renamed', Type::getType(Types::INTEGER)),
            ],
            [],
        );

        self::assertCount(3, $diff->getChangedColumns());
        self::assertCount(2, $diff->getModifiedColumns());
        self::assertEquals('foo', $diff->getChangedColumns()[0]->getOldColumnName()->getName());
        self::assertEquals('baz', $diff->getChangedColumns()[0]->getNewColumn()->getName());
        self::assertTrue($diff->getChangedColumns()[0]->hasTypeChanged());
        self::assertEquals(Type::getType(Types::INTEGER), $diff->getChangedColumns()[0]->getNewColumn()->getType());
        self::assertEquals('bar', $diff->getChangedColumns()[2]->getOldColumnName()->getName());
        self::assertEquals('renamed', $diff->getChangedColumns()[2]->getNewColumn()->getName());

        self::assertCount(2, $diff->renamedColumns);

        $diff->renamedColumns = ['old_name' => new Column('new_name', Type::getType(Types::INTEGER))];
        self::assertCount(4, $diff->getChangedColumns());
        self::assertCount(3, $diff->renamedColumns);

        // Test that __isset __set and __get have the default php behavior
        @$diff->foo = 'baz';
        self::assertTrue(isset($diff->renamedColumns));
        self::assertTrue(isset($diff->foo));
        self::assertEquals('baz', $diff->foo);
    }

    public function testPrefersNameFromTableObject(): void
    {
        $tableMock = $this->getMockBuilder(Table::class)
            ->disableOriginalConstructor()
            ->getMock();

        $tableDiff            = new TableDiff('foo');
        $tableDiff->fromTable = $tableMock;

        $tableMock->expects(self::once())
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

    public function testOmittingFromTableInConstructorIsDeprecated(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/5678');
        $tableDiff = new TableDiff('foo');
    }

    public function testPassingFromTableToConstructorIsDeprecated(): void
    {
        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/5678');
        $tableDiff = new TableDiff(
            'foo',
            [],
            [],
            [],
            [],
            [],
            [],
            new Table('foo'),
        );
    }
}
