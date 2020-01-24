<?php
// query_filtering_on.php
require_once "bootstrap.php";

$shardManager->setFilteringEnabled(true);
$shardManager->selectShard(55);

$data = $conn->fetchAll('SELECT * FROM Customers');
print_r($data);
