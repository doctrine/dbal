<?php

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function array_map;

class MySQLSchemaManagerTest extends TestCase
{
    /** @var AbstractSchemaManager */
    private $manager;

    /** @var Connection&MockObject */
    private $conn;

    protected function setUp(): void
    {
        $eventManager = new EventManager();
        $driverMock   = $this->createMock(Driver::class);

        $platform = $this->createMock(AbstractMySQLPlatform::class);
        $platform->method('getListTableForeignKeysSQL')
            ->willReturn('');

        $this->conn    = $this->getMockBuilder(Connection::class)
            ->onlyMethods(['fetchAllAssociative'])
            ->setConstructorArgs([[], $driverMock, new Configuration(), $eventManager])
            ->getMock();
        $this->manager = new MySQLSchemaManager($this->conn, $platform);
    }

    public function testCompositeForeignKeys(): void
    {
        $this->conn->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn($this->getFKDefinition());

        $fkeys = $this->manager->listTableForeignKeys('dummy', 'dummy');
        self::assertCount(1, $fkeys, 'Table has to have one foreign key.');

        self::assertInstanceOf(ForeignKeyConstraint::class, $fkeys[0]);

        self::assertEquals([
            'column_1',
            'column_2',
            'column_3',
        ], array_map('strtolower', $fkeys[0]->getLocalColumns()));

        self::assertEquals([
            'column_1',
            'column_2',
            'column_3',
        ], array_map('strtolower', $fkeys[0]->getForeignColumns()));
    }

    /**
     * @return string[][]
     */
    public function getFKDefinition(): array
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
