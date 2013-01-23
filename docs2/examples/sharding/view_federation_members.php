<?php
// view_federation_members.php
require_once "bootstrap.php";

$shards = $shardManager->getShards();
foreach ($shards as $shard) {
    print_r($shard);
}
