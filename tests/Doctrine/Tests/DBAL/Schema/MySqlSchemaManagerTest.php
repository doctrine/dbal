<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\MySqlSchemaManager;
use PHPUnit\Framework\TestCase;
use function array_map;

class MySqlSchemaManagerTest extends TestCase
{
    /** @var AbstractSchemaManager */
    private $manager;

    /** @var Connection */
    private $conn;

    protected function setUp() : void
    {
        $eventManager  = new EventManager();
        $driverMock    = $this->createMock(Driver::class);
        $platform      = $this->createMock(MySqlPlatform::class);
        $this->conn    = $this->getMockBuilder(Connection::class)
            ->onlyMethods(['fetchAll'])
            ->setConstructorArgs([['platform' => $platform], $driverMock, new Configuration(), $eventManager])
            ->getMock();
        $this->manager = new MySqlSchemaManager($this->conn);
    }

    public function testCompositeForeignKeys() : void
    {
        $this->conn->expects($this->once())->method('fetchAll')->will($this->returnValue($this->getFKDefinition()));
        $fkeys = $this->manager->listTableForeignKeys('dummy');
        self::assertCount(1, $fkeys, 'Table has to have one foreign key.');

        self::assertInstanceOf(ForeignKeyConstraint::class, $fkeys[0]);
        self::assertEquals(['column_1', 'column_2', 'column_3'], array_map('strtolower', $fkeys[0]->getLocalColumns()));
        self::assertEquals(['column_1', 'column_2', 'column_3'], array_map('strtolower', $fkeys[0]->getForeignColumns()));
    }

    /**
     * @return string[][]
     */
    public function getFKDefinition() : array
    {
        return [
            [
                'CONSTRAINT_NAME' => 'FK_C1B1712387FE737264DE5A5511B8B3E',
                'COLUMN_NAME' => 'column_1',
                'REFERENCED_TABLE_NAME' => 'dummy',
                'REFERENCED_COLUMN_NAME' => 'column_1',
                'update_rule' => 'RESTRICT',
                'delete_rule' => 'RESTRICT',
            ],
            [
                'CONSTRAINT_NAME' => 'FK_C1B1712387FE737264DE5A5511B8B3E',
                'COLUMN_NAME' => 'column_2',
                'REFERENCED_TABLE_NAME' => 'dummy',
                'REFERENCED_COLUMN_NAME' => 'column_2',
                'update_rule' => 'RESTRICT',
                'delete_rule' => 'RESTRICT',
            ],
            [
                'CONSTRAINT_NAME' => 'FK_C1B1712387FE737264DE5A5511B8B3E',
                'COLUMN_NAME' => 'column_3',
                'REFERENCED_TABLE_NAME' => 'dummy',
                'REFERENCED_COLUMN_NAME' => 'column_3',
                'update_rule' => 'RESTRICT',
                'delete_rule' => 'RESTRICT',
            ],
        ];
    }
}
