<?php

namespace Doctrine\DBAL\Sharding;

use Doctrine\DBAL\Sharding\ShardChoser\ShardChoser;

/**
 * Shard Manager for the Connection Pooling Shard Strategy
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class PoolingShardManager implements ShardManager
{
    /**
     * @var PoolingShardConnection
     */
    private $conn;

    /**
     * @var ShardChoser
     */
    private $choser;

    /**
     * @var string|null
     */
    private $currentDistributionValue;

    /**
     * @param PoolingShardConnection $conn
     */
    public function __construct(PoolingShardConnection $conn)
    {
        $params       = $conn->getParams();
        $this->conn   = $conn;
        $this->choser = $params['shardChoser'];
    }

    /**
     * @return void
     */
    public function selectGlobal()
    {
        $this->conn->connect(0);
        $this->currentDistributionValue = null;
    }

    /**
     * @param string $distributionValue
     *
     * @return void
     */
    public function selectShard($distributionValue)
    {
        $shardId = $this->choser->pickShard($distributionValue, $this->conn);
        $this->conn->connect($shardId);
        $this->currentDistributionValue = $distributionValue;
    }

    /**
     * @return string|null
     */
    public function getCurrentDistributionValue()
    {
        return $this->currentDistributionValue;
    }

    /**
     * @return array
     */
    public function getShards()
    {
        $params = $this->conn->getParams();
        $shards = array();

        foreach ($params['shards'] as $shard) {
            $shards[] = array('id' => $shard['id']);
        }

        return $shards;
    }

    /**
     * @param string $sql
     * @param array  $params
     * @param array  $types
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public function queryAll($sql, array $params, array $types)
    {
        $shards = $this->getShards();
        if (!$shards) {
            throw new \RuntimeException("No shards found.");
        }

        $result = array();
        $oldDistribution = $this->getCurrentDistributionValue();

        foreach ($shards as $shard) {
            $this->conn->connect($shard['id']);
            foreach ($this->conn->fetchAll($sql, $params, $types) as $row) {
                $result[] = $row;
            }
        }

        if ($oldDistribution === null) {
            $this->selectGlobal();
        } else {
            $this->selectShard($oldDistribution);
        }

        return $result;
    }
}
