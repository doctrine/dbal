<?php

namespace Doctrine\Tests\DBAL\Sharding\SQLAzure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Sharding\ShardingException;
use Doctrine\DBAL\Sharding\SQLAzure\SQLAzureShardManager;
use PHPUnit\Framework\TestCase;

class SQLAzureShardManagerTest extends TestCase
{
    public function testNoFederationName() : void
    {
        $this->expectException(ShardingException::class);
        $this->expectExceptionMessage('SQLAzure requires a federation name to be set during sharding configuration.');

        $conn = $this->createConnection(['sharding' => ['distributionKey' => 'abc', 'distributionType' => 'integer']]);
        new SQLAzureShardManager($conn);
    }

    public function testNoDistributionKey() : void
    {
        $this->expectException(ShardingException::class);
        $this->expectExceptionMessage('SQLAzure requires a distribution key to be set during sharding configuration.');

        $conn = $this->createConnection(['sharding' => ['federationName' => 'abc', 'distributionType' => 'integer']]);
        new SQLAzureShardManager($conn);
    }

    public function testNoDistributionType() : void
    {
        $this->expectException(ShardingException::class);

        $conn = $this->createConnection(['sharding' => ['federationName' => 'abc', 'distributionKey' => 'foo']]);
        new SQLAzureShardManager($conn);
    }

    public function testGetDefaultDistributionValue() : void
    {
        $conn = $this->createConnection(['sharding' => ['federationName' => 'abc', 'distributionKey' => 'foo', 'distributionType' => 'integer']]);

        $sm = new SQLAzureShardManager($conn);
        self::assertNull($sm->getCurrentDistributionValue());
    }

    public function testSelectGlobalTransactionActive() : void
    {
        $conn = $this->createConnection(['sharding' => ['federationName' => 'abc', 'distributionKey' => 'foo', 'distributionType' => 'integer']]);
        $conn->expects($this->at(1))->method('isTransactionActive')->will($this->returnValue(true));

        $this->expectException(ShardingException::class);
        $this->expectExceptionMessage('Cannot switch shard during an active transaction.');

        $sm = new SQLAzureShardManager($conn);
        $sm->selectGlobal();
    }

    public function testSelectGlobal() : void
    {
        $conn = $this->createConnection(['sharding' => ['federationName' => 'abc', 'distributionKey' => 'foo', 'distributionType' => 'integer']]);
        $conn->expects($this->at(1))->method('isTransactionActive')->will($this->returnValue(false));
        $conn->expects($this->at(2))->method('exec')->with($this->equalTo('USE FEDERATION ROOT WITH RESET'));

        $sm = new SQLAzureShardManager($conn);
        $sm->selectGlobal();
    }

    public function testSelectShard() : void
    {
        $conn = $this->createConnection(['sharding' => ['federationName' => 'abc', 'distributionKey' => 'foo', 'distributionType' => 'integer']]);
        $conn->expects($this->at(1))->method('isTransactionActive')->will($this->returnValue(true));

        $this->expectException(ShardingException::class);
        $this->expectExceptionMessage('Cannot switch shard during an active transaction.');

        $sm = new SQLAzureShardManager($conn);
        $sm->selectShard(1234);

        self::assertEquals(1234, $sm->getCurrentDistributionValue());
    }

    /**
     * @param mixed[] $params
     */
    private function createConnection(array $params) : Connection
    {
        $conn = $this->getMockBuilder(Connection::class)
            ->onlyMethods(['getParams', 'exec', 'isTransactionActive'])
            ->disableOriginalConstructor()
            ->getMock();
        $conn->expects($this->at(0))->method('getParams')->will($this->returnValue($params));

        return $conn;
    }
}
