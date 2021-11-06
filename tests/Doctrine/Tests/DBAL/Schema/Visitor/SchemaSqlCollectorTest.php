<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Tests\DBAL\MockBuilderProxy;
use PHPUnit\Framework\TestCase;

class SchemaSqlCollectorTest extends TestCase
{
    public function testCreateSchema(): void
    {
        $platformMock = (new MockBuilderProxy($this->getMockBuilder(MySqlPlatform::class)))
            ->onlyMethods(['getCreateTableSql', 'getCreateSequenceSql', 'getCreateForeignKeySql'])
            ->getMock();
        $platformMock->expects($this->exactly(2))
                     ->method('getCreateTableSql')
                     ->willReturn(['foo']);
        $platformMock->expects($this->exactly(1))
                     ->method('getCreateSequenceSql')
                     ->willReturn('bar');
        $platformMock->expects($this->exactly(1))
                     ->method('getCreateForeignKeySql')
                     ->willReturn('baz');

        $schema = $this->createFixtureSchema();

        $sql = $schema->toSql($platformMock);

        self::assertEquals(['foo', 'foo', 'bar', 'baz'], $sql);
    }

    public function testDropSchema(): void
    {
        $platformMock = (new MockBuilderProxy($this->getMockBuilder(MySqlPlatform::class)))
            ->onlyMethods(['getDropTableSql', 'getDropSequenceSql', 'getDropForeignKeySql'])
            ->getMock();
        $platformMock->expects($this->exactly(2))
                     ->method('getDropTableSql')
                     ->willReturn('tbl');
        $platformMock->expects($this->exactly(1))
                     ->method('getDropSequenceSql')
                     ->willReturn('seq');
        $platformMock->expects($this->exactly(1))
                     ->method('getDropForeignKeySql')
                     ->willReturn('fk');

        $schema = $this->createFixtureSchema();

        $sql = $schema->toDropSql($platformMock);

        self::assertEquals(['fk', 'seq', 'tbl', 'tbl'], $sql);
    }

    public function createFixtureSchema(): Schema
    {
        $schema = new Schema();
        $tableA = $schema->createTable('foo');
        $tableA->addColumn('id', 'integer');
        $tableA->addColumn('bar', 'string', ['length' => 255]);
        $tableA->setPrimaryKey(['id']);

        $schema->createSequence('foo_seq');

        $tableB = $schema->createTable('bar');
        $tableB->addColumn('id', 'integer');
        $tableB->setPrimaryKey(['id']);

        $tableA->addForeignKeyConstraint($tableB, ['bar'], ['id']);

        return $schema;
    }
}
