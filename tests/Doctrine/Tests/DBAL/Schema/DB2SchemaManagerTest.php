<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Schema\DB2SchemaManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use function in_array;
use function preg_match;

/**
 * @covers \Doctrine\DBAL\Schema\DB2SchemaManager
 */
final class DB2SchemaManagerTest extends TestCase
{
    /** @var Connection|MockObject */
    private $conn;

    /** @var DB2SchemaManager */
    private $manager;

    protected function setUp() : void
    {
        $eventManager = new EventManager();
        $driverMock   = $this->createMock(Driver::class);
        $platform     = $this->createMock(DB2Platform::class);
        $this->conn   = $this
            ->getMockBuilder(Connection::class)
            ->setMethods(['fetchAll', 'getUsername'])
            ->setConstructorArgs([['platform' => $platform], $driverMock, new Configuration(), $eventManager])
            ->getMock();

        $this->conn->expects($this->any())
            ->method('getUsername')
            ->willReturn('db2inst1');

        $this->manager = new DB2SchemaManager($this->conn);
    }

    /**
     * @see https://github.com/doctrine/dbal/issues/2701
     *
     * @return void
     *
     * @group DBAL-2701
     */
    public function testListTableNamesFiltersAssetNamesCorrectly()
    {
        $this->conn->getConfiguration()->setSchemaAssetsFilter(static function (string $name) : bool {
            return preg_match('/^(?!T_)/', $name) === 1;
        });
        $this->conn->expects($this->once())->method('fetchAll')->will($this->returnValue([
            ['name' => 'FOO'],
            ['name' => 'T_FOO'],
            ['name' => 'BAR'],
            ['name' => 'T_BAR'],
        ]));

        self::assertSame(
            [
                'FOO',
                'BAR',
            ],
            $this->manager->listTableNames()
        );
    }

    /**
     * @return void
     */
    public function testListTableNamesFiltersAssetNamesCorrectlyWithCallable()
    {
        $accepted = ['T_FOO', 'T_BAR'];
        $this->conn->getConfiguration()->setSchemaAssetsFilter(static function ($assetName) use ($accepted) {
            return in_array($assetName, $accepted);
        });

        $this->conn->expects($this->once())->method('fetchAll')->will($this->returnValue([
            ['name' => 'FOO'],
            ['name' => 'T_FOO'],
            ['name' => 'BAR'],
            ['name' => 'T_BAR'],
        ]));

        self::assertSame(
            [
                'T_FOO',
                'T_BAR',
            ],
            $this->manager->listTableNames()
        );
    }
}
