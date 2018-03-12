<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\Schema;

class SchemaSqlCollectorTest extends \PHPUnit\Framework\TestCase
{
    public function testCreateSchema()
    {
        $platformMock = $this->getMockBuilder('Doctrine\DBAL\Platforms\MySqlPlatform')
            ->setMethods(array('getCreateTableSql', 'getCreateSequenceSql', 'getCreateForeignKeySql'))
            ->getMock();
        $platformMock->expects($this->exactly(2))
                     ->method('getCreateTableSql')
                     ->will($this->returnValue(array("foo")));
        $platformMock->expects($this->exactly(1))
                     ->method('getCreateSequenceSql')
                     ->will($this->returnValue("bar"));
        $platformMock->expects($this->exactly(1))
                     ->method('getCreateForeignKeySql')
                     ->will($this->returnValue("baz"));

        $schema = $this->createFixtureSchema();

        $sql = $schema->toSql($platformMock);

        self::assertEquals(array("foo", "foo", "bar", "baz"), $sql);
    }

    public function testDropSchema()
    {
        $platformMock = $this->getMockBuilder('Doctrine\DBAL\Platforms\MySqlPlatform')
            ->setMethods(array('getDropTableSql', 'getDropSequenceSql', 'getDropForeignKeySql'))
            ->getMock();
        $platformMock->expects($this->exactly(2))
                     ->method('getDropTableSql')
                     ->will($this->returnValue("tbl"));
        $platformMock->expects($this->exactly(1))
                     ->method('getDropSequenceSql')
                     ->will($this->returnValue("seq"));
        $platformMock->expects($this->exactly(1))
                     ->method('getDropForeignKeySql')
                     ->will($this->returnValue("fk"));

        $schema = $this->createFixtureSchema();

        $sql = $schema->toDropSql($platformMock);

        self::assertEquals(array("fk", "seq", "tbl", "tbl"), $sql);
    }

    /**
     * @return Schema
     */
    public function createFixtureSchema()
    {
        $schema = new Schema();
        $tableA = $schema->createTable("foo");
        $tableA->addColumn("id", 'integer');
        $tableA->addColumn("bar", 'string', array('length' => 255));
        $tableA->setPrimaryKey(array("id"));

        $schema->createSequence("foo_seq");

        $tableB = $schema->createTable("bar");
        $tableB->addColumn("id", 'integer');
        $tableB->setPrimaryKey(array("id"));

        $tableA->addForeignKeyConstraint($tableB, array("bar"), array("id"));

        return $schema;
    }
}
