<?php

namespace Doctrine\Tests\DBAL\Sharding\SQLAzure;

use Doctrine\DBAL\Sharding\SQLAzure\SQLAzureShardManager;

class SQLAzureShardManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testNoFederationName()
    {
        $this->setExpectedException('Doctrine\DBAL\Sharding\ShardingException', 'SQLAzure requires a federation name to be set during sharding configuration.');

        $conn = $this->createConnection(array('sharding' => array('distributionKey' => 'abc', 'distributionType' => 'integer')));
        $sm = new SQLAzureShardManager($conn);
    }

    public function testNoDistributionKey()
    {
        $this->setExpectedException('Doctrine\DBAL\Sharding\ShardingException', 'SQLAzure requires a distribution key to be set during sharding configuration.');

        $conn = $this->createConnection(array('sharding' => array('federationName' => 'abc', 'distributionType' => 'integer')));
        $sm = new SQLAzureShardManager($conn);
    }

    public function testNoDistributionType()
    {
        $this->setExpectedException('Doctrine\DBAL\Sharding\ShardingException');

        $conn = $this->createConnection(array('sharding' => array('federationName' => 'abc', 'distributionKey' => 'foo')));
        $sm = new SQLAzureShardManager($conn);
    }

    public function testGetDefaultDistributionValue()
    {
        $conn = $this->createConnection(array('sharding' => array('federationName' => 'abc', 'distributionKey' => 'foo', 'distributionType' => 'integer')));

        $sm = new SQLAzureShardManager($conn);
        $this->assertNull($sm->getCurrentDistributionValue());
    }

    public function testSelectGlobalTransactionActive()
    {
        $conn = $this->createConnection(array('sharding' => array('federationName' => 'abc', 'distributionKey' => 'foo', 'distributionType' => 'integer')));
        $conn->expects($this->at(1))->method('isTransactionActive')->will($this->returnValue(true));

        $this->setExpectedException('Doctrine\DBAL\Sharding\ShardingException', 'Cannot switch shard during an active transaction.');

        $sm = new SQLAzureShardManager($conn);
        $sm->selectGlobal();
    }

    public function testSelectGlobal()
    {
        $conn = $this->createConnection(array('sharding' => array('federationName' => 'abc', 'distributionKey' => 'foo', 'distributionType' => 'integer')));
        $conn->expects($this->at(1))->method('isTransactionActive')->will($this->returnValue(false));
        $conn->expects($this->at(2))->method('exec')->with($this->equalTo('USE FEDERATION ROOT WITH RESET'));

        $sm = new SQLAzureShardManager($conn);
        $sm->selectGlobal();
    }

    public function testSelectShard()
    {
        $conn = $this->createConnection(array('sharding' => array('federationName' => 'abc', 'distributionKey' => 'foo', 'distributionType' => 'integer')));
        $conn->expects($this->at(1))->method('isTransactionActive')->will($this->returnValue(true));

        $this->setExpectedException('Doctrine\DBAL\Sharding\ShardingException', 'Cannot switch shard during an active transaction.');

        $sm = new SQLAzureShardManager($conn);
        $sm->selectShard(1234);

        $this->assertEquals(1234, $sm->getCurrentDistributionValue());
    }

    public function testSelectShardNoDistributionValue()
    {
        $conn = $this->createConnection(array('sharding' => array('federationName' => 'abc', 'distributionKey' => 'foo', 'distributionType' => 'integer')));
        $conn->expects($this->at(1))->method('isTransactionActive')->will($this->returnValue(false));

        $this->setExpectedException('Doctrine\DBAL\Sharding\ShardingException', 'You have to specify a string or integer as shard distribution value.');

        $sm = new SQLAzureShardManager($conn);
        $sm->selectShard(null);
    }

    private function createConnection(array $params)
    {
        $conn = $this->getMock('Doctrine\DBAL\Connection', array('getParams', 'exec', 'isTransactionActive'), array(), '', false);
        $conn->expects($this->at(0))->method('getParams')->will($this->returnValue($params));
        return $conn;
    }
}

