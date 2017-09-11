<?php

namespace Doctrine\Tests\DBAL\Sharding\ShardChoser;

use Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser;

class MultiTenantShardChoserTest extends \PHPUnit\Framework\TestCase
{
    public function testPickShard()
    {
        $choser = new MultiTenantShardChoser();
        $conn = $this->createConnectionMock();

        self::assertEquals(1, $choser->pickShard(1, $conn));
        self::assertEquals(2, $choser->pickShard(2, $conn));
    }

    private function createConnectionMock()
    {
        return $this->getMockBuilder('Doctrine\DBAL\Sharding\PoolingShardConnection')
            ->setMethods(array('connect', 'getParams', 'fetchAll'))
            ->disableOriginalConstructor()
            ->getMock();
    }
}

