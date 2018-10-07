<?php

namespace Doctrine\Tests\DBAL\Sharding;

use Doctrine\DBAL\Sharding\PoolingShardConnection;
use Doctrine\DBAL\Sharding\PoolingShardManager;
use Doctrine\DBAL\Sharding\ShardChoser\ShardChoser;
use PHPUnit\Framework\TestCase;

class PoolingShardManagerTest extends TestCase
{
    private function createConnectionMock()
    {
        return $this->getMockBuilder(PoolingShardConnection::class)
            ->setMethods(['connect', 'getParams', 'fetchAll'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createPassthroughShardChoser()
    {
        $mock = $this->createMock(ShardChoser::class);
        $mock->expects($this->any())
             ->method('pickShard')
             ->will($this->returnCallback(static function ($value) {
                return $value;
             }));
        return $mock;
    }

    private function createStaticShardChooser()
    {
        $mock = $this->createMock(ShardChoser::class);
        $mock->expects($this->any())
            ->method('pickShard')
            ->willReturn(1);
        return $mock;
    }

    public function testSelectGlobal()
    {
        $conn = $this->createConnectionMock();
        $conn->expects($this->once())->method('connect')->with($this->equalTo(0));

        $shardManager = new PoolingShardManager($conn);
        $shardManager->selectGlobal();

        self::assertNull($shardManager->getCurrentDistributionValue());
    }

    public function testSelectShard()
    {
        $shardId = 10;
        $conn    = $this->createConnectionMock();
        $conn->expects($this->at(0))->method('getParams')->will($this->returnValue(['shardChoser' => $this->createPassthroughShardChoser()]));
        $conn->expects($this->at(1))->method('connect')->with($this->equalTo($shardId));

        $shardManager = new PoolingShardManager($conn);
        $shardManager->selectShard($shardId);

        self::assertEquals($shardId, $shardManager->getCurrentDistributionValue());
    }

    public function testGetShards()
    {
        $conn = $this->createConnectionMock();
        $conn->expects($this->any())->method('getParams')->will(
            $this->returnValue(
                ['shards' => [ ['id' => 1], ['id' => 2] ], 'shardChoser' => $this->createPassthroughShardChoser()]
            )
        );

        $shardManager = new PoolingShardManager($conn);
        $shards       = $shardManager->getShards();

        self::assertEquals([['id' => 1], ['id' => 2]], $shards);
    }

    public function testQueryAll()
    {
        $sql    = 'SELECT * FROM table';
        $params = [1];
        $types  = [1];

        $conn = $this->createConnectionMock();
        $conn->expects($this->at(0))->method('getParams')->will($this->returnValue(
            ['shards' => [ ['id' => 1], ['id' => 2] ], 'shardChoser' => $this->createPassthroughShardChoser()]
        ));
        $conn->expects($this->at(1))->method('getParams')->will($this->returnValue(
            ['shards' => [ ['id' => 1], ['id' => 2] ], 'shardChoser' => $this->createPassthroughShardChoser()]
        ));
        $conn->expects($this->at(2))->method('connect')->with($this->equalTo(1));
        $conn->expects($this->at(3))
             ->method('fetchAll')
             ->with($this->equalTo($sql), $this->equalTo($params), $this->equalTo($types))
             ->will($this->returnValue([ ['id' => 1] ]));
        $conn->expects($this->at(4))->method('connect')->with($this->equalTo(2));
        $conn->expects($this->at(5))
             ->method('fetchAll')
             ->with($this->equalTo($sql), $this->equalTo($params), $this->equalTo($types))
             ->will($this->returnValue([ ['id' => 2] ]));

        $shardManager = new PoolingShardManager($conn);
        $result       = $shardManager->queryAll($sql, $params, $types);

        self::assertEquals([['id' => 1], ['id' => 2]], $result);
    }

    public function testQueryAllWithStaticShardChoser()
    {
        $sql    = 'SELECT * FROM table';
        $params = [1];
        $types  = [1];

        $conn = $this->createConnectionMock();
        $conn->expects($this->at(0))->method('getParams')->will($this->returnValue(
            ['shards' => [ ['id' => 1], ['id' => 2] ], 'shardChoser' => $this->createStaticShardChooser()]
        ));
        $conn->expects($this->at(1))->method('getParams')->will($this->returnValue(
            ['shards' => [ ['id' => 1], ['id' => 2] ], 'shardChoser' => $this->createStaticShardChooser()]
        ));
        $conn->expects($this->at(2))->method('connect')->with($this->equalTo(1));
        $conn->expects($this->at(3))
            ->method('fetchAll')
            ->with($this->equalTo($sql), $this->equalTo($params), $this->equalTo($types))
            ->will($this->returnValue([ ['id' => 1] ]));
        $conn->expects($this->at(4))->method('connect')->with($this->equalTo(2));
        $conn->expects($this->at(5))
            ->method('fetchAll')
            ->with($this->equalTo($sql), $this->equalTo($params), $this->equalTo($types))
            ->will($this->returnValue([ ['id' => 2] ]));

        $shardManager = new PoolingShardManager($conn);
        $result       = $shardManager->queryAll($sql, $params, $types);

        self::assertEquals([['id' => 1], ['id' => 2]], $result);
    }
}
