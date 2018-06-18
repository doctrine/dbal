<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\MySqlSchemaManager;
use function array_map;

class MySqlSchemaManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    private $manager;

    /** @var Connection */
    private $conn;

    protected function setUp()
    {
        $eventManager = new EventManager();
        $driverMock = $this->createMock('Doctrine\DBAL\Driver');
        $platform = $this->createMock('Doctrine\DBAL\Platforms\MySqlPlatform');
        $this->conn = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->setMethods(array('fetchAll'))
            ->setConstructorArgs(array(array('platform' => $platform), $driverMock, new Configuration(), $eventManager))
            ->getMock();
        $this->manager = new MySqlSchemaManager($this->conn);
    }

    public function testCompositeForeignKeys()
    {
        $this->conn->expects($this->once())->method('fetchAll')->will($this->returnValue($this->getFKDefinition()));
        $fkeys = $this->manager->listTableForeignKeys('dummy');
        self::assertCount(1, $fkeys, "Table has to have one foreign key.");

        self::assertInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint', $fkeys[0]);
        self::assertEquals(array('column_1', 'column_2', 'column_3'), array_map('strtolower', $fkeys[0]->getLocalColumns()));
        self::assertEquals(array('column_1', 'column_2', 'column_3'), array_map('strtolower', $fkeys[0]->getForeignColumns()));
    }

    public function getFKDefinition()
    {
        return array(
            array(
                "CONSTRAINT_NAME" => "FK_C1B1712387FE737264DE5A5511B8B3E",
                "COLUMN_NAME" => "column_1",
                "REFERENCED_TABLE_NAME" => "dummy",
                "REFERENCED_COLUMN_NAME" => "column_1",
                "update_rule" => "RESTRICT",
                "delete_rule" => "RESTRICT",
            ),
            array(
                "CONSTRAINT_NAME" => "FK_C1B1712387FE737264DE5A5511B8B3E",
                "COLUMN_NAME" => "column_2",
                "REFERENCED_TABLE_NAME" => "dummy",
                "REFERENCED_COLUMN_NAME" => "column_2",
                "update_rule" => "RESTRICT",
                "delete_rule" => "RESTRICT",
            ),
            array(
                "CONSTRAINT_NAME" => "FK_C1B1712387FE737264DE5A5511B8B3E",
                "COLUMN_NAME" => "column_3",
                "REFERENCED_TABLE_NAME" => "dummy",
                "REFERENCED_COLUMN_NAME" => "column_3",
                "update_rule" => "RESTRICT",
                "delete_rule" => "RESTRICT",
            )
        );
    }
}
