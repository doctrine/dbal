<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Schema\DB2SchemaManager;

/**
 * @covers \Doctrine\DBAL\Schema\DB2SchemaManager
 */
final class DB2SchemaManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var DB2SchemaManager
     */
    private $manager;

    protected function setUp()
    {
        $eventManager = new EventManager();
        $driverMock = $this->getMock(Driver::class);
        $platform = $this->getMock(DB2Platform::class);
        $this->conn = $this->getMock(
            Connection::class,
            ['fetchAll'],
            [['platform' => $platform], $driverMock, new Configuration(), $eventManager]
        );
        $this->manager = new DB2SchemaManager($this->conn);
    }

    public function testListTableNamesFiltersAssetNamesCorrectly()
    {
        $this->conn->getConfiguration()->setFilterSchemaAssetsExpression('/^(?!T_)/');
        $this->conn->expects($this->once())->method('fetchAll')->will($this->returnValue([
            [
                'name' => 'FOO',
            ],
            [
                'name' => 'T_FOO',
            ],
            [
                'name' => 'BAR',
            ],
            [
                'name' => 'T_BAR',
            ],
        ]));

        $this->assertSame(
            [
                'FOO',
                'BAR',
            ],
            $this->manager->listTableNames()
        );
    }
}
