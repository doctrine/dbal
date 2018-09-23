<?php

namespace Doctrine\Tests\DBAL\Sharding\ShardChoser;

use Doctrine\DBAL\Sharding\PoolingShardConnection;
use Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser;
use PHPUnit\Framework\TestCase;

class MultiTenantShardChoserTest extends TestCase
{
    public function testPickShard()
    {
        $choser = new MultiTenantShardChoser();
        $conn   = $this->createConnectionMock();

        self::assertEquals(1, $choser->pickShard(1, $conn));
        self::assertEquals(2, $choser->pickShard(2, $conn));
    }

    private function createConnectionMock()
    {
        return $this->getMockBuilder(PoolingShardConnection::class)
            ->setMethods(['connect', 'getParams', 'fetchAll'])
            ->disableOriginalConstructor()
            ->getMock();
    }
}
