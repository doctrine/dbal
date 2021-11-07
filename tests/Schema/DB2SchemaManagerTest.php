<?php

namespace Doctrine\DBAL\Tests\Schema;

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
    /** @var Connection&MockObject */
    private $conn;

    /** @var DB2SchemaManager */
    private $manager;

    protected function setUp(): void
    {
        $eventManager  = new EventManager();
        $driverMock    = $this->createMock(Driver::class);
        $platform      = $this->createMock(DB2Platform::class);
        $this->conn    = $this
            ->getMockBuilder(Connection::class)
            ->onlyMethods(['fetchAllAssociative', 'quote'])
            ->setConstructorArgs([[], $driverMock, new Configuration(), $eventManager])
            ->getMock();
        $this->manager = new DB2SchemaManager($this->conn, $platform);
    }

    /**
     * @see https://github.com/doctrine/dbal/issues/2701
     */
    public function testListTableNamesFiltersAssetNamesCorrectly(): void
    {
        $this->conn->getConfiguration()->setSchemaAssetsFilter(static function (string $name): bool {
            return preg_match('/^(?!T_)/', $name) === 1;
        });
        $this->conn->expects(self::once())->method('fetchAllAssociative')->willReturn([
            ['name' => 'FOO'],
            ['name' => 'T_FOO'],
            ['name' => 'BAR'],
            ['name' => 'T_BAR'],
        ]);

        self::assertSame(
            [
                'FOO',
                'BAR',
            ],
            $this->manager->listTableNames()
        );
    }

    public function testListTableNamesFiltersAssetNamesCorrectlyWithCallable(): void
    {
        $accepted = ['T_FOO', 'T_BAR'];
        $this->conn->getConfiguration()->setSchemaAssetsFilter(
            static function (string $assetName) use ($accepted): bool {
                return in_array($assetName, $accepted, true);
            }
        );
        $this->conn->expects(self::any())->method('quote');
        $this->conn->expects(self::once())->method('fetchAllAssociative')->willReturn([
            ['name' => 'FOO'],
            ['name' => 'T_FOO'],
            ['name' => 'BAR'],
            ['name' => 'T_BAR'],
        ]);

        self::assertSame(
            [
                'T_FOO',
                'T_BAR',
            ],
            $this->manager->listTableNames()
        );
    }
}
