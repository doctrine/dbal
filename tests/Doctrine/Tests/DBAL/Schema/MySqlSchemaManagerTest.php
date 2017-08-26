<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Schema\MySqlSchemaManager;

class MySqlSchemaManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     *
     * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    private $manager;

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
        $this->assertEquals(1, count($fkeys), "Table has to have one foreign key.");

        $this->assertInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint', $fkeys[0]);
        $this->assertEquals(array('column_1', 'column_2', 'column_3'), array_map('strtolower', $fkeys[0]->getLocalColumns()));
        $this->assertEquals(array('column_1', 'column_2', 'column_3'), array_map('strtolower', $fkeys[0]->getForeignColumns()));
    }

    public function testPortableTableColumnDefinition()
    {
        $eventManager = new EventManager();
        $driverMock = $this->createMock('Doctrine\DBAL\Driver');
        $platform = new \Doctrine\DBAL\Platforms\MySqlPlatform();
        $conn = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->setMethods(array('fetchAll'))
            ->setConstructorArgs(array(array('platform' => $platform), $driverMock, new Configuration(), $eventManager))
            ->getMock();
        $this->manager = new MySqlSchemaManager($conn);


        $conn->expects($this->once())->method('fetchAll')->will($this->returnValue($this->getColumnDefinition()));
        $columns = $this->manager->listTableColumns('dummy');
        $this->assertEquals(count($this->getColumnDefinition()), count($columns));
        $this->assertTrue($columns['id']->getNotnull());
        $this->assertNull($columns['id']->getDefault());
        $this->assertFalse($columns['updated_at']->getNotnull());
        $this->assertNull($columns['updated_at']->getDefault());
        $this->assertFalse($columns['created_by']->getNotnull());
        $this->assertNull($columns['created_by']->getDefault());

    }

    public function getColumnDefinition()
    {
        return [
            [
                'Field'   => 'id',
                'Type'    => 'int(10) unsigned',
                'Null'    => 'NO',
                'Key'     => 'PRI',
                'Default' => null,
                'Extra'   => 'auto_increment',
                'Comment' => '',
                'CharacterSet' => null,
                'Collation' => null
            ],
            [
                'Field'   => 'updated_at',
                'Type'    => 'datetime',
                'Null'    => 'YES',
                'Key'     => '',
                'Default' => 'NULL', // MariaDB 10.2.7 return 'NULL'
                'Extra'   => '',
                'Comment' => 'Record last update timestamp',
                'CharacterSet' => null,
                'Collation' => null
            ],[
                'Field'=> 'created_by',
                'Type'=> 'varchar(40)',
                'Null'=> 'YES',
                'Key'=>  '',
                'Default'=> null, // MySQL 5.1 - 5.7 sends: null
                'Extra' => '',
                'Comment' => 'Creator name',
                'CharacterSet' => 'utf8',
                'Collation'=> 'utf8_unicode_ci'
            ]
        ];

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
